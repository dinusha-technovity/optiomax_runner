<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // DB::unprepared(
        //     "CREATE OR REPLACE PROCEDURE STORE_PROCEDURE_LIST_RELEVANT_WORKFLOWS(
        //         IN p_workflow_request_type_id INT DEFAULT NULL,
        //         IN p_user_id INT DEFAULT NULL,
        //         IN p_tenant_id BIGINT DEFAULT NULL
        //     )
        //     AS $$
        //     BEGIN
        //         DROP TABLE IF EXISTS all_relevant_workflows_from_store_procedure;
            
        //         IF p_workflow_request_type_id IS NOT NULL AND p_workflow_request_type_id < 0 THEN
        //             RAISE EXCEPTION 'Invalid p_workflow_request_type_id: %', p_workflow_request_type_id;
        //         END IF;
            
        //         IF p_user_id IS NOT NULL AND p_user_id < 0 THEN 
        //             RAISE EXCEPTION 'Invalid p_user_id: %', p_user_id;
        //         END IF;
            
        //         CREATE TEMP TABLE all_relevant_workflows_from_store_procedure AS
        //         SELECT 
        //             workflows.id AS workflow_id,
        //             workflows.workflow_name
        //         FROM workflows
        //         WHERE (p_workflow_request_type_id IS NULL OR p_workflow_request_type_id = 0 OR workflows.workflow_request_type_id = p_workflow_request_type_id)
        //             AND (workflows.workflow_status = true)
        //             AND workflows.tenant_id = p_tenant_id
        //             AND workflows.deleted_at IS NULL
        //             AND workflows.is_published = TRUE;
        //     END;
        //     $$ LANGUAGE plpgsql;"
        // );
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_workflow_request_related_workflows(
                p_workflow_request_type_id INT DEFAULT NULL,
                p_user_id INT DEFAULT NULL,
                p_tenant_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                workflow_id BIGINT,
                workflow_name TEXT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                workflow_count INT;
            BEGIN
                -- Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT AS workflow_id,
                        NULL::TEXT AS workflow_name;
                    RETURN;
                END IF;
        
                -- Validate workflow request type ID
                IF p_workflow_request_type_id IS NOT NULL AND p_workflow_request_type_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid p_workflow_request_type_id'::TEXT AS message,
                        NULL::BIGINT AS workflow_id,
                        NULL::TEXT AS workflow_name;
                    RETURN;
                END IF;
        
                -- Validate user ID
                IF p_user_id IS NOT NULL AND p_user_id < 0 THEN 
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid p_user_id'::TEXT AS message,
                        NULL::BIGINT AS workflow_id,
                        NULL::TEXT AS workflow_name;
                    RETURN;
                END IF;
        
                -- Check if any matching workflows exist
                SELECT COUNT(*) INTO workflow_count
                FROM workflows
                WHERE (p_workflow_request_type_id IS NULL OR p_workflow_request_type_id = 0 OR workflows.workflow_request_type_id = p_workflow_request_type_id)
                    AND workflows.workflow_status = TRUE
                    AND workflows.tenant_id = p_tenant_id
                    AND workflows.deleted_at IS NULL
                    AND workflows.is_published = TRUE;
        
                IF workflow_count = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'No matching workflows found'::TEXT AS message,
                        NULL::BIGINT AS workflow_id,
                        NULL::TEXT AS workflow_name;
                    RETURN;
                END IF;
        
                -- Return the matching workflows
                RETURN QUERY
                SELECT 
                    'SUCCESS'::TEXT AS status,
                    'Workflows fetched successfully'::TEXT AS message,
                    workflows.id AS workflow_id,
                    workflows.workflow_name::TEXT
                FROM workflows
                WHERE (p_workflow_request_type_id IS NULL OR p_workflow_request_type_id = 0 OR workflows.workflow_request_type_id = p_workflow_request_type_id)
                    AND workflows.workflow_status = TRUE
                    AND workflows.tenant_id = p_tenant_id
                    AND workflows.deleted_at IS NULL
                    AND workflows.is_published = TRUE;
            END;
            $$;
        SQL);    

    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_workflow_request_related_workflows');
    }
};