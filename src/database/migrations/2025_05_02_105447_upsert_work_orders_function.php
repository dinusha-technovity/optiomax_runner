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
           DROP FUNCTION IF EXISTS upsert_work_order_func(
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
                    p_skills_certifications TEXT,
                    p_risk_assessment TEXT,
                    p_safety_instruction TEXT,
                    p_compliance_note TEXT,
                    p_work_order_start TIMESTAMP,
                    p_work_order_end TIMESTAMP,
                    p_expected_duration INTEGER,
                    p_expected_duration_unit TEXT,
                    p_labour_hours DECIMAL(8,2),
                    p_est_cost DECIMAL(10,2),
                    p_permit_documents JSONB,
                    p_work_order_materials JSONB,
                    p_work_order_equipments JSONB,
                    p_tenant_id BIGINT,
                    p_user_id BIGINT,
                    p_user_name TEXT,
                    p_actual_work_order_start TIMESTAMP  ,
                    p_actual_work_order_end TIMESTAMP  ,
                    p_completion_note TEXT  ,
                    p_actual_used_materials JSONB  ,
                    p_technician_comment TEXT  ,
                    p_completion_images JSONB  

        );
        CREATE OR REPLACE FUNCTION upsert_work_order_func(
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
                    p_skills_certifications TEXT,
                    p_risk_assessment TEXT,
                    p_safety_instruction TEXT,
                    p_compliance_note TEXT,
                    p_work_order_start TIMESTAMP,
                    p_work_order_end TIMESTAMP,
                    p_expected_duration INTEGER,
                    p_expected_duration_unit TEXT,
                    p_labour_hours DECIMAL(8,2),
                    p_est_cost DECIMAL(10,2),
                    p_permit_documents JSONB,
                    p_work_order_materials JSONB,
                    p_work_order_equipments JSONB,
                    p_tenant_id BIGINT,
                    p_user_id BIGINT,
                    p_user_name TEXT,
                    p_actual_work_order_start TIMESTAMP DEFAULT NULL,
                    p_actual_work_order_end TIMESTAMP DEFAULT NULL,
                    p_completion_note TEXT DEFAULT NULL,
                    p_actual_used_materials JSONB DEFAULT NULL,
                    p_technician_comment TEXT DEFAULT NULL,
                    p_completion_images JSONB DEFAULT NULL
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
                    v_work_order_data JSONB;
                    v_old_data JSONB;
                    v_new_data JSONB;
                    v_action_type TEXT;
                    v_log_success BOOLEAN;
                    v_error_message TEXT;
                    v_current_year TEXT;
                BEGIN
                    -- Initialize validation errors array
                    v_validation_errors := ARRAY[]::TEXT[];
                    
                    -- Get current year for sequence management
                    v_current_year := to_char(CURRENT_DATE, 'YYYY');
                    
                    -- Generate or validate work order number
                    IF p_id = 0 THEN
                        -- Check if sequence exists for current year
                        IF NOT EXISTS (
                            SELECT 1 FROM pg_sequences 
                            WHERE sequencename = 'work_order_sequence_' || v_current_year
                        ) THEN
                            -- Create new sequence for current year
                            EXECUTE format('CREATE SEQUENCE work_order_sequence_%s START WITH 1', v_current_year);
                        END IF;
                        
                        -- Generate new work order number
                        EXECUTE format('SELECT ''WO-'' || %L || ''-'' || lpad(nextval(''work_order_sequence_'' || %L)::TEXT, 3, ''0'')', 
                                    v_current_year, v_current_year)
                        INTO v_work_order_number;
                    ELSE
                        -- For updates, get existing work order number
                        SELECT work_order_number INTO v_work_order_number
                        FROM work_orders
                        WHERE id = p_id AND tenant_id = p_tenant_id;
                        
                        IF NOT FOUND THEN
                            RETURN QUERY SELECT 
                                'FAILURE'::TEXT AS operation_status,
                                'Work order not found'::TEXT AS message,
                                NULL::JSONB AS result_data;
                            RETURN;
                        END IF;
                    END IF;
                    
                    -- Validate required fields
                    IF p_title IS NULL OR p_title = '' THEN
                        v_validation_errors := array_append(v_validation_errors, 'Title is required');
                    END IF;
                    
                    IF p_type IS NULL OR p_type = '' THEN
                        v_validation_errors := array_append(v_validation_errors, 'Type is required');
                    END IF;
                    
                    IF p_priority IS NULL OR p_priority = '' THEN
                        v_validation_errors := array_append(v_validation_errors, 'Priority is required');
                    END IF;
                    
                    IF p_scope_of_work IS NULL OR p_scope_of_work = '' THEN
                        v_validation_errors := array_append(v_validation_errors, 'Scope of work is required');
                    END IF;
                    
                    -- Validate foreign key relationships if provided
                    IF p_asset_item_id IS NOT NULL AND NOT EXISTS (
                        SELECT 1 FROM asset_items WHERE id = p_asset_item_id AND deleted_at IS NULL
                    ) THEN
                        v_validation_errors := array_append(v_validation_errors, 'Invalid asset item ID');
                    END IF;
                    
                    IF p_technician_id IS NOT NULL AND NOT EXISTS (
                        SELECT 1 FROM work_order_technicians WHERE id = p_technician_id AND deleted_at IS NULL AND isactive = TRUE
                    ) THEN
                        v_validation_errors := array_append(v_validation_errors, 'Invalid technician ID');
                    END IF;
                    
                    IF p_maintenance_type_id IS NOT NULL AND NOT EXISTS (
                        SELECT 1 FROM work_order_maintenance_types WHERE id = p_maintenance_type_id AND deleted_at IS NULL AND isactive = TRUE
                    ) THEN
                        v_validation_errors := array_append(v_validation_errors, 'Invalid maintenance type ID');
                    END IF;
                    
                    IF p_budget_code_id IS NOT NULL AND NOT EXISTS (
                        SELECT 1 FROM work_order_budget_codes WHERE id = p_budget_code_id AND deleted_at IS NULL AND isactive = TRUE
                    ) THEN
                        v_validation_errors := array_append(v_validation_errors, 'Invalid budget code ID');
                    END IF;
                    
                
                    
                    -- If there are validation errors, return them
                    IF array_length(v_validation_errors, 1) > 0 THEN
                        RETURN QUERY SELECT 
                            'FAILURE'::TEXT AS operation_status,
                            'Validation failed'::TEXT AS message,
                            jsonb_build_object('errors', v_validation_errors) AS result_data;
                        RETURN;
                    END IF;

                    -- Begin transaction block
                    BEGIN
                        -- Determine action type for logging
                        IF p_id = 0 THEN
                            v_action_type := 'created';
                        ELSE
                            v_action_type := 'updated';

                            -- Get old data for logging
                            SELECT jsonb_build_object(
                                'work_order_number', work_order_number,
                                'title', title,
                                'description', description,
                                'asset_item_id', asset_item_id,
                                'technician_id', technician_id,
                                'maintenance_type_id', maintenance_type_id,
                                'budget_code_id', budget_code_id,
                                'approved_supervisor_id', approved_supervisor_id,
                                'type', type,
                                'priority', priority,
                                'status', status,
                                'job_title', job_title,
                                'job_title_description', job_title_description,
                                'scope_of_work', scope_of_work,
                                'skills_certifications', skills_certifications,
                                'risk_assessment', risk_assessment,
                                'safety_instruction', safety_instruction,
                                'compliance_note', compliance_note,
                                'work_order_start', work_order_start,
                                'work_order_end', work_order_end,
                                'expected_duration', expected_duration,
                                'expected_duration_unit', expected_duration_unit,
                                'labour_hours', labour_hours,
                                'est_cost', est_cost,
                                'permit_documents', permit_documents,
                                'work_order_materials', work_order_materials,
                                'work_order_equipments', work_order_equipments,
                                'actual_work_order_start', actual_work_order_start,
                                'actual_work_order_end', actual_work_order_end,
                                'completion_note', completion_note,
                                'actual_used_materials', actual_used_materials,
                                'technician_comment', technician_comment,
                                'completion_images', completion_images,
                                'isactive', isactive
                            ) INTO v_old_data
                            FROM work_orders
                            WHERE id = p_id;
                        END IF;
                        
                        -- Upsert work order record
                        IF p_id = 0 THEN
                            -- Insert new work order
                            INSERT INTO work_orders (
                                work_order_number, title, description,
                                asset_item_id, technician_id, maintenance_type_id, budget_code_id,
                                type, priority, status, job_title, job_title_description,
                                scope_of_work, skills_certifications, risk_assessment, safety_instruction, compliance_note,
                                work_order_start, work_order_end, expected_duration, expected_duration_unit,
                                labour_hours, est_cost, permit_documents, work_order_materials, work_order_equipments,
                                actual_work_order_start, actual_work_order_end, completion_note,
                                actual_used_materials, technician_comment, completion_images,
                                tenant_id,user_id
                            ) VALUES (
                                v_work_order_number, p_title, p_description,
                                p_asset_item_id, p_technician_id, p_maintenance_type_id, p_budget_code_id,
                                p_type, p_priority, COALESCE(p_status, 'pending'), p_job_title, p_job_title_description,
                                p_scope_of_work, p_skills_certifications, p_risk_assessment, p_safety_instruction, p_compliance_note,
                                p_work_order_start, p_work_order_end, p_expected_duration, p_expected_duration_unit,
                                p_labour_hours, p_est_cost, p_permit_documents, p_work_order_materials, p_work_order_equipments,
                                p_actual_work_order_start, p_actual_work_order_end, p_completion_note,
                                p_actual_used_materials, p_technician_comment, p_completion_images,
                                p_tenant_id,p_user_id
                            ) RETURNING id INTO v_work_order_id;
                        ELSE
                            -- Update existing work order
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
                                skills_certifications = p_skills_certifications,
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
                                completion_images = p_completion_images
                            WHERE id = p_id
                            RETURNING id INTO v_work_order_id;
                        END IF;
                        
                        -- Prepare work order data for logging
                        v_new_data := jsonb_build_object(
                            'id', v_work_order_id,
                            'work_order_number', v_work_order_number,
                            'title', p_title,
                            'description', p_description,
                            'asset_item_id', p_asset_item_id,
                            'technician_id', p_technician_id,
                            'maintenance_type_id', p_maintenance_type_id,
                            'budget_code_id', p_budget_code_id,
                            'type', p_type,
                            'priority', p_priority,
                            'status', COALESCE(p_status, CASE WHEN p_id = 0 THEN 'pending' ELSE NULL END),
                            'job_title', p_job_title,
                            'job_title_description', p_job_title_description,
                            'scope_of_work', p_scope_of_work,
                            'skills_certifications', p_skills_certifications,
                            'risk_assessment', p_risk_assessment,
                            'safety_instruction', p_safety_instruction,
                            'compliance_note', p_compliance_note,
                            'work_order_start', p_work_order_start,
                            'work_order_end', p_work_order_end,
                            'expected_duration', p_expected_duration,
                            'expected_duration_unit', p_expected_duration_unit,
                            'labour_hours', p_labour_hours,
                            'est_cost', p_est_cost,
                            'permit_documents', p_permit_documents,
                            'work_order_materials', p_work_order_materials,
                            'work_order_equipments', p_work_order_equipments,
                            'actual_work_order_start', p_actual_work_order_start,
                            'actual_work_order_end', p_actual_work_order_end,
                            'completion_note', p_completion_note,
                            'actual_used_materials', p_actual_used_materials,
                            'technician_comment', p_technician_comment,
                            'completion_images', p_completion_images,
                            'action', v_action_type,
                            'tenant_id', p_tenant_id,
                            'user_id', p_user_id,
                            'user_name', p_user_name
                        );

                        -- Combine old and new data for logging
                        v_work_order_data := jsonb_build_object(
                            'old_data', v_old_data,
                            'new_data', v_new_data
                        );
                        
                        -- Log the activity (with error handling)
                        BEGIN
                            PERFORM log_activity(
                                'work_order.' || v_action_type,
                                'Work order ' || v_action_type || ' by ' || p_user_name || ': ' || p_title,
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
                            v_error_message := 'Logging failed: ' || SQLERRM;
                        END;
                        
                        -- Return success with the work order ID and log status
                        RETURN QUERY SELECT 
                            'SUCCESS'::TEXT AS operation_status,
                            CASE 
                                WHEN p_id = 0 THEN 'Work order created successfully' 
                                ELSE 'Work order updated successfully' 
                            END || 
                            CASE 
                                WHEN NOT v_log_success THEN ' (but logging failed: ' || v_error_message || ')'
                                ELSE ''
                            END::TEXT AS message,
                            jsonb_build_object(
                                'work_order_id', v_work_order_id,
                                'work_order_number', v_work_order_number,
                                'log_data', v_work_order_data,
                                'log_success', COALESCE(v_log_success, FALSE)
                            ) AS result_data;
                        
                    EXCEPTION WHEN OTHERS THEN
                        -- Attempt to log the error
                        BEGIN
                            PERFORM log_activity(
                                'work_order.error',
                                'Failed to ' || COALESCE(v_action_type, 'process') || ' work order: ' || SQLERRM,
                                'work_order',
                                COALESCE(v_work_order_id, p_id),
                                'user',
                                p_user_id,
                                jsonb_build_object(
                                    'error', SQLERRM,
                                    'input', jsonb_build_object(
                                        'title', p_title,
                                        'user_id', p_user_id,
                                        'tenant_id', p_tenant_id
                                    )
                                ),
                                p_tenant_id
                            );
                        EXCEPTION WHEN OTHERS THEN
                            -- If logging fails, just continue
                        END;
                        
                        RETURN QUERY SELECT 
                            'ERROR'::TEXT AS operation_status,
                            'Database error: ' || SQLERRM::TEXT AS message,
                            NULL::JSONB AS result_data;
                    END;
                END;
                $$;
        
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS upsert_work_order_func');
    }
};
