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
                    WHERE proname = 'insert_replace_asset_requisition'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION insert_replace_asset_requisition(
                IN _asset_id BIGINT,
                IN _reason TEXT,
                IN _other_reason TEXT,
                IN _justification TEXT,
                IN _replacement_description TEXT,
                IN _expected_outcomes JSONB,
                IN _expected_outcome_benefits TEXT,
                IN _priority BIGINT,
                IN _expected_date DATE,
                IN _mode_of_acquisition BIGINT,
                IN _expected_condition BIGINT,
                IN _suppliers JSONB,
                IN _set_asset_to_disposal BOOLEAN,
                IN _error_logs_performance_doc JSONB,
                IN _screenshots JSONB,
                IN _tenant_id BIGINT,
                IN _created_by BIGINT,
                IN _work_order_id BIGINT DEFAULT NULL,
                IN _other_docs JSONB DEFAULT NULL
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
                v_current_year INTEGER;
                v_inserted_id BIGINT;
                v_asset_requisition_id BIGINT;
                v_asset_requisition_reg_no VARCHAR;
                v_decision_id BIGINT;
                v_log_type_id BIGINT;
                v_asset_group_id BIGINT;
                v_requisition_json JSON;
                v_asset_json JSON;
                v_requester_json JSON;
                v_priority_json JSON;
                v_leaders_json JSON;
                v_leader_ids JSONB;
                v_reasons_json JSON;
                v_outcomes_json JSON;
                v_suppliers_json JSON;
                v_mode_of_acquisition_json JSON;
                v_expected_condition_json JSON;
                v_log_data JSONB;
                v_log_success BOOLEAN := FALSE;
                v_error_message TEXT;
                v_ar_seq_val INTEGER;
                v_replace_seq_val INTEGER;
                v_requisition_status VARCHAR;
                v_replace_status VARCHAR;
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

                IF _expected_date IS NULL THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT AS status,
                        'Expected date is required'::TEXT AS message,
                        NULL::JSON AS requisition_data,
                        NULL::JSON AS maintenance_leaders;
                    RETURN;
                END IF;

                IF _mode_of_acquisition IS NULL THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT AS status,
                        'Mode of acquisition is required'::TEXT AS message,
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

                -- ─── Set Status ───
                v_requisition_status := 'PENDING';
                v_replace_status := 'PENDING';

                -- ─── Generate Requisition Numbers ───
                v_current_year := EXTRACT(YEAR FROM CURRENT_DATE)::INTEGER;

                -- Generate asset requisition register number
                v_ar_seq_val := nextval('asset_requisition_register_id_seq');
                v_asset_requisition_reg_no := 'AST-REQ-' || v_current_year || '-' || LPAD(v_ar_seq_val::TEXT, 4, '0');

                -- ═══════════════════════════════════════════════════════════════
                -- STEP 1: Insert into asset_requisitions table
                -- ═══════════════════════════════════════════════════════════════
                INSERT INTO asset_requisitions (
                    requisition_id,
                    requisition_by,
                    requisition_date,
                    requisition_status,
                    isactive,
                    tenant_id,
                    asset_requisition_type_id,
                    is_transitioned,
                    created_at,
                    updated_at
                )
                VALUES (
                    v_asset_requisition_reg_no,
                    _created_by,
                    NOW(),
                    v_requisition_status,
                    TRUE,
                    _tenant_id,
                    3,  -- asset_requisition_type_id = 3 (Replace)
                    FALSE,
                    NOW(),
                    NOW()
                )
                RETURNING id INTO v_asset_requisition_id;

                -- ═══════════════════════════════════════════════════════════════
                -- STEP 2: Insert into replace_asset_requisitions table
                -- ═══════════════════════════════════════════════════════════════
                INSERT INTO replace_asset_requisitions (
                    tenant_id,
                    asset_requisition_id,
                    asset_id,
                    priority,
                    mode_of_acquisition,
                    expected_condition,
                    expected_date,
                    replacement_description,
                    other_reason,
                    justification,
                    error_logs_performance_doc,
                    screenshots,
                    other_docs,
                    work_order_id,
                    status,
                    is_came_from_upgrade_req,
                    is_disposal_recommended,
                    is_active,
                    created_by,
                    created_at,
                    updated_at
                )
                VALUES (
                    _tenant_id,
                    v_asset_requisition_id,
                    _asset_id,
                    _priority,
                    _mode_of_acquisition,
                    _expected_condition,
                    _expected_date,
                    COALESCE(_replacement_description, ''),
                    _other_reason,
                    _justification,
                    _error_logs_performance_doc,
                    _screenshots,
                    _other_docs,
                    _work_order_id,
                    v_replace_status,
                    FALSE,
                    COALESCE(_set_asset_to_disposal, FALSE),
                    TRUE,
                    _created_by,
                    NOW(),
                    NOW()
                )
                RETURNING id INTO v_inserted_id;

                -- ═══════════════════════════════════════════════════════════════
                -- STEP 2.1: Insert into asset_requisition_reasons_pivot
                -- ═══════════════════════════════════════════════════════════════
                IF _reason IS NOT NULL AND _reason <> '' AND _reason <> '[]' THEN
                    INSERT INTO asset_requisition_reasons_pivot (
                        tenant_id,
                        asset_requisition_id,
                        asset_requisition_type_id,
                        asset_requisition_data_id,
                        reason_id,
                        created_at,
                        updated_at
                    )
                    SELECT
                        _tenant_id,
                        v_asset_requisition_id,
                        3,  -- asset_requisition_type_id = 3 (Replace)
                        v_inserted_id,
                        elem::BIGINT,
                        NOW(),
                        NOW()
                    FROM json_array_elements_text(_reason::JSON) AS elem
                    WHERE EXISTS (
                        SELECT 1 FROM asset_upgrade_replace_reasons
                        WHERE id = elem::BIGINT AND is_active = TRUE
                    );
                END IF;

                -- ═══════════════════════════════════════════════════════════════
                -- STEP 2.2: Insert into asset_requisition_outcomes_pivot
                -- ═══════════════════════════════════════════════════════════════
                IF _expected_outcomes IS NOT NULL AND _expected_outcomes::TEXT <> '[]' AND _expected_outcomes::TEXT <> 'null' THEN
                    INSERT INTO asset_requisition_outcomes_pivot (
                        tenant_id,
                        asset_requisition_id,
                        asset_requisition_type_id,
                        asset_requisition_data_id,
                        outcome_id,
                        created_at,
                        updated_at
                    )
                    SELECT
                        _tenant_id,
                        v_asset_requisition_id,
                        3,  -- asset_requisition_type_id = 3 (Replace)
                        v_inserted_id,
                        (elem::TEXT)::BIGINT,
                        NOW(),
                        NOW()
                    FROM jsonb_array_elements(_expected_outcomes) AS elem
                    WHERE EXISTS (
                        SELECT 1 FROM asset_upgrade_replace_outcomes
                        WHERE id = (elem::TEXT)::BIGINT AND is_active = TRUE
                    );
                END IF;

                -- ═══════════════════════════════════════════════════════════════
                -- STEP 2.3: Insert into asset_requisition_supplier_pivot
                -- ═══════════════════════════════════════════════════════════════
                IF _suppliers IS NOT NULL AND _suppliers::TEXT <> '[]' AND _suppliers::TEXT <> 'null' THEN
                    INSERT INTO asset_requisition_supplier_pivot (
                        tenant_id,
                        asset_requisition_type_id,
                        asset_requisition_data_id,
                        supplier_id,
                        is_active,
                        created_at,
                        updated_at
                    )
                    SELECT
                        _tenant_id,
                        3,  -- asset_requisition_type_id = 3 (Replace)
                        v_inserted_id,
                        (elem::TEXT)::BIGINT,
                        TRUE,
                        NOW(),
                        NOW()
                    FROM jsonb_array_elements(_suppliers) AS elem
                    WHERE EXISTS (
                        SELECT 1 FROM suppliers
                        WHERE id = (elem::TEXT)::BIGINT
                    );
                END IF;

                -- ═══════════════════════════════════════════════════════════════
                -- STEP 3: Insert into asset_requisition_decision table
                -- ═══════════════════════════════════════════════════════════════
                INSERT INTO asset_requisition_decision (
                    tenant_id,
                    asset_requisition_id,
                    requisition_type_id,
                    asset_requisition_data_id,
                    status,
                    is_get_action,
                    created_at,
                    updated_at
                )
                VALUES (
                    _tenant_id,
                    v_asset_requisition_id,
                    3,  -- requisition_type_id = 3 (Replace)
                    v_inserted_id,
                    v_replace_status,
                    FALSE,
                    NOW(),
                    NOW()
                )
                RETURNING id INTO v_decision_id;

                -- ═══════════════════════════════════════════════════════════════
                -- STEP 4: Insert into asset_requisition_logs
                -- ═══════════════════════════════════════════════════════════════
                SELECT id INTO v_log_type_id
                FROM asset_requisition_log_types
                WHERE code = 'ASSET_REQUISITION_CREATED'
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
                        v_asset_requisition_id,
                        v_log_type_id,
                        _created_by,
                        NOW(),
                        jsonb_build_object(
                            'replace_requisition_id', v_inserted_id,
                            'asset_requisition_reg_no', v_asset_requisition_reg_no,
                            'asset_id', _asset_id,
                            'decision_id', v_decision_id,
                            'requisition_type', 'REPLACE',
                            'action', 'create'
                        ),
                        TRUE,
                        NOW(),
                        NOW()
                    );
                END IF;

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

                -- ─── Get Expected Condition Details ───
                IF _expected_condition IS NOT NULL THEN
                    SELECT json_build_object(
                        'id', arct.id,
                        'name', arct.name
                    )
                    INTO v_expected_condition_json
                    FROM asset_received_condition_types arct
                    WHERE arct.id = _expected_condition;
                ELSE
                    v_expected_condition_json := NULL;
                END IF;

                -- ─── Get Mode of Acquisition Details ───
                IF _mode_of_acquisition IS NOT NULL THEN
                    SELECT json_build_object(
                        'id', arat.id,
                        'name', arat.name
                    )
                    INTO v_mode_of_acquisition_json
                    FROM asset_requisition_availability_types arat
                    WHERE arat.id = _mode_of_acquisition;
                ELSE
                    v_mode_of_acquisition_json := NULL;
                END IF;

                -- ─── Get Supplier Details ───
                IF _suppliers IS NOT NULL AND _suppliers::TEXT <> '[]' AND _suppliers::TEXT <> 'null' THEN
                    SELECT json_agg(
                        json_build_object(
                            'id', s.id,
                            'name', s.name
                        )
                    )
                    INTO v_suppliers_json
                    FROM jsonb_array_elements(_suppliers) AS elem
                    INNER JOIN suppliers s
                        ON s.id = (elem::TEXT)::BIGINT;
                ELSE
                    v_suppliers_json := '[]'::JSON;
                END IF;

                -- ─── Resolve Reason IDs to Titles/Descriptions ───
                IF _reason IS NOT NULL AND _reason <> '' AND _reason <> '[]' THEN
                    SELECT json_agg(
                        json_build_object(
                            'id', aur.id,
                            'title', aur.title,
                            'description', aur.description
                        )
                    )
                    INTO v_reasons_json
                    FROM json_array_elements_text(_reason::JSON) AS elem
                    INNER JOIN asset_upgrade_replace_reasons aur
                        ON aur.id = elem::BIGINT
                        AND aur.is_active = TRUE;
                ELSE
                    v_reasons_json := '[]'::JSON;
                END IF;

                -- ─── Resolve Outcome IDs to Text/Descriptions ───
                IF _expected_outcomes IS NOT NULL AND _expected_outcomes::TEXT <> '[]' AND _expected_outcomes::TEXT <> 'null' THEN
                    SELECT json_agg(
                        json_build_object(
                            'id', auo.id,
                            'outcome_text', auo.outcome_text,
                            'description', auo.description
                        )
                    )
                    INTO v_outcomes_json
                    FROM jsonb_array_elements(_expected_outcomes) AS elem
                    INNER JOIN asset_upgrade_replace_outcomes auo
                        ON auo.id = (elem::TEXT)::BIGINT
                        AND auo.is_active = TRUE;
                ELSE
                    v_outcomes_json := '[]'::JSON;
                END IF;

                -- ─── Get Maintenance Leaders ───
                v_leaders_json := '[]'::JSON;
                v_leader_ids := '[]'::JSONB;

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
                UPDATE replace_asset_requisitions
                SET notified_maintenance_leaders = v_leader_ids
                WHERE id = v_inserted_id;

                -- ─── Build the Requisition Result ───
                v_requisition_json := json_build_object(
                    'id', v_inserted_id,
                    'asset_requisition_id', v_asset_requisition_id,
                    'asset_requisition_reg_no', v_asset_requisition_reg_no,
                    'decision_id', v_decision_id,
                    'status', v_replace_status,
                    'expected_date', _expected_date,
                    'mode_of_acquisition', v_mode_of_acquisition_json,
                    'expected_condition', v_expected_condition_json,
                    'suppliers', COALESCE(v_suppliers_json, '[]'::JSON),
                    'reasons', COALESCE(v_reasons_json, '[]'::JSON),
                    'reason_ids', _reason,
                    'outcomes', COALESCE(v_outcomes_json, '[]'::JSON),
                    'outcome_ids', _expected_outcomes,
                    'other_reason', _other_reason,
                    'justification', _justification,
                    'replacement_description', _replacement_description,
                    'is_disposal_recommended', COALESCE(_set_asset_to_disposal, FALSE),
                    'error_logs_performance_doc', _error_logs_performance_doc,
                    'screenshots', _screenshots,
                    'other_docs', _other_docs,
                    'work_order_id', _work_order_id,
                    'asset', v_asset_json,
                    'requester', v_requester_json,
                    'priority', v_priority_json,
                    'created_at', NOW()
                );

                -- ─── Activity Log ───
                BEGIN
                    v_log_data := jsonb_build_object(
                        'asset_requisition_id', v_asset_requisition_id,
                        'requisition_id', v_inserted_id,
                        'asset_requisition_reg_no', v_asset_requisition_reg_no,
                        'asset_id', _asset_id,
                        'created_by', _created_by,
                        'action', 'create'
                    );

                    PERFORM log_activity(
                        'replace_asset_requisition.created',
                        'Replace asset requisition created: ',
                        'replace_asset_requisitions',
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
                    'Replace asset requisition created successfully'::TEXT AS message,
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
                    WHERE proname = 'insert_replace_asset_requisition'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
        SQL);
    }
};
