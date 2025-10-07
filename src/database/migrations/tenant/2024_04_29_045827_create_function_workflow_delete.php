<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{ 
    public function up(): void
    {
        // DB::unprepared(
        //     "CREATE OR REPLACE PROCEDURE STORE_PROCEDURE_DELETE_WORKFLOW(
        //         p_workflow_id bigint
        //     ) LANGUAGE plpgsql
        //     AS $$
        //     BEGIN
        //         IF p_workflow_id IS NULL OR p_workflow_id = 0 THEN
        //             RAISE EXCEPTION 'Workflow ID cannot be null or zero';
        //         END IF;
            
        //         IF NOT EXISTS (SELECT 1 FROM workflows WHERE id = p_workflow_id) THEN
        //             RAISE EXCEPTION 'Workflow with ID % does not exist', p_workflow_id;
        //         END IF;
            
        //         -- DELETE FROM workflow_details WHERE workflow_id = p_workflow_id;
        //         -- DELETE FROM workflows WHERE id = p_workflow_id;

        //         UPDATE workflow_details
        //         SET deleted_at = NOW(), isactive = FALSE
        //         WHERE workflow_id = p_workflow_id;

        //         UPDATE workflows
        //         SET deleted_at = NOW(), isactive = FALSE
        //         WHERE id = p_workflow_id;
        //     END;
        //     $$;"
        // );

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION delete_workflow(
                p_workflow_id BIGINT,
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
                deleted_data JSONB;  -- Stores workflow data before deletion
                workflow_exists BOOLEAN; -- Checks if workflow exists
                existing_count INT;      -- Tracks number of existing references
            BEGIN
                -- Assign default value to p_deleted_at if NULL
                IF p_deleted_at IS NULL THEN
                    p_deleted_at := NOW();
                END IF;
        
                -- Validate p_workflow_id
                IF p_workflow_id IS NULL OR p_workflow_id = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Workflow ID cannot be null or zero'::TEXT AS message,
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
        
                -- Check if workflow exists
                SELECT EXISTS(
                    SELECT 1 FROM workflows 
                    WHERE id = p_workflow_id 
                    AND tenant_id = p_tenant_id 
                    AND deleted_at IS NULL
                ) INTO workflow_exists;
        
                IF NOT workflow_exists THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Workflow with the given ID does not exist or is already deleted'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                    RETURN;
                END IF;
        
                -- Check for pending workflow requests (only allow deletion if no pending requests)
                SELECT COUNT(*) INTO existing_count
                FROM workflows w
                INNER JOIN workflow_request_queues wrq ON w.id = wrq.workflow_id
                WHERE w.id = p_workflow_id
                AND w.tenant_id = p_tenant_id
                AND w.deleted_at IS NULL
                AND wrq.workflow_request_status NOT IN ('APPROVED', 'REJECT');
        
                IF existing_count > 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'You cannot delete this Workflow, as it has pending requests'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                    RETURN;
                END IF;
        
                -- Fetch workflow data before deletion
                SELECT jsonb_build_object(
                    'id', id,
                    'workflow_request_type_id', workflow_request_type_id,
                    'workflow_name', workflow_name,
                    'workflow_description', workflow_description,
                    'workflow_status', workflow_status,
                    'is_published', is_published,
                    'tenant_id', tenant_id,
                    'created_at', created_at,
                    'updated_at', updated_at
                ) INTO deleted_data
                FROM workflows
                WHERE id = p_workflow_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;
        
                -- Soft delete workflow details
                UPDATE workflow_details
                SET 
                    deleted_at = p_deleted_at, 
                    isactive = FALSE
                WHERE workflow_id = p_workflow_id;
        
                -- Soft delete workflow
                UPDATE workflows
                SET 
                    deleted_at = p_deleted_at
                WHERE id = p_workflow_id;
        
                -- Check if any row was affected
                GET DIAGNOSTICS existing_count = ROW_COUNT;
                IF existing_count = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No workflow deleted. Workflow may not exist or was already deleted'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                    RETURN;
                END IF;
        
                -- Return success response
                RETURN QUERY SELECT 
                    'SUCCESS'::TEXT AS status, 
                    'Workflow deleted successfully'::TEXT AS message,
                    deleted_data;
            END;
            $$;
        SQL);    

    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS delete_workflow');
    }
};