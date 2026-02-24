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
                    WHERE proname = 'approve_upgrade_asset_requisition'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION approve_upgrade_asset_requisition(
                IN _decision_id                    BIGINT,
                IN _asset_requisition_id           BIGINT,
                IN _asset_requisition_data_id      BIGINT,
                IN _asset_id                       BIGINT,
                IN _justification                  TEXT     DEFAULT NULL,
                IN _action_by                      BIGINT   DEFAULT NULL,
                IN _tenant_id                      BIGINT   DEFAULT NULL
            )
            RETURNS TABLE (
                status            TEXT,
                message           TEXT,
                approval_data     JSON
            )
            LANGUAGE plpgsql
            AS $fn$
            DECLARE
                v_decision_status       VARCHAR;
                v_requisition_status    VARCHAR;
                v_asset_group_id        BIGINT;
                v_is_authorized         BOOLEAN := FALSE;
                v_inserted_action_id    BIGINT;
                v_inserted_ticket_id    BIGINT;
                v_log_type_id           BIGINT;
                v_approval_json         JSON;
                v_log_data              JSONB;
                v_log_success           BOOLEAN := FALSE;
                v_error_message         TEXT;
                v_requisition_number    VARCHAR;
                v_requisition_asset_id  BIGINT;
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

                IF _asset_id IS NULL THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT,
                        'Asset ID is required'::TEXT,
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
                -- STEP 3: Fetch upgrade requisition details & verify
                -- ═══════════════════════════════════════════════════════════════

                SELECT ureq.status, ureq.asset_id, ar.requisition_id
                INTO v_requisition_status, v_requisition_asset_id, v_requisition_number
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
                -- STEP 4: Access Control - verify user is maintenance team leader
                -- ═══════════════════════════════════════════════════════════════

                SELECT a.id INTO v_asset_group_id
                FROM asset_items ai
                INNER JOIN assets a ON a.id = ai.asset_id
                WHERE ai.id = v_requisition_asset_id;

                SELECT EXISTS(
                    SELECT 1
                    FROM maintenance_team_members mtm
                    INNER JOIN maintenance_team_related_asset_groups mtrag
                        ON mtrag.team_id = mtm.team_id
                        AND mtrag.isactive = TRUE
                        AND mtrag.deleted_at IS NULL
                        AND mtrag.tenant_id = _tenant_id
                    WHERE mtm.user_id = _action_by
                      AND mtm.is_team_leader = TRUE
                      AND mtm.isactive = TRUE
                      AND mtm.deleted_at IS NULL
                      AND mtrag.asset_group_id = v_asset_group_id
                ) INTO v_is_authorized;

                IF NOT v_is_authorized THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT,
                        'Access denied. You are not authorized to approve this requisition.'::TEXT,
                        NULL::JSON;
                    RETURN;
                END IF;

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
                    'Upgrade_asset_requisition_approved',
                    _justification,
                    NULL,
                    TRUE,
                    NOW(),
                    NOW()
                )
                RETURNING id INTO v_inserted_action_id;

                -- ═══════════════════════════════════════════════════════════════
                -- STEP 6: Insert into work_order_tickets
                -- ═══════════════════════════════════════════════════════════════

                INSERT INTO work_order_tickets (
                    asset_id,
                    reference_id,
                    type,
                    is_get_action,
                    is_closed,
                    isactive,
                    tenant_id,
                    created_at,
                    updated_at
                )
                VALUES (
                    _asset_id,
                    _asset_requisition_data_id,
                    'asset_upgrade_request',
                    FALSE,
                    FALSE,
                    TRUE,
                    _tenant_id,
                    NOW(),
                    NOW()
                )
                RETURNING id INTO v_inserted_ticket_id;

                -- ═══════════════════════════════════════════════════════════════
                -- STEP 7: Update decision status to SENT_TO_WORK_ORDER
                -- ═══════════════════════════════════════════════════════════════

                UPDATE asset_requisition_decision
                SET status = 'SENT_TO_WORK_ORDER',
                    is_get_action = TRUE,
                    updated_at = NOW()
                WHERE id = _decision_id;

                -- Also update the upgrade_asset_requisitions status
                UPDATE upgrade_asset_requisitions
                SET status = 'SENT_TO_WORK_ORDER',
                    updated_at = NOW()
                WHERE id = _asset_requisition_data_id;


                -- ═══════════════════════════════════════════════════════════════
                -- STEP 8: Update status in asset_requisitions and upgrade_asset_requisitions
                -- ═══════════════════════════════════════════════════════════════
    
                UPDATE asset_requisitions
                SET requisition_status = 'SENT_TO_WORK_ORDER',
                    updated_at = NOW()
                WHERE id = _asset_requisition_id;

                UPDATE upgrade_asset_requisitions
                SET status = 'SENT_TO_WORK_ORDER',
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
                            'ticket_id', v_inserted_ticket_id,
                            'asset_id', _asset_id,
                            'asset_requisition_data_id', _asset_requisition_data_id,
                            'action_type', 'Upgrade_asset_requisition_approved',
                            'justification', _justification
                        ),
                        TRUE,
                        NOW(),
                        NOW()
                    );
                END IF;

                -- ═══════════════════════════════════════════════════════════════
                -- STEP 10: Build response JSON
                -- ═══════════════════════════════════════════════════════════════

                v_approval_json := json_build_object(
                    'action_id', v_inserted_action_id,
                    'ticket_id', v_inserted_ticket_id,
                    'decision_id', _decision_id,
                    'asset_requisition_id', _asset_requisition_id,
                    'asset_requisition_data_id', _asset_requisition_data_id,
                    'asset_id', _asset_id,
                    'action_type', 'Upgrade_asset_requisition_approved',
                    'new_status', 'SENT_TO_WORK_ORDER',
                    'requisition_number', v_requisition_number,
                    'created_at', NOW()
                );

                -- ═══════════════════════════════════════════════════════════════
                -- STEP 11: Activity Log (system log)
                -- ═══════════════════════════════════════════════════════════════

                BEGIN
                    v_log_data := jsonb_build_object(
                        'action_id', v_inserted_action_id,
                        'ticket_id', v_inserted_ticket_id,
                        'decision_id', _decision_id,
                        'asset_requisition_id', _asset_requisition_id,
                        'asset_requisition_data_id', _asset_requisition_data_id,
                        'asset_id', _asset_id,
                        'action_by', _action_by,
                        'action', 'approve_upgrade_request'
                    );

                    PERFORM log_activity(
                        'upgrade_asset_requisition.approved',
                        'Upgrade asset requisition approved and submitted to work order: ' || COALESCE(v_requisition_number, 'N/A'),
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
                    'Upgrade asset requisition approved and work order ticket created successfully'::TEXT AS message,
                    v_approval_json AS approval_data;

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
                    WHERE proname = 'approve_upgrade_asset_requisition'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
        SQL);
    }
};
