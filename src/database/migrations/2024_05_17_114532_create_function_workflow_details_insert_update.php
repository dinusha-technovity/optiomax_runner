<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    { 
        // DB::unprepared(
        //     "CREATE OR REPLACE PROCEDURE STORE_PROCEDURE_INSERT_OR_UPDATE_WORKFLOW_DETAILS(
        //         p_workflow_id bigint,
        //         p_workflow_detail_parent_id bigint,
        //         p_workflow_detail_type_id bigint,
        //         p_workflow_detail_behavior_type_id bigint,
        //         p_workflow_detail_order integer,
        //         p_workflow_detail_level integer,
        //         p_workflow_detail_data_object json,
        //         p_workflow_detail_id bigint DEFAULT NULL
        //     ) LANGUAGE plpgsql
        //     AS $$
        //     BEGIN
        //         IF p_workflow_id IS NULL OR p_workflow_id = 0 THEN
        //             RAISE EXCEPTION 'Workflow ID cannot be null or zero';
        //         END IF;
            
        //         IF p_workflow_detail_type_id IS NULL OR p_workflow_detail_type_id = 0 THEN
        //             RAISE EXCEPTION 'Workflow detail type ID cannot be null or zero';
        //         END IF;
            
        //         IF p_workflow_detail_behavior_type_id IS NULL OR p_workflow_detail_behavior_type_id = 0 THEN
        //             RAISE EXCEPTION 'Workflow detail behavior type ID cannot be null or zero';
        //         END IF;
            
        //         IF p_workflow_detail_order IS NULL THEN
        //             RAISE EXCEPTION 'Workflow detail order cannot be null';
        //         END IF;
            
        //         IF p_workflow_detail_level IS NULL THEN
        //             RAISE EXCEPTION 'Workflow detail level cannot be null';
        //         END IF;
            
        //         IF p_workflow_detail_data_object IS NULL THEN
        //             RAISE EXCEPTION 'Workflow detail data object cannot be null';
        //         END IF;
            
        //         IF p_workflow_detail_id IS NULL OR p_workflow_detail_id = 0 THEN
        //             INSERT INTO workflow_details (workflow_id, workflow_detail_parent_id, workflow_detail_type_id, workflow_detail_behavior_type_id, workflow_detail_order, workflow_detail_level, workflow_detail_data_object, created_at, updated_at)
        //             VALUES (p_workflow_id, p_workflow_detail_parent_id, p_workflow_detail_type_id, p_workflow_detail_behavior_type_id, p_workflow_detail_order, p_workflow_detail_level, p_workflow_detail_data_object, NOW(), NOW());
        //         ELSE
        //             UPDATE workflow_details
        //             SET 
        //                 workflow_detail_parent_id = p_workflow_detail_parent_id,
        //                 workflow_detail_type_id = p_workflow_detail_type_id,
        //                 workflow_detail_behavior_type_id = p_workflow_detail_behavior_type_id,
        //                 workflow_detail_order = p_workflow_detail_order,
        //                 workflow_detail_level = p_workflow_detail_level,
        //                 workflow_detail_data_object = p_workflow_detail_data_object,
        //                 updated_at = NOW()
        //             WHERE id = p_workflow_detail_id AND workflow_id = p_workflow_id;
                    
        //             IF NOT FOUND THEN
        //                 INSERT INTO workflow_details (workflow_id, workflow_detail_parent_id, workflow_detail_type_id, workflow_detail_behavior_type_id, workflow_detail_order, workflow_detail_level, workflow_detail_data_object, created_at, updated_at)
        //                 VALUES (p_workflow_id, p_workflow_detail_parent_id, p_workflow_detail_type_id, p_workflow_detail_behavior_type_id, p_workflow_detail_order, p_workflow_detail_level, p_workflow_detail_data_object, NOW(), NOW());
        //             END IF;
        //         END IF;
        //     END;
        //     $$;"
        // ); 
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION insert_or_update_workflow_details(
                IN p_workflow_id BIGINT,
                IN p_workflow_detail_parent_id BIGINT,
                IN p_workflow_detail_running_parent_id BIGINT,
                IN p_workflow_detail_type_id BIGINT,
                IN p_workflow_detail_behavior_type_id BIGINT,
                IN p_workflow_detail_order INTEGER,
                IN p_workflow_detail_level INTEGER,
                IN p_workflow_detail_data_object JSON,
                IN p_tenant_id BIGINT,
                IN p_current_time TIMESTAMP WITH TIME ZONE,
                IN p_workflow_detail_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                old_workflow_detail JSONB,
                new_workflow_detail JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                old_data JSONB;
                new_data JSONB;
                row_count INT;
            BEGIN
                -- Validate inputs
                IF p_workflow_id IS NULL OR p_workflow_id = 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Workflow ID cannot be null or zero', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;
                
                IF p_workflow_detail_type_id IS NULL OR p_workflow_detail_type_id = 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Workflow detail type ID cannot be null or zero', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;
                
                IF p_workflow_detail_behavior_type_id IS NULL OR p_workflow_detail_behavior_type_id = 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Workflow detail behavior type ID cannot be null or zero', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;
                
                IF p_workflow_detail_order IS NULL THEN
                    RETURN QUERY SELECT 'FAILURE', 'Workflow detail order cannot be null', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;
                
                IF p_workflow_detail_level IS NULL THEN
                    RETURN QUERY SELECT 'FAILURE', 'Workflow detail level cannot be null', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;
                
                IF p_workflow_detail_data_object IS NULL THEN
                    RETURN QUERY SELECT 'FAILURE', 'Workflow detail data object cannot be null', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;
                
                -- Validate if workflow detail exists when updating
                IF p_workflow_detail_id IS NOT NULL AND p_workflow_detail_id != 0 THEN
                    SELECT COUNT(*) INTO row_count FROM workflow_details WHERE id = p_workflow_detail_id;
                    IF row_count = 0 THEN
                        RETURN QUERY SELECT 'FAILURE', 'Workflow detail ID does not exist', NULL::JSONB, NULL::JSONB;
                        RETURN;
                    END IF;
                END IF;
                
                -- Insert or update logic
                IF p_workflow_detail_id IS NULL OR p_workflow_detail_id = 0 THEN
                    -- Insert new workflow detail
                    INSERT INTO workflow_details (
                        workflow_id, workflow_detail_parent_id, workflow_detail_running_parent_id, workflow_detail_type_id, 
                        workflow_detail_behavior_type_id, workflow_detail_order, workflow_detail_level, 
                        workflow_detail_data_object, tenant_id, created_at, updated_at
                    )
                    VALUES (
                        p_workflow_id, p_workflow_detail_parent_id, p_workflow_detail_running_parent_id, p_workflow_detail_type_id, 
                        p_workflow_detail_behavior_type_id, p_workflow_detail_order, p_workflow_detail_level, 
                        p_workflow_detail_data_object, p_tenant_id, p_current_time, p_current_time
                    ) RETURNING to_jsonb(workflow_details.*) INTO new_data;
                    
                    -- Update workflows table
                    UPDATE workflows SET is_published = FALSE WHERE id = p_workflow_id;
                    
                    RETURN QUERY SELECT 'SUCCESS', 'Workflow detail inserted successfully', NULL::JSONB, new_data;
                ELSE
                    -- Fetch old data before updating
                    SELECT to_jsonb(workflow_details.*) INTO old_data
                    FROM workflow_details
                    WHERE id = p_workflow_detail_id;
                    
                    -- Update existing workflow detail
                    UPDATE workflow_details
                    SET 
                        workflow_detail_parent_id = p_workflow_detail_parent_id,
                        workflow_detail_running_parent_id = p_workflow_detail_running_parent_id,
                        workflow_detail_type_id = p_workflow_detail_type_id,
                        workflow_detail_behavior_type_id = p_workflow_detail_behavior_type_id,
                        workflow_detail_order = p_workflow_detail_order,
                        workflow_detail_level = p_workflow_detail_level,
                        workflow_detail_data_object = p_workflow_detail_data_object,
                        tenant_id = p_tenant_id,
                        updated_at = p_current_time
                    WHERE id = p_workflow_detail_id AND workflow_id = p_workflow_id;
                    
                    -- Fetch new data after updating
                    SELECT to_jsonb(workflow_details.*) INTO new_data
                    FROM workflow_details
                    WHERE id = p_workflow_detail_id;
                    
                    -- Update workflows table
                    UPDATE workflows SET is_published = FALSE WHERE id = p_workflow_id;
                    
                    RETURN QUERY SELECT 'SUCCESS', 'Workflow detail updated successfully', old_data, new_data;
                END IF;
            END;
            $$;
        SQL);    

    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS insert_or_update_workflow_details');
    }
};