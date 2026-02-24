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
                    WHERE proname = 'resume_upgrade_decision'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

        CREATE OR REPLACE FUNCTION resume_upgrade_decision(
            IN _tenant_id              BIGINT,
            IN _action_by              BIGINT,
            IN _decision_id            BIGINT,
            IN _asset_requisition_id   BIGINT
        )
        RETURNS TABLE (
            status      TEXT,
            message     TEXT,
            result_data JSON
        )
        LANGUAGE plpgsql
        AS $fn$
        DECLARE
            v_decision_status       VARCHAR;
            v_log_type_id           BIGINT;
            v_inserted_action_id    BIGINT;
            v_requisition_number    VARCHAR;
            v_result_json           JSON;
        BEGIN

            -- ═══ Input Validation ═══
            IF _decision_id IS NULL THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Decision ID is required'::TEXT, NULL::JSON;
                RETURN;
            END IF;

            IF _action_by IS NULL THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Action by user ID is required'::TEXT, NULL::JSON;
                RETURN;
            END IF;

            -- ═══ Validate decision exists and is ON_HOLD ═══
            SELECT ard.status
            INTO v_decision_status
            FROM asset_requisition_decision ard
            WHERE ard.id = _decision_id
              AND ard.tenant_id = _tenant_id
              AND ard.deleted_at IS NULL;

            IF v_decision_status IS NULL THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Decision not found or does not belong to this tenant'::TEXT, NULL::JSON;
                RETURN;
            END IF;

            IF v_decision_status <> 'ON_HOLD' THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, ('Cannot resume: current status is ' || v_decision_status || '. Must be ON_HOLD.')::TEXT, NULL::JSON;
                RETURN;
            END IF;

            -- ═══ Get requisition number ═══
            SELECT ar.requisition_id
            INTO v_requisition_number
            FROM asset_requisitions ar
            WHERE ar.id = _asset_requisition_id;

            -- ═══ Get log type ID ═══
            SELECT id INTO v_log_type_id
            FROM asset_requisition_log_types
            WHERE code = 'ASSET_REQUISITION_RESUMED'
              AND is_active = TRUE
            LIMIT 1;

            -- ═══ Step 1: Insert action record ═══
            INSERT INTO asset_requisition_actions (
                asset_requisition_id,
                action_by,
                action_type,
                reason,
                is_active,
                tenant_id,
                created_at,
                updated_at
            )
            SELECT
                ar.id,
                _action_by,
                'RESUMED',
                'Requisition resumed from on-hold status',
                TRUE,
                _tenant_id,
                NOW(),
                NOW()
            FROM asset_requisition_decision ard
            JOIN upgrade_asset_requisitions uar ON uar.id = ard.asset_requisition_data_id
            JOIN asset_requisitions ar ON ar.id = uar.asset_requisition_id
            WHERE ard.id = _decision_id
            RETURNING id INTO v_inserted_action_id;

            -- ═══ Step 2: Insert log entry ═══
            IF v_log_type_id IS NOT NULL THEN
                INSERT INTO asset_requisition_logs (
                    tenant_id,
                    asset_requisition_id,
                    log_type_id,
                    action_by,
                    payload,
                    created_at,
                    updated_at
                )
                VALUES (
                    _tenant_id,
                    _asset_requisition_id,
                    v_log_type_id,
                    _action_by,
                    jsonb_build_object('action_id', v_inserted_action_id),
                    NOW(),
                    NOW()
                );
            END IF;

            -- ═══ Step 3: Update asset requisition status ═══
            UPDATE asset_requisitions
            SET requisition_status = 'PENDING',
                updated_at = NOW()
            WHERE id = _asset_requisition_id;

            -- ═══ Step 4: Update decision status ═══
            UPDATE asset_requisition_decision
            SET status = 'PENDING',
                updated_at = NOW()
            WHERE id = _decision_id;

            -- ═══ Update upgrade_asset_requisitions status ═══
            UPDATE upgrade_asset_requisitions
            SET status = 'PENDING',
                updated_at = NOW()
            WHERE asset_requisition_id = _asset_requisition_id;

            -- ═══ Build result JSON ═══
            v_result_json := json_build_object(
                'action_id', v_inserted_action_id,
                'decision_id', _decision_id,
                'asset_requisition_id', _asset_requisition_id,
                'new_status', 'PENDING',
                'requisition_number', v_requisition_number
            );

            RETURN QUERY SELECT
                'SUCCESS'::TEXT,
                'Requisition resumed successfully'::TEXT,
                v_result_json;

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
                    WHERE proname = 'resume_upgrade_decision'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
        SQL);
    }
};
