<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
        
             -- drop all versions of the function if exists
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'insert_or_update_work_order'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION insert_or_update_work_order(
                p_id BIGINT,
                p_title TEXT,
                p_description TEXT,
                p_asset_item_id BIGINT,
                p_technician_id BIGINT,
                p_maintenance_type_id BIGINT,
                p_budget_code_id BIGINT,
                p_type TEXT,
                p_priority TEXT,
                p_status TEXT,
                p_job_title TEXT,
                p_job_title_description TEXT,
                p_scope_of_work TEXT,
                p_risk_assessment TEXT,
                p_safety_instruction TEXT,
                p_compliance_note TEXT,
                p_work_order_start TIMESTAMPTZ,
                p_work_order_end TIMESTAMPTZ,
                p_expected_duration DECIMAL,
                p_expected_duration_unit TEXT,
                p_labour_hours NUMERIC,
                p_est_cost NUMERIC,
                p_permit_documents JSONB,
                p_work_order_materials JSONB,
                p_work_order_equipments JSONB,
                p_tenant_id BIGINT,
                p_user_id BIGINT,
                p_user_name TEXT,
                p_actual_work_order_start TIMESTAMPTZ DEFAULT NULL,
                p_actual_work_order_end TIMESTAMPTZ DEFAULT NULL,
                p_completion_note TEXT DEFAULT NULL,
                p_actual_used_materials JSONB DEFAULT NULL,
                p_technician_comment TEXT DEFAULT NULL,
                p_completion_images JSONB DEFAULT NULL,
                p_work_order_ticket_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                operation_status TEXT,
                message TEXT,
                result_data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_work_order_id BIGINT;
                v_work_order_number TEXT;
                v_validation_errors TEXT[];
                v_old_data JSONB;
                v_new_data JSONB;
                v_work_order_data JSONB;
                v_action_type TEXT;
                v_log_success BOOLEAN;
                v_error_message TEXT;
                v_current_year TEXT := to_char(CURRENT_DATE, 'YYYY');
                rec JSONB;
            BEGIN
                -- Initialize validation errors array
                v_validation_errors := ARRAY[]::TEXT[];

                -- Validate required fields
                IF p_title IS NULL OR btrim(p_title) = '' THEN
                    v_validation_errors := array_append(v_validation_errors, 'Title is required');
                END IF;

                IF p_type IS NULL OR btrim(p_type) = '' THEN
                    v_validation_errors := array_append(v_validation_errors, 'Type is required');
                END IF;

                IF p_priority IS NULL OR btrim(p_priority) = '' THEN
                    v_validation_errors := array_append(v_validation_errors, 'Priority is required');
                END IF;

                IF p_scope_of_work IS NULL OR btrim(p_scope_of_work) = '' THEN
                    v_validation_errors := array_append(v_validation_errors, 'Scope of work is required');
                END IF;

                -- Validate foreign keys if provided
                IF p_asset_item_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM asset_items WHERE id = p_asset_item_id AND deleted_at IS NULL) THEN
                    v_validation_errors := array_append(v_validation_errors, 'Invalid asset item ID');
                END IF;

                IF p_technician_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM users WHERE id = p_technician_id AND deleted_at IS NULL AND is_user_active) THEN
                    v_validation_errors := array_append(v_validation_errors, 'Invalid technician ID');
                END IF;

                IF p_maintenance_type_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM work_order_maintenance_types WHERE id = p_maintenance_type_id AND deleted_at IS NULL AND isactive) THEN
                    v_validation_errors := array_append(v_validation_errors, 'Invalid maintenance type ID');
                END IF;

                IF p_budget_code_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM work_order_budget_codes WHERE id = p_budget_code_id AND deleted_at IS NULL AND isactive) THEN
                    v_validation_errors := array_append(v_validation_errors, 'Invalid budget code ID');
                END IF;

                -- Return if validation fails
                IF array_length(v_validation_errors, 1) > 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Validation failed', jsonb_build_object('errors', v_validation_errors);
                    RETURN;
                END IF;

                -- Generate or retrieve work_order_number
                IF p_id = 0 THEN
                    -- Check or create sequence for the current year
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_class c
                        JOIN pg_namespace n ON n.oid = c.relnamespace
                        WHERE c.relkind = 'S' AND c.relname = 'work_order_sequence_' || v_current_year
                    ) THEN
                        EXECUTE format('CREATE SEQUENCE work_order_sequence_%s START 1', v_current_year);
                    END IF;

                    EXECUTE format(
                        'SELECT ''WO-'' || %L || ''-'' || LPAD(nextval(''work_order_sequence_%s'')::TEXT, 4, ''0'')',
                        v_current_year, v_current_year
                    ) INTO v_work_order_number;
                ELSE
                    SELECT work_order_number INTO v_work_order_number
                    FROM work_orders
                    WHERE id = p_id AND tenant_id = p_tenant_id;

                    IF NOT FOUND THEN
                        RETURN QUERY SELECT 'FAILURE', 'Work order not found', NULL;
                        RETURN;
                    END IF;
                END IF;

                -- Retrieve old data if updating
                IF p_id <> 0 THEN
                    SELECT to_jsonb(w) INTO v_old_data FROM work_orders w WHERE w.id = p_id;
                    v_action_type := 'updated';
                ELSE
                    v_old_data := NULL;
                    v_action_type := 'created';
                END IF;

                -- UPSERT work_orders table
                IF p_id = 0 THEN
                    INSERT INTO work_orders (
                        work_order_number, title, description,
                        asset_item_id, technician_id, maintenance_type_id, budget_code_id,
                        type, priority, status, job_title, job_title_description,
                        scope_of_work, risk_assessment, safety_instruction, compliance_note,
                        work_order_start, work_order_end, expected_duration, expected_duration_unit,
                        labour_hours, est_cost, permit_documents, work_order_materials, work_order_equipments,
                        actual_work_order_start, actual_work_order_end, completion_note,
                        actual_used_materials, technician_comment, completion_images,
                        tenant_id, user_id, work_order_ticket_id
                    ) VALUES (
                        v_work_order_number, p_title, p_description,
                        p_asset_item_id, p_technician_id, p_maintenance_type_id, p_budget_code_id,
                        p_type, p_priority, COALESCE(p_status, 'scheduled'), p_job_title, p_job_title_description,
                        p_scope_of_work, p_risk_assessment, p_safety_instruction, p_compliance_note,
                        p_work_order_start, p_work_order_end, p_expected_duration, p_expected_duration_unit,
                        p_labour_hours, p_est_cost, p_permit_documents, p_work_order_materials, p_work_order_equipments,
                        p_actual_work_order_start, p_actual_work_order_end, p_completion_note,
                        p_actual_used_materials, p_technician_comment, p_completion_images,
                        p_tenant_id, p_user_id, p_work_order_ticket_id
                    ) RETURNING id INTO v_work_order_id;

                    
                ELSE
                    UPDATE work_orders SET
                        title = p_title,
                        description = p_description,
                        asset_item_id = p_asset_item_id,
                        technician_id = p_technician_id,
                        maintenance_type_id = p_maintenance_type_id,
                        budget_code_id = p_budget_code_id,
                        type = p_type,
                        priority = p_priority,
                        status = COALESCE(p_status, status),
                        job_title = p_job_title,
                        job_title_description = p_job_title_description,
                        scope_of_work = p_scope_of_work,
                        risk_assessment = p_risk_assessment,
                        safety_instruction = p_safety_instruction,
                        compliance_note = p_compliance_note,
                        work_order_start = p_work_order_start,
                        work_order_end = p_work_order_end,
                        expected_duration = p_expected_duration,
                        expected_duration_unit = p_expected_duration_unit,
                        labour_hours = p_labour_hours,
                        est_cost = p_est_cost,
                        permit_documents = p_permit_documents,
                        work_order_materials = p_work_order_materials,
                        work_order_equipments = p_work_order_equipments,
                        actual_work_order_start = p_actual_work_order_start,
                        actual_work_order_end = p_actual_work_order_end,
                        completion_note = p_completion_note,
                        actual_used_materials = p_actual_used_materials,
                        technician_comment = p_technician_comment,
                        completion_images = p_completion_images,
                        work_order_ticket_id = p_work_order_ticket_id
                    WHERE id = p_id
                    RETURNING id INTO v_work_order_id;
                END IF;

                -- Delete existing related requested items for this work order
                DELETE FROM work_orders_related_requested_item
                WHERE work_order_id = v_work_order_id AND tenant_id = p_tenant_id;

                -- Insert new related requested items from JSONB array
                IF p_work_order_materials IS NOT NULL THEN
                    FOR rec IN SELECT * FROM jsonb_array_elements(p_work_order_materials)
                    LOOP
                        INSERT INTO work_orders_related_requested_item (
                            work_order_id,
                            item_id,
                            requested_qty,
                            tenant_id,
                            isactive,
                            created_at,
                            updated_at
                        ) VALUES (
                            v_work_order_id,
                            (rec->>'id')::BIGINT,
                            (rec->>'quantity')::NUMERIC,
                            p_tenant_id,
                            TRUE,
                            NOW(),
                            NOW()
                        );
                    END LOOP;
                END IF;

                -- Prepare new data JSON for logging
                SELECT to_jsonb(w) INTO v_new_data FROM work_orders w WHERE id = v_work_order_id;

                v_work_order_data := jsonb_build_object(
                    'old_data', v_old_data,
                    'new_data', v_new_data
                );

                -- Log activity safely
                BEGIN
                    PERFORM log_activity(
                        'work_order.' || v_action_type,
                        format('Work order %s by %s: %s', v_action_type, p_user_name, p_title),
                        'work_order',
                        v_work_order_id,
                        'user',
                        p_user_id,
                        v_work_order_data,
                        p_tenant_id
                    );
                    v_log_success := TRUE;
                EXCEPTION WHEN OTHERS THEN
                    v_log_success := FALSE;
                    v_error_message := SQLERRM;
                END;

                -- Return result with log status info
                RETURN QUERY SELECT
                    'SUCCESS'::TEXT AS operation_status,
                    CASE
                        WHEN p_id = 0 THEN 'Work order created successfully'
                        ELSE 'Work order updated successfully'
                    END ||
                    CASE
                        WHEN NOT v_log_success THEN ' (Logging failed: ' || v_error_message || ')'
                        ELSE ''
                    END AS message,
                    v_new_data AS result_data;

            EXCEPTION WHEN OTHERS THEN
                -- Log error safely
                BEGIN
                    PERFORM log_activity(
                        'work_order.error',
                        'Error in upsert_work_order_func: ' || SQLERRM,
                        'work_order',
                        COALESCE(v_work_order_id, p_id),
                        'user',
                        p_user_id,
                        jsonb_build_object('error', SQLERRM, 'input', jsonb_build_object('title', p_title, 'user_id', p_user_id, 'tenant_id', p_tenant_id)),
                        p_tenant_id
                    );
                EXCEPTION WHEN OTHERS THEN
                    -- suppress
                    NULL;
                END;

                RETURN QUERY SELECT
                    'ERROR'::TEXT,
                    'Database error: ' || SQLERRM::TEXT,
                    NULL::JSONB;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(' DROP FUNCTION IF EXISTS insert_or_update_work_order(
                BIGINT, TEXT, TEXT, BIGINT, BIGINT, BIGINT, BIGINT,
                TEXT, TEXT, TEXT, TEXT, TEXT, TEXT, TEXT, TEXT, TEXT,
                TIMESTAMPTZ, TIMESTAMPTZ, DECIMAL, TEXT, NUMERIC, NUMERIC,
                JSONB, JSONB, JSONB, BIGINT, BIGINT, TEXT,
                TIMESTAMPTZ, TIMESTAMPTZ, TEXT, JSONB, TEXT, JSONB, BIGINT);');
    }
};