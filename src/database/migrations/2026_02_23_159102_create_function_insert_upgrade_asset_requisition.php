<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
        DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'insert_upgrade_asset_requisition'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION insert_upgrade_asset_requisition(
                IN _asset_id BIGINT,
                IN _reason TEXT,
                IN _other_reason TEXT,
                IN _justification TEXT,
                IN _upgrade_description TEXT,
                IN _expected_outcomes JSONB,
                IN _expected_outcome_benefits TEXT,
                IN _priority BIGINT,
                IN _expected_date DATE,
                IN _error_logs_performance_doc JSONB,
                IN _screenshots JSONB,
                IN _tenant_id BIGINT,
                IN _created_by BIGINT,
                IN _work_order_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                requisition_data JSON,
                maintenance_leaders JSON
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_seq_val INTEGER;
                v_requisition_number VARCHAR;
                v_current_year INTEGER;
                v_inserted_id BIGINT;
                v_asset_group_id BIGINT;
                v_requisition_json JSON;
                v_asset_json JSON;
                v_requester_json JSON;
                v_priority_json JSON;
                v_leaders_json JSON;
                v_leader_ids JSONB;
                v_log_data JSONB;
                v_log_success BOOLEAN := FALSE;
                v_error_message TEXT;
            BEGIN

                -- ─── Input Validation ───
                IF _asset_id IS NULL THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT AS status,
                        'Asset ID is required'::TEXT AS message,
                        NULL::JSON AS requisition_data,
                        NULL::JSON AS maintenance_leaders;
                    RETURN;
                END IF;

                IF _upgrade_description IS NULL OR _upgrade_description = '' THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT AS status,
                        'Upgrade description is required'::TEXT AS message,
                        NULL::JSON AS requisition_data,
                        NULL::JSON AS maintenance_leaders;
                    RETURN;
                END IF;

                IF _expected_date IS NULL THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT AS status,
                        'Expected date is required'::TEXT AS message,
                        NULL::JSON AS requisition_data,
                        NULL::JSON AS maintenance_leaders;
                    RETURN;
                END IF;

                IF _tenant_id IS NULL THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT AS status,
                        'Tenant ID is required'::TEXT AS message,
                        NULL::JSON AS requisition_data,
                        NULL::JSON AS maintenance_leaders;
                    RETURN;
                END IF;

                IF _created_by IS NULL THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT AS status,
                        'Created by user ID is required'::TEXT AS message,
                        NULL::JSON AS requisition_data,
                        NULL::JSON AS maintenance_leaders;
                    RETURN;
                END IF;

                -- ─── Generate Requisition Number ───
                v_current_year := EXTRACT(YEAR FROM CURRENT_DATE)::INTEGER;
                v_seq_val := nextval('upgrade_asset_requisition_id_seq');
                v_requisition_number := 'UPG-AST-' || v_current_year || '-' || LPAD(v_seq_val::TEXT, 4, '0');

                -- ─── Insert the Requisition ───
                INSERT INTO upgrade_asset_requisitions (
                    asset_id,
                    reason,
                    other_reason,
                    justification,
                    upgrade_description,
                    expected_outcomes,
                    expected_outcome_benefits,
                    priority,
                    expected_date,
                    error_logs_performance_doc,
                    screenshots,
                    status,
                    work_order_id,
                    upgrade_requisition_number,
                    isactive,
                    tenant_id,
                    created_by,
                    created_at,
                    updated_at
                )
                VALUES (
                    _asset_id,
                    _reason,
                    _other_reason,
                    _justification,
                    _upgrade_description,
                    _expected_outcomes,
                    _expected_outcome_benefits,
                    _priority,
                    _expected_date,
                    _error_logs_performance_doc,
                    _screenshots,
                    'PENDING',
                    _work_order_id,
                    v_requisition_number,
                    TRUE,
                    _tenant_id,
                    _created_by,
                    NOW(),
                    NOW()
                )
                RETURNING id INTO v_inserted_id;

                -- ─── Get Asset Details ───
                SELECT json_build_object(
                    'id', ai.id,
                    'asset_id', ai.asset_id,
                    'model_number', ai.model_number,
                    'serial_number', ai.serial_number,
                    'thumbnail_image', ai.thumbnail_image,
                    'asset_group_id', a.id,
                    'asset_name', a.name,
                    'category_id', a.category,
                    'sub_category_id', a.sub_category
                )
                INTO v_asset_json
                FROM asset_items ai
                LEFT JOIN assets a ON ai.asset_id = a.id
                WHERE ai.id = _asset_id
                AND ai.tenant_id = _tenant_id;

                -- Get asset_group_id for maintenance leader lookup
                SELECT a.id INTO v_asset_group_id
                FROM asset_items ai
                LEFT JOIN assets a ON ai.asset_id = a.id
                WHERE ai.id = _asset_id
                AND ai.tenant_id = _tenant_id;

                -- ─── Get Requester Details ───
                SELECT json_build_object(
                    'id', u.id,
                    'user_name', u.user_name,
                    'email', u.email
                )
                INTO v_requester_json
                FROM users u
                WHERE u.id = _created_by;

                -- ─── Get Priority Details ───
                IF _priority IS NOT NULL THEN
                    SELECT json_build_object(
                        'id', wpl.id,
                        'name', wpl.name
                    )
                    INTO v_priority_json
                    FROM work_order_priority_levels wpl
                    WHERE wpl.id = _priority;
                ELSE
                    v_priority_json := NULL;
                END IF;

                -- ─── Get Maintenance Leaders (CRITICAL for notifications) ───
                -- Flow: asset_items → assets → maintenance_team_related_asset_groups → maintenance_team_members (is_team_leader) → users
                SELECT json_agg(
                    json_build_object(
                        'id', u.id,
                        'user_name', u.user_name,
                        'email', u.email
                    )
                )
                INTO v_leaders_json
                FROM (
                    SELECT DISTINCT u.id, u.user_name, u.email
                    FROM maintenance_team_related_asset_groups mtrag
                    INNER JOIN maintenance_team_members mtm
                        ON mtm.team_id = mtrag.team_id
                        AND mtm.isactive = TRUE
                        AND mtm.is_team_leader = TRUE
                        AND mtm.deleted_at IS NULL
                    INNER JOIN users u
                        ON u.id = mtm.user_id
                    WHERE mtrag.asset_group_id = v_asset_group_id
                    AND mtrag.isactive = TRUE
                    AND mtrag.deleted_at IS NULL
                    AND mtrag.tenant_id = _tenant_id
                ) u;

                -- Collect leader user IDs for storing in the requisition record
                SELECT COALESCE(jsonb_agg(leader_id), '[]'::JSONB)
                INTO v_leader_ids
                FROM (
                    SELECT DISTINCT mtm.user_id AS leader_id
                    FROM maintenance_team_related_asset_groups mtrag
                    INNER JOIN maintenance_team_members mtm
                        ON mtm.team_id = mtrag.team_id
                        AND mtm.isactive = TRUE
                        AND mtm.is_team_leader = TRUE
                        AND mtm.deleted_at IS NULL
                    WHERE mtrag.asset_group_id = v_asset_group_id
                    AND mtrag.isactive = TRUE
                    AND mtrag.deleted_at IS NULL
                    AND mtrag.tenant_id = _tenant_id
                ) sub;

                -- Update the requisition with notified_maintenance_leaders
                UPDATE upgrade_asset_requisitions
                SET notified_maintenance_leaders = v_leader_ids
                WHERE id = v_inserted_id;

                -- ─── Build the Requisition Result ───
                v_requisition_json := json_build_object(
                    'id', v_inserted_id,
                    'upgrade_requisition_number', v_requisition_number,
                    'status', 'PENDING',
                    'expected_date', _expected_date,
                    'reason', _reason,
                    'other_reason', _other_reason,
                    'justification', _justification,
                    'upgrade_description', _upgrade_description,
                    'expected_outcomes', _expected_outcomes,
                    'expected_outcome_benefits', _expected_outcome_benefits,
                    'error_logs_performance_doc', _error_logs_performance_doc,
                    'screenshots', _screenshots,
                    'work_order_id', _work_order_id,
                    'asset', v_asset_json,
                    'requester', v_requester_json,
                    'priority', v_priority_json,
                    'created_at', NOW()
                );

                -- ─── Activity Log ───
                BEGIN
                    v_log_data := jsonb_build_object(
                        'requisition_id', v_inserted_id,
                        'requisition_number', v_requisition_number,
                        'asset_id', _asset_id,
                        'created_by', _created_by,
                        'action', 'create'
                    );

                    PERFORM log_activity(
                        'upgrade_asset_requisition.created',
                        'Upgrade asset requisition created: ' || v_requisition_number,
                        'upgrade_asset_requisitions',
                        v_inserted_id,
                        'user',
                        _created_by,
                        v_log_data,
                        _tenant_id
                    );
                    v_log_success := TRUE;
                EXCEPTION WHEN OTHERS THEN
                    v_log_success := FALSE;
                    v_error_message := 'Activity logging failed: ' || SQLERRM;
                END;

                -- ─── Return Success ───
                RETURN QUERY SELECT
                    'SUCCESS'::TEXT AS status,
                    'Upgrade asset requisition created successfully'::TEXT AS message,
                    v_requisition_json AS requisition_data,
                    COALESCE(v_leaders_json, '[]'::JSON) AS maintenance_leaders;

            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(<<<SQL
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'insert_upgrade_asset_requisition'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
        SQL);
    }
};
