<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'complete_upgrade_to_workorder'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION complete_upgrade_to_workorder(
                IN _decision_id                    BIGINT,
                IN _asset_requisition_id           BIGINT,
                IN _asset_requisition_data_id      BIGINT,
                IN _work_order_id                  BIGINT,
                IN _action_by                      BIGINT   DEFAULT NULL,
                IN _tenant_id                      BIGINT   DEFAULT NULL
            )
            RETURNS TABLE (
                status            TEXT,
                message           TEXT,
                result_data       JSON
            )
            LANGUAGE plpgsql
            AS $fn$
            DECLARE
                v_decision_status       VARCHAR;
                v_requisition_status    VARCHAR;
                v_inserted_action_id    BIGINT;
                v_log_type_id           BIGINT;
                v_result_json           JSON;
                v_log_data              JSONB;
                v_log_success           BOOLEAN := FALSE;
                v_error_message         TEXT;
                v_requisition_number    VARCHAR;
                v_asset_id              BIGINT;
                v_created_by            BIGINT;
                v_created_by_name       VARCHAR;
                v_created_by_email      VARCHAR;
                v_asset_name            VARCHAR;
                v_upgrade_description   TEXT;
                v_job_title             VARCHAR;
                v_work_order_number     VARCHAR;
            BEGIN

                -- ═══════════════════════════════════════════════════════════════
                -- STEP 1: Input Validation
                -- ═══════════════════════════════════════════════════════════════

                IF _decision_id IS NULL THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT,
                        'Decision ID is required'::TEXT,
                        NULL::JSON;
                    RETURN;
                END IF;

                IF _asset_requisition_id IS NULL THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT,
                        'Asset requisition ID is required'::TEXT,
                        NULL::JSON;
                    RETURN;
                END IF;

                IF _asset_requisition_data_id IS NULL THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT,
                        'Asset requisition data ID is required'::TEXT,
                        NULL::JSON;
                    RETURN;
                END IF;

                IF _work_order_id IS NULL THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT,
                        'Work order ID is required'::TEXT,
                        NULL::JSON;
                    RETURN;
                END IF;

                IF _action_by IS NULL THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT,
                        'Action by user ID is required'::TEXT,
                        NULL::JSON;
                    RETURN;
                END IF;

                -- ═══════════════════════════════════════════════════════════════
                -- STEP 2: Verify decision exists & fetch current status
                -- ═══════════════════════════════════════════════════════════════

                SELECT ard.status
                INTO v_decision_status
                FROM asset_requisition_decision ard
                WHERE ard.id = _decision_id
                  AND ard.asset_requisition_id = _asset_requisition_id
                  AND ard.tenant_id = _tenant_id
                  AND ard.deleted_at IS NULL;

                IF v_decision_status IS NULL THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT,
                        'Decision record not found or does not belong to this tenant'::TEXT,
                        NULL::JSON;
                    RETURN;
                END IF;

                -- Terminal state guard
                IF v_decision_status = 'SENT_TO_WORK_ORDER' THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT,
                        'This requisition has already been sent to a work order. No further actions are allowed.'::TEXT,
                        NULL::JSON;
                    RETURN;
                END IF;

                -- ═══════════════════════════════════════════════════════════════
                -- STEP 3: Fetch upgrade requisition details
                -- ═══════════════════════════════════════════════════════════════

                SELECT ureq.status, ureq.asset_id, ureq.upgrade_description,
                       ureq.created_by, ar.requisition_id
                INTO v_requisition_status, v_asset_id, v_upgrade_description,
                     v_created_by, v_requisition_number
                FROM upgrade_asset_requisitions ureq
                LEFT JOIN asset_requisitions ar ON ar.id = ureq.asset_requisition_id
                WHERE ureq.id = _asset_requisition_data_id
                  AND ureq.tenant_id = _tenant_id
                  AND ureq.is_active = TRUE
                  AND ureq.deleted_at IS NULL;

                IF v_requisition_status IS NULL THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT,
                        'Upgrade asset requisition not found or inactive'::TEXT,
                        NULL::JSON;
                    RETURN;
                END IF;

                -- ═══════════════════════════════════════════════════════════════
                -- STEP 4: Get created_by user details (for email notification)
                -- ═══════════════════════════════════════════════════════════════

                SELECT u.name, u.email
                INTO v_created_by_name, v_created_by_email
                FROM users u
                WHERE u.id = v_created_by;

                -- Get asset name
               SELECT a.name
                INTO v_asset_name
                FROM asset_items ai
                JOIN assets a ON a.id = ai.asset_id
                WHERE ai.id = v_asset_id
                LIMIT 1;

                -- Get work order details
                SELECT wo.job_title, wo.work_order_number
                INTO v_job_title, v_work_order_number
                FROM work_orders wo
                WHERE wo.id = _work_order_id;

                -- ═══════════════════════════════════════════════════════════════
                -- STEP 5: Insert into asset_requisition_actions
                -- ═══════════════════════════════════════════════════════════════

                INSERT INTO asset_requisition_actions (
                    tenant_id,
                    decision_id,
                    asset_requisition_id,
                    action_by,
                    action_type,
                    reason,
                    work_order_id,
                    is_active,
                    created_at,
                    updated_at
                )
                VALUES (
                    _tenant_id,
                    _decision_id,
                    _asset_requisition_id,
                    _action_by,
                    'SENT_TO_WORK_ORDER',
                    'Upgrade requisition sent to work order',
                    _work_order_id,
                    TRUE,
                    NOW(),
                    NOW()
                )
                RETURNING id INTO v_inserted_action_id;

                -- ═══════════════════════════════════════════════════════════════
                -- STEP 6: Update asset_requisition_decision status
                -- ═══════════════════════════════════════════════════════════════

                UPDATE asset_requisition_decision
                SET status = 'SENT_TO_WORK_ORDER',
                    is_get_action = TRUE,
                    updated_at = NOW()
                WHERE id = _decision_id;

                -- ═══════════════════════════════════════════════════════════════
                -- STEP 7: Update asset_requisitions status
                -- ═══════════════════════════════════════════════════════════════

                UPDATE asset_requisitions
                SET requisition_status = 'SENT_TO_WORK_ORDER',
                    updated_at = NOW()
                WHERE id = _asset_requisition_id;

                -- ═══════════════════════════════════════════════════════════════
                -- STEP 8: Update upgrade_asset_requisitions status & work_order_id
                -- ═══════════════════════════════════════════════════════════════

                UPDATE upgrade_asset_requisitions
                SET status = 'SENT_TO_WORK_ORDER',
                    work_order_id = _work_order_id,
                    updated_at = NOW()
                WHERE id = _asset_requisition_data_id;

                -- ═══════════════════════════════════════════════════════════════
                -- STEP 9: Insert into asset_requisition_logs
                -- ═══════════════════════════════════════════════════════════════

                SELECT id INTO v_log_type_id
                FROM asset_requisition_log_types
                WHERE code = 'ASSET_UPGRADE_SUBMITTED_TO_WORKORDER'
                AND (tenant_id IS NULL OR tenant_id = _tenant_id)
                LIMIT 1;

                IF v_log_type_id IS NOT NULL THEN
                    INSERT INTO asset_requisition_logs (
                        tenant_id,
                        asset_requisition_id,
                        log_type_id,
                        action_by,
                        action_at,
                        payload,
                        is_active,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        _tenant_id,
                        _asset_requisition_id,
                        v_log_type_id,
                        _action_by,
                        NOW(),
                        jsonb_build_object(
                            'decision_id', _decision_id,
                            'action_id', v_inserted_action_id,
                            'work_order_id', _work_order_id,
                            'asset_id', v_asset_id,
                            'asset_requisition_data_id', _asset_requisition_data_id,
                            'action_type', 'SENT_TO_WORK_ORDER',
                            'completed_by', _action_by
                        ),
                        TRUE,
                        NOW(),
                        NOW()
                    );
                END IF;

                -- ═══════════════════════════════════════════════════════════════
                -- STEP 10: Build response JSON
                -- ═══════════════════════════════════════════════════════════════

                v_result_json := json_build_object(
                    'action_id', v_inserted_action_id,
                    'work_order_id', _work_order_id,
                    'work_order_number', v_work_order_number,
                    'decision_id', _decision_id,
                    'asset_requisition_id', _asset_requisition_id,
                    'asset_requisition_data_id', _asset_requisition_data_id,
                    'asset_id', v_asset_id,
                    'action_type', 'SENT_TO_WORK_ORDER',
                    'new_status', 'SENT_TO_WORK_ORDER',
                    'requisition_number', v_requisition_number,
                    'created_by_id', v_created_by,
                    'created_by_name', v_created_by_name,
                    'created_by_email', v_created_by_email,
                    'asset_name', v_asset_name,
                    'job_title', v_job_title,
                    'created_at', NOW()
                );

                -- ═══════════════════════════════════════════════════════════════
                -- STEP 11: Activity Log (system log)
                -- ═══════════════════════════════════════════════════════════════

                BEGIN
                    v_log_data := jsonb_build_object(
                        'action_id', v_inserted_action_id,
                        'work_order_id', _work_order_id,
                        'decision_id', _decision_id,
                        'asset_requisition_id', _asset_requisition_id,
                        'asset_requisition_data_id', _asset_requisition_data_id,
                        'asset_id', v_asset_id,
                        'action_by', _action_by,
                        'action', 'complete_upgrade_to_workorder'
                    );

                    PERFORM log_activity(
                        'upgrade_asset_requisition.sent_to_workorder',
                        'Upgrade requisition completed and sent to work order: ' || COALESCE(v_requisition_number, 'N/A'),
                        'asset_requisition_actions',
                        v_inserted_action_id,
                        'user',
                        _action_by,
                        v_log_data,
                        _tenant_id
                    );
                    v_log_success := TRUE;
                EXCEPTION WHEN OTHERS THEN
                    v_log_success := FALSE;
                    v_error_message := 'Activity logging failed: ' || SQLERRM;
                END;

                -- ═══════════════════════════════════════════════════════════════
                -- STEP 12: Return Success
                -- ═══════════════════════════════════════════════════════════════

                RETURN QUERY SELECT
                    'SUCCESS'::TEXT AS status,
                    'Upgrade requisition successfully sent to work order and all statuses updated'::TEXT AS message,
                    v_result_json AS result_data;

            END;
            $fn$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'complete_upgrade_to_workorder'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
        SQL);
    }
};
