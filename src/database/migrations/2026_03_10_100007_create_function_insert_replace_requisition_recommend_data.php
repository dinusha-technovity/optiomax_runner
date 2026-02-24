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
                    WHERE proname = 'insert_replace_requisition_recommend_data'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

        CREATE OR REPLACE FUNCTION insert_replace_requisition_recommend_data(
            IN _tenant_id                  BIGINT,
            IN _asset_requisition_id       BIGINT,
            IN _decision_id                BIGINT,
            IN _asset_id                   BIGINT,
            IN _recommendation_reason      TEXT,
            IN _priority_id                BIGINT    DEFAULT NULL,
            IN _estimated_cost             DECIMAL   DEFAULT NULL,
            IN _suppliers                  JSONB     DEFAULT NULL,
            IN _specifications             JSONB     DEFAULT NULL,
            IN _is_disposal_recommended    BOOLEAN   DEFAULT FALSE,
            IN _disposal_recommendation_id BIGINT    DEFAULT NULL,
            IN _mode_of_acquisition_id     BIGINT    DEFAULT NULL,
            IN _recommended_by             BIGINT    DEFAULT NULL,
            IN _recomend_user_type_id      BIGINT    DEFAULT NULL
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
            v_inserted_id               BIGINT;
            v_inserted_action_id        BIGINT;
            v_asset_recommendation_id   BIGINT;
            v_recommendation_type_id    BIGINT;
            v_owner_email               VARCHAR;
            v_owner_name                VARCHAR;
            v_requisition_number        VARCHAR;
            v_priority_name             VARCHAR;
            v_mode_name                 VARCHAR;
            v_result_json               JSON;
        BEGIN

            -- ═══ Input Validation ═══
            IF _decision_id IS NULL THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Decision ID is required'::TEXT, NULL::JSON;
                RETURN;
            END IF;

            IF _recommendation_reason IS NULL OR TRIM(_recommendation_reason) = '' THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Recommendation reason is required'::TEXT, NULL::JSON;
                RETURN;
            END IF;

            IF _recommended_by IS NULL THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Recommended by user ID is required'::TEXT, NULL::JSON;
                RETURN;
            END IF;

            IF _recomend_user_type_id IS NULL THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Recommend user type ID is required'::TEXT, NULL::JSON;
                RETURN;
            END IF;

            -- ═══ Validate decision is PENDING ═══
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

            IF v_decision_status <> 'PENDING' THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, ('Cannot recommend replacement: current status is ' || v_decision_status)::TEXT, NULL::JSON;
                RETURN;
            END IF;

            -- ═══ Get requisition number and owner info ═══
            SELECT ar.requisition_id, u.email, u.name
            INTO v_requisition_number, v_owner_email, v_owner_name
            FROM asset_requisitions ar
            JOIN users u ON u.id = ar.requisition_by
            WHERE ar.id = _asset_requisition_id;

            -- ═══ Get priority name ═══
            IF _priority_id IS NOT NULL THEN
                SELECT name INTO v_priority_name FROM work_order_priority_levels WHERE id = _priority_id;
            END IF;

            -- ═══ Get mode of acquisition name ═══
            IF _mode_of_acquisition_id IS NOT NULL THEN
                SELECT name INTO v_mode_name FROM asset_requisition_availability_types WHERE id = _mode_of_acquisition_id;
            END IF;

            -- ═══ Get log type ID ═══
            SELECT id INTO v_log_type_id
            FROM asset_requisition_log_types
            WHERE code = 'ASSET_REQUISITION_RECOMMENDED_FOR_REPLACE'
              AND is_active = TRUE
            LIMIT 1;

            -- ═══ Get recommendation type ID for REPLACE ═══
            SELECT id INTO v_recommendation_type_id
            FROM asset_recommendation_types
            WHERE code = 'REPLACE_ASSET_REQUISITION'
              AND is_active = TRUE
            LIMIT 1;

            IF v_recommendation_type_id IS NULL THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Recommendation type REPLACE_ASSET_REQUISITION not found in asset_recommendation_types'::TEXT, NULL::JSON;
                RETURN;
            END IF;

            -- ═══ Step 1: Insert recommend data ═══
            INSERT INTO replace_requisition_recommend_data (
                tenant_id,
                asset_requisition_id,
                decision_id,
                asset_id,
                recommendation_reason,
                priority_id,
                estimated_cost,
                suppliers,
                specifications,
                is_disposal_recommended,
                disposal_recommendation_id,
                mode_of_acquisition_id,
                recommended_by,
                recomend_user_type_id,
                is_active,
                created_at,
                updated_at
            )
            VALUES (
                _tenant_id,
                _asset_requisition_id,
                _decision_id,
                _asset_id,
                _recommendation_reason,
                _priority_id,
                _estimated_cost,
                _suppliers,
                _specifications,
                COALESCE(_is_disposal_recommended, FALSE),
                _disposal_recommendation_id,
                _mode_of_acquisition_id,
                _recommended_by,
                _recomend_user_type_id,
                TRUE,
                NOW(),
                NOW()
            )
            RETURNING id INTO v_inserted_id;

            -- ═══ Step 2: Insert into asset_recommendations ═══
            INSERT INTO asset_recommendations (
                tenant_id,
                asset_id,
                recommendation_type_id,
                recommend_user_type_id,
                recommended_by_user_id,
                message,
                recommendation_date,
                is_active,
                created_at,
                updated_at
            )
            VALUES (
                _tenant_id,
                _asset_id,
                v_recommendation_type_id,
                _recomend_user_type_id,
                _recommended_by,
                _recommendation_reason,
                NOW()::DATE,
                TRUE,
                NOW(),
                NOW()
            )
            RETURNING id INTO v_asset_recommendation_id;

            -- ═══ Step 3: Insert action record ═══
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
                _recommended_by,
                'REPLACE_SUGGESTED',
                _recommendation_reason,
                TRUE,
                _tenant_id,
                NOW(),
                NOW()
            FROM asset_requisition_decision ard
            JOIN upgrade_asset_requisitions uar ON uar.id = ard.asset_requisition_data_id
            JOIN asset_requisitions ar ON ar.id = uar.asset_requisition_id
            WHERE ard.id = _decision_id
            RETURNING id INTO v_inserted_action_id;

            -- ═══ Step 4: Insert log entry ═══
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
                    _recommended_by,
                    jsonb_build_object(
                        'recommend_data_id', v_inserted_id,
                        'action_id', v_inserted_action_id,
                        'recommendation_reason', _recommendation_reason
                    ),
                    NOW(),
                    NOW()
                );
            END IF;

            -- ═══ Step 5: Update statuses ═══
            UPDATE asset_requisitions
            SET requisition_status = 'REPLACE_SUGGESTED',
                updated_at = NOW()
            WHERE id = _asset_requisition_id;

            UPDATE asset_requisition_decision
            SET status = 'REPLACE_SUGGESTED',
                updated_at = NOW()
            WHERE id = _decision_id;

            UPDATE upgrade_asset_requisitions
            SET status = 'REPLACE_SUGGESTED',
                is_recommend_for_transition = TRUE,
                updated_at = NOW()
            WHERE asset_requisition_id = _asset_requisition_id;

            -- ═══ Build result JSON ═══
            v_result_json := json_build_object(
                'recommend_data_id', v_inserted_id,
                'asset_recommendation_id', v_asset_recommendation_id,
                'action_id', v_inserted_action_id,
                'decision_id', _decision_id,
                'asset_requisition_id', _asset_requisition_id,
                'new_status', 'REPLACE_SUGGESTED',
                'owner_email', v_owner_email,
                'owner_name', v_owner_name,
                'requisition_number', v_requisition_number,
                'recommendation_reason', _recommendation_reason,
                'priority_name', v_priority_name,
                'mode_of_acquisition_name', v_mode_name,
                'specifications', _specifications
            );

            RETURN QUERY SELECT
                'SUCCESS'::TEXT,
                'Replacement recommendation submitted successfully'::TEXT,
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
                    WHERE proname = 'insert_replace_requisition_recommend_data'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
        SQL);
    }
};
