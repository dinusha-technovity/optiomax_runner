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
                    WHERE proname = 'confirm_replace_recommendation'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

        CREATE OR REPLACE FUNCTION confirm_replace_recommendation(
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
            v_decision_status           VARCHAR;
            v_log_type_id               BIGINT;
            v_recommend_data            RECORD;
            v_new_requisition_id        BIGINT;
            v_new_replace_req_id        BIGINT;
            v_requisition_number        VARCHAR;
            v_new_requisition_number    VARCHAR;
            v_result_json               JSON;
            v_requisition_type_id       BIGINT;
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

            -- ═══ Validate decision is REPLACE_SUGGESTED ═══
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

            IF v_decision_status <> 'REPLACE_SUGGESTED' THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, ('Cannot confirm: current status is ' || v_decision_status || '. Must be REPLACE_SUGGESTED.')::TEXT, NULL::JSON;
                RETURN;
            END IF;

            -- ═══ Get recommendation data ═══
            SELECT *
            INTO v_recommend_data
            FROM replace_requisition_recommend_data
            WHERE decision_id = _decision_id
              AND tenant_id = _tenant_id
              AND is_active = TRUE
              AND deleted_at IS NULL
            ORDER BY id DESC
            LIMIT 1;

            IF v_recommend_data IS NULL THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'No recommendation data found for this decision'::TEXT, NULL::JSON;
                RETURN;
            END IF;

            -- ═══ Get current requisition number ═══
            SELECT ar.requisition_id
            INTO v_requisition_number
            FROM asset_requisitions ar
            WHERE ar.id = _asset_requisition_id;

            -- ═══ Get log type ID ═══
            SELECT id INTO v_log_type_id
            FROM asset_requisition_log_types
            WHERE code = 'ASSET_OWNER_ACCEPTED_REPLACEMENT'
              AND is_active = TRUE
            LIMIT 1;

            -- ═══ Get replacement requisition type ID ═══
            SELECT id INTO v_requisition_type_id
            FROM asset_requisition_types
            WHERE code = 'REPLACE'
              AND is_active = TRUE
            LIMIT 1;

            IF v_requisition_type_id IS NULL THEN
                v_requisition_type_id := 3; -- Default to 3 for replace type
            END IF;

            -- ═══ Step 1: Insert log entry ═══
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
                    jsonb_build_object(
                        'decision_id', _decision_id,
                        'recommend_data_id', v_recommend_data.id
                    ),
                    NOW(),
                    NOW()
                );
            END IF;

            -- ═══ Step 2: Update upgrade requisition status ═══
            UPDATE asset_requisitions
            SET requisition_status = 'ASSET_OWNER_ACCEPTED_REPLACEMENT',
                updated_at = NOW()
            WHERE id = _asset_requisition_id;

            -- ═══ Step 3: Update decision status ═══
            UPDATE asset_requisition_decision
            SET status = 'ASSET_OWNER_ACCEPTED_REPLACEMENT',
                updated_at = NOW()
            WHERE id = _decision_id;

            UPDATE upgrade_asset_requisitions
            SET status = 'ASSET_OWNER_ACCEPTED_REPLACEMENT',
                updated_at = NOW()
            WHERE asset_requisition_id = _asset_requisition_id;

            -- ═══ Build result JSON ═══
            v_result_json := json_build_object(
                'decision_id', _decision_id,
                'asset_requisition_id', _asset_requisition_id,
                'new_status', 'ASSET_OWNER_ACCEPTED_REPLACEMENT',
                'requisition_number', v_requisition_number,
                'recommend_data_id', v_recommend_data.id
            );

            RETURN QUERY SELECT
                'SUCCESS'::TEXT,
                'Replacement recommendation confirmed successfully'::TEXT,
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
                    WHERE proname = 'confirm_replace_recommendation'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
        SQL);
    }
};
