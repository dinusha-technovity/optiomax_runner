<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    { 
        // DB::unprepared(
        //     "CREATE OR REPLACE PROCEDURE STORE_PROCEDURE_INSERT_OR_UPDATE_WORKFLOW(
        //         p_workflow_request_type_id bigint,
        //         p_workflow_name text,
        //         p_workflow_description text,
        //         p_tenant_id BIGINT,
        //         OUT p_inserted_or_updated_workflow_id bigint,
        //         p_workflow_id bigint DEFAULT NULL,
        //         p_workflow_status boolean DEFAULT true 
        //     ) LANGUAGE plpgsql
        //     AS $$
        //     BEGIN
        //         IF p_workflow_request_type_id IS NULL OR p_workflow_request_type_id = 0 THEN
        //             RAISE EXCEPTION 'Workflow request type ID cannot be null or zero';
        //         END IF;
                
        //         IF p_workflow_name IS NULL OR p_workflow_name = '' THEN 
        //             RAISE EXCEPTION 'Workflow name cannot be null or empty';
        //         END IF;
            
        //         IF p_workflow_description IS NULL THEN
        //             RAISE EXCEPTION 'Workflow description cannot be null';
        //         END IF;
            
        //         IF p_workflow_id IS NULL OR p_workflow_id = 0 THEN

        //             INSERT INTO workflows (workflow_request_type_id, workflow_name, workflow_description, workflow_status, tenant_id, created_at, updated_at)
        //             VALUES (p_workflow_request_type_id, p_workflow_name, p_workflow_description, p_workflow_status, p_tenant_id, NOW(), NOW())
        //             RETURNING id INTO p_inserted_or_updated_workflow_id;
        //         ELSE

        //             UPDATE workflows
        //             SET 
        //                 workflow_request_type_id = p_workflow_request_type_id,
        //                 workflow_name = p_workflow_name,
        //                 workflow_description = p_workflow_description,
        //                 workflow_status = p_workflow_status,
        //                 updated_at = NOW()
        //             WHERE id = p_workflow_id;
                    
        //             p_inserted_or_updated_workflow_id := p_workflow_id;
        //         END IF;
        //     END;
        //     $$;"
        // ); 
        // DB::unprepared(
        //     "CREATE OR REPLACE PROCEDURE STORE_PROCEDURE_INSERT_OR_UPDATE_WORKFLOW(
        //         p_workflow_request_type_id bigint,
        //         p_workflow_name text,
        //         p_workflow_description text,
        //         p_tenant_id BIGINT,
        //         INOUT p_inserted_or_updated_workflow_id bigint,
        //         p_workflow_id bigint DEFAULT NULL,
        //         p_workflow_status boolean DEFAULT true 
        //     ) LANGUAGE plpgsql
        //     AS $$
        //     BEGIN
        //         IF p_workflow_request_type_id IS NULL OR p_workflow_request_type_id = 0 THEN
        //             RAISE EXCEPTION 'Workflow request type ID cannot be null or zero';
        //         END IF;
                
        //         IF p_workflow_name IS NULL OR p_workflow_name = '' THEN 
        //             RAISE EXCEPTION 'Workflow name cannot be null or empty';
        //         END IF;
            
        //         IF p_workflow_description IS NULL THEN
        //             RAISE EXCEPTION 'Workflow description cannot be null';
        //         END IF;
            
        //         IF p_workflow_id IS NULL OR p_workflow_id = 0 THEN
        //             -- Insert new workflow
        //             INSERT INTO workflows (workflow_request_type_id, workflow_name, workflow_description, workflow_status, tenant_id, created_at, updated_at)
        //             VALUES (p_workflow_request_type_id, p_workflow_name, p_workflow_description, p_workflow_status, p_tenant_id, NOW(), NOW())
        //             RETURNING id INTO p_inserted_or_updated_workflow_id;
        //         ELSE
        //             -- Update existing workflow
        //             UPDATE workflows
        //             SET 
        //                 workflow_request_type_id = p_workflow_request_type_id,
        //                 workflow_name = p_workflow_name,
        //                 workflow_description = p_workflow_description,
        //                 workflow_status = p_workflow_status,
        //                 updated_at = NOW()
        //             WHERE id = p_workflow_id;
                    
        //             p_inserted_or_updated_workflow_id := p_workflow_id;
        //         END IF;
        //     END;
        //     $$;"
        // );

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION insert_or_update_workflow(
                IN p_workflow_request_type_id BIGINT,
                IN p_workflow_name TEXT,
                IN p_workflow_description TEXT,
                IN p_tenant_id BIGINT,
                IN p_current_time TIMESTAMP WITH TIME ZONE,
                IN p_workflow_id BIGINT DEFAULT NULL,
                IN p_workflow_status BOOLEAN DEFAULT true
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                old_workflow JSONB,
                new_workflow JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                p_inserted_or_updated_workflow_id BIGINT;
                existing_count INT;
                old_data JSONB;
                new_data JSONB;
            BEGIN
                -- Validate inputs
                IF p_workflow_request_type_id IS NULL OR p_workflow_request_type_id = 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Workflow request type ID cannot be null or zero', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;
        
                IF p_workflow_name IS NULL OR LENGTH(TRIM(p_workflow_name)) = 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Workflow name cannot be null or empty', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;
        
                IF p_workflow_description IS NULL THEN
                    RETURN QUERY SELECT 'FAILURE', 'Workflow description cannot be null', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;
        
                -- Check for existing workflow name
                IF p_workflow_id IS NULL OR p_workflow_id = 0 THEN
                    -- Insert check
                    SELECT COUNT(*) INTO existing_count
                    FROM workflows
                    WHERE workflow_name = p_workflow_name
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;
                ELSE
                    -- Update check excluding current workflow
                    SELECT COUNT(*) INTO existing_count
                    FROM workflows
                    WHERE workflow_name = p_workflow_name
                    AND tenant_id = p_tenant_id
                    AND id != p_workflow_id
                    AND deleted_at IS NULL;
                END IF;
        
                IF existing_count > 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Workflow name already exists', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;
        
                IF p_workflow_id IS NULL OR p_workflow_id = 0 THEN
                    -- Insert new workflow
                    INSERT INTO workflows (
                        workflow_request_type_id, 
                        workflow_name, 
                        workflow_description, 
                        workflow_status, 
                        tenant_id, 
                        created_at, 
                        updated_at
                    )
                    VALUES (
                        p_workflow_request_type_id, 
                        p_workflow_name, 
                        p_workflow_description, 
                        p_workflow_status, 
                        p_tenant_id, 
                        p_current_time, 
                        p_current_time
                    )
                    RETURNING id, to_jsonb(workflows.*) INTO p_inserted_or_updated_workflow_id, new_data;
        
                    RETURN QUERY SELECT 'SUCCESS', 'Workflow inserted successfully', NULL::JSONB, new_data;
                ELSE
                    -- Fetch old data before updating
                    SELECT to_jsonb(workflows.*) INTO old_data
                    FROM workflows
                    WHERE id = p_workflow_id;
        
                    -- Update existing workflow
                    UPDATE workflows
                    SET 
                        workflow_request_type_id = p_workflow_request_type_id,
                        workflow_name = p_workflow_name,
                        workflow_description = p_workflow_description,
                        workflow_status = p_workflow_status,
                        updated_at = p_current_time
                    WHERE id = p_workflow_id;
        
                    -- Fetch new data after updating
                    SELECT to_jsonb(workflows.*) INTO new_data
                    FROM workflows
                    WHERE id = p_workflow_id;
        
                    RETURN QUERY SELECT 'SUCCESS', 'Workflow updated successfully', old_data, new_data;
                END IF;
            END;
            $$;
        SQL);    
        
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS insert_or_update_workflow');
    }
};