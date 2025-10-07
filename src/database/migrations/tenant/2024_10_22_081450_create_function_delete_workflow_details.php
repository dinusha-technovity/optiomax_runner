<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    { 
        // DB::unprepared(
        //     "CREATE OR REPLACE PROCEDURE STORE_PROCEDURE_DELETE_WORKFLOW_DETAILS(
        //         p_workflow_detail_id INT
        //     )
        //     AS $$
        //     BEGIN
        //         -- Validate workflow_detail_id input
        //         IF p_workflow_detail_id IS NULL OR p_workflow_detail_id = 0 THEN
        //             RAISE EXCEPTION 'Workflow detail ID cannot be null or zero';
        //         END IF;

        //         -- Attempt to delete the workflow detail
        //         DELETE FROM workflow_details
        //         WHERE id = p_workflow_detail_id;

        //         -- If no rows are affected, raise an exception
        //         IF NOT FOUND THEN
        //             RAISE EXCEPTION 'No matching workflow detail found for Detail ID: %', 
        //                             p_workflow_detail_id;
        //         END IF;

        //         -- Optionally, log success
        //         RAISE NOTICE 'Workflow detail with ID % successfully removed', 
        //                      p_workflow_detail_id;
        //     END;
        //     $$ LANGUAGE plpgsql;"
        // );

        // DB::unprepared(<<<SQL
        //     CREATE OR REPLACE FUNCTION delete_workflow_details(
        //         p_workflow_detail_id BIGINT,
        //         p_tenant_id BIGINT,
        //         p_deleted_at TIMESTAMP WITH TIME ZONE
        //     )
        //     RETURNS TABLE (
        //         status TEXT, 
        //         message TEXT,
        //         deleted_data JSONB
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     DECLARE
        //         deleted_data JSONB;
        //         workflow_details_exists BOOLEAN;
        //         existing_count INT;
        //         workflow_id BIGINT;
        //     BEGIN
        //         -- Assign default value to p_deleted_at if NULL
        //         IF p_deleted_at IS NULL THEN
        //             p_deleted_at := NOW();
        //         END IF;
        
        //         -- Validate workflow_detail_id
        //         IF p_workflow_detail_id IS NULL OR p_workflow_detail_id = 0 THEN
        //             RETURN QUERY SELECT 
        //                 'FAILURE'::TEXT AS status, 
        //                 'Workflow Details ID cannot be null or zero'::TEXT AS message,
        //                 NULL::JSONB AS deleted_data;
        //             RETURN;
        //         END IF;
        
        //         -- Validate p_tenant_id
        //         IF p_tenant_id IS NULL OR p_tenant_id = 0 THEN
        //             RETURN QUERY SELECT 
        //                 'FAILURE'::TEXT AS status, 
        //                 'Tenant ID cannot be null or zero'::TEXT AS message,
        //                 NULL::JSONB AS deleted_data;
        //             RETURN;
        //         END IF;
        
        //         -- Check if workflow detail exists
        //         SELECT EXISTS(
        //             SELECT 1 FROM workflow_details 
        //             WHERE id = p_workflow_detail_id 
        //             AND tenant_id = p_tenant_id 
        //             AND deleted_at IS NULL
        //         ) INTO workflow_details_exists;
        
        //         IF NOT workflow_details_exists THEN
        //             RETURN QUERY SELECT 
        //                 'FAILURE'::TEXT AS status, 
        //                 'Workflow Details with the given ID does not exist or is already deleted'::TEXT AS message,
        //                 NULL::JSONB AS deleted_data;
        //             RETURN;
        //         END IF;
        
        //         -- Fetch workflow data before deletion
        //         SELECT jsonb_build_object(
        //             'id', id,
        //             'workflow_detail_parent_id', workflow_detail_parent_id,
        //             'workflow_id', workflow_id,
        //             'workflow_detail_type_id', workflow_detail_type_id,
        //             'workflow_detail_behavior_type_id', workflow_detail_behavior_type_id,
        //             'workflow_detail_order', workflow_detail_order,
        //             'workflow_detail_level', workflow_detail_level,
        //             'workflow_detail_data_object', workflow_detail_data_object,
        //             'created_at', created_at,
        //             'updated_at', updated_at
        //         ), workflow_id
        //         INTO deleted_data, workflow_id
        //         FROM workflow_details
        //         WHERE id = p_workflow_detail_id
        //         AND tenant_id = p_tenant_id
        //         AND deleted_at IS NULL;
        
        //         -- Soft delete workflow details
        //         UPDATE workflow_details
        //         SET 
        //             deleted_at = p_deleted_at, 
        //             isactive = FALSE
        //         WHERE id = p_workflow_detail_id;
        
        //         -- Check if any row was affected
        //         GET DIAGNOSTICS existing_count = ROW_COUNT;
        //         IF existing_count = 0 THEN
        //             RETURN QUERY SELECT 
        //                 'FAILURE'::TEXT AS status, 
        //                 'No workflow detail deleted. It may not exist or was already deleted'::TEXT AS message,
        //                 NULL::JSONB AS deleted_data;
        //             RETURN;
        //         END IF;
        
        //         -- Soft delete workflow by updating is_published
        //         UPDATE workflows 
        //         SET is_published = FALSE 
        //         WHERE id = workflow_id;
        
        //         -- Return success response
        //         RETURN QUERY SELECT 
        //             'SUCCESS'::TEXT AS status, 
        //             'Workflow detail deleted successfully'::TEXT AS message,
        //             deleted_data;
        //     END;
        //     $$;
        // SQL); 
        
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION delete_workflow_details(
                p_workflow_detail_id BIGINT,
                p_tenant_id BIGINT,
                p_deleted_at TIMESTAMP WITH TIME ZONE
            )
            RETURNS TABLE (
                status TEXT, 
                message TEXT,
                deleted_data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                deleted_data JSONB;
                workflow_details_exists BOOLEAN;
                existing_count INT;
                v_workflow_id BIGINT; -- Renamed to avoid ambiguity
            BEGIN
                -- Assign default value to p_deleted_at if NULL
                IF p_deleted_at IS NULL THEN
                    p_deleted_at := NOW();
                END IF;

                -- Validate workflow_detail_id
                IF p_workflow_detail_id IS NULL OR p_workflow_detail_id = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Workflow Details ID cannot be null or zero'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                    RETURN;
                END IF;

                -- Validate p_tenant_id
                IF p_tenant_id IS NULL OR p_tenant_id = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Tenant ID cannot be null or zero'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                    RETURN;
                END IF;

                -- Check if workflow detail exists
                SELECT EXISTS(
                    SELECT 1 FROM workflow_details 
                    WHERE id = p_workflow_detail_id 
                    AND tenant_id = p_tenant_id 
                    AND deleted_at IS NULL
                ) INTO workflow_details_exists;

                IF NOT workflow_details_exists THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Workflow Details with the given ID does not exist or is already deleted'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                    RETURN;
                END IF;

                -- Fetch workflow data before deletion
                SELECT jsonb_build_object(
                    'id', id,
                    'workflow_detail_parent_id', workflow_detail_parent_id,
                    'workflow_id', workflow_details.workflow_id,  -- Explicitly referencing the table column
                    'workflow_detail_type_id', workflow_detail_type_id,
                    'workflow_detail_behavior_type_id', workflow_detail_behavior_type_id,
                    'workflow_detail_order', workflow_detail_order,
                    'workflow_detail_level', workflow_detail_level,
                    'workflow_detail_data_object', workflow_detail_data_object,
                    'created_at', created_at,
                    'updated_at', updated_at
                ), workflow_details.workflow_id
                INTO deleted_data, v_workflow_id  -- Store workflow_id in v_workflow_id to avoid ambiguity
                FROM workflow_details
                WHERE id = p_workflow_detail_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

                -- Soft delete workflow details
                UPDATE workflow_details
                SET 
                    deleted_at = p_deleted_at, 
                    isactive = FALSE
                WHERE id = p_workflow_detail_id;

                -- Check if any row was affected
                GET DIAGNOSTICS existing_count = ROW_COUNT;
                IF existing_count = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No workflow detail deleted. It may not exist or was already deleted'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                    RETURN;
                END IF;

                -- Soft delete workflow by updating is_published
                UPDATE workflows 
                SET is_published = FALSE 
                WHERE id = v_workflow_id;  -- Use v_workflow_id instead of workflow_id to avoid ambiguity

                -- Return success response
                RETURN QUERY SELECT 
                    'SUCCESS'::TEXT AS status, 
                    'Workflow detail deleted successfully'::TEXT AS message,
                    deleted_data;
            END;
            $$;
        SQL);
    
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS delete_workflow_details');
    }
};