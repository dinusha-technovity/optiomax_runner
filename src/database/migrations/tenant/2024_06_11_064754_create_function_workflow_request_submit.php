<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    { 
        // DB::unprepared(
        //     "CREATE OR REPLACE PROCEDURE STORE_PROCEDURE_WORKFLOW_REQUEST_SUBMIT(
        //         p_user_id INT,
        //         p_workflow_request_type_id INT,
        //         p_workflow_id INT,
        //         p_asset_requisition_id TEXT,
        //         p_requisition_data_object JSONB,
        //         p_request_comment TEXT DEFAULT NULL
        //     )
        //     AS $$
        //     DECLARE
        //         error_message TEXT;
        //         request_id BIGINT;
        //     BEGIN
        //         DROP TABLE IF EXISTS response;
        //         CREATE TEMP TABLE response (
        //             status TEXT,
        //             message TEXT,
        //             request_id BIGINT DEFAULT 0
        //         );

        //         IF p_user_id IS NULL OR p_user_id <= 0 THEN
        //             INSERT INTO response (status, message)
        //             VALUES ('ERROR', 'User ID cannot be NULL or less than or equal to 0');
        //             RETURN;
        //         END IF;

        //         IF p_workflow_request_type_id IS NULL OR p_workflow_request_type_id <= 0 THEN
        //             INSERT INTO response (status, message)
        //             VALUES ('ERROR', 'Workflow request type ID cannot be NULL or less than or equal to 0');
        //             RETURN;
        //         END IF;

        //         IF p_workflow_id IS NULL OR p_workflow_id <= 0 THEN
        //             INSERT INTO response (status, message)
        //             VALUES ('ERROR', 'Workflow ID cannot be NULL or less than or equal to 0');
        //             RETURN;
        //         END IF;

        //         IF p_requisition_data_object IS NULL THEN
        //             INSERT INTO response (status, message)
        //             VALUES ('ERROR', 'Requisition data object cannot be NULL');
        //             RETURN;
        //         END IF;

        //         IF p_request_comment IS NOT NULL AND LENGTH(p_request_comment) > 500 THEN
        //             INSERT INTO response (status, message)
        //             VALUES ('ERROR', 'Request comment exceeds the maximum length of 500 characters');
        //             RETURN;
        //         END IF;

        //         BEGIN
        //             INSERT INTO workflow_request_queues(
        //                 user_id, 
        //                 workflow_request_type, 
        //                 workflow_id, 
        //                 requisition_data_object, 
        //                 workflow_request_status
        //             )
        //             VALUES ( 
        //                 p_user_id, 
        //                 p_workflow_request_type_id, 
        //                 p_workflow_id, 
        //                 p_requisition_data_object, 
        //                 'PENDING'
        //             )RETURNING id INTO request_id;

        //             RAISE INFO 'Test %',request_id;
                    
        //             INSERT INTO response (status, message, request_id)
        //             VALUES ('SUCCESS', 'Data inserted successfully', request_id);
        //         EXCEPTION
        //             WHEN OTHERS THEN
        //                 error_message := SQLERRM;
        //                 INSERT INTO response (status, message)
        //                 VALUES ('ERROR', 'Error during insert: ' || error_message);
        //         END;
        //     END;
        //     $$ LANGUAGE plpgsql;"
        // );

        // DB::unprepared(
        //     "CREATE OR REPLACE FUNCTION workflow_request_submit(
        //         IN p_user_id INT,
        //         IN p_workflow_request_type_id INT,
        //         IN p_workflow_id INT,
        //         IN p_asset_requisition_id TEXT,
        //         IN p_requisition_data_object JSONB,
        //         IN p_tenant_id BIGINT,
        //         IN p_current_time TIMESTAMP WITH TIME ZONE,
        //         IN p_request_comment TEXT DEFAULT NULL
        //     )
        //     RETURNS TABLE (
        //         status TEXT,
        //         message TEXT,
        //         request_id BIGINT
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     DECLARE
        //         error_message TEXT;
        //         request_id BIGINT;
        //     BEGIN
        //         -- Validate input parameters
        //         IF p_user_id IS NULL OR p_user_id <= 0 THEN
        //             RETURN QUERY SELECT 'ERROR', 'User ID cannot be NULL or less than or equal to 0', NULL::BIGINT;
        //             RETURN;
        //         END IF;

        //         IF p_workflow_request_type_id IS NULL OR p_workflow_request_type_id <= 0 THEN
        //             RETURN QUERY SELECT 'ERROR', 'Workflow request type ID cannot be NULL or less than or equal to 0', NULL::BIGINT;
        //             RETURN;
        //         END IF;

        //         IF p_workflow_id IS NULL OR p_workflow_id <= 0 THEN
        //             RETURN QUERY SELECT 'ERROR', 'Workflow ID cannot be NULL or less than or equal to 0', NULL::BIGINT;
        //             RETURN;
        //         END IF;

        //         IF p_requisition_data_object IS NULL THEN
        //             RETURN QUERY SELECT 'ERROR', 'Requisition data object cannot be NULL', NULL::BIGINT;
        //             RETURN;
        //         END IF;

        //         IF p_request_comment IS NOT NULL AND LENGTH(p_request_comment) > 500 THEN
        //             RETURN QUERY SELECT 'ERROR', 'Request comment exceeds the maximum length of 500 characters', NULL::BIGINT;
        //             RETURN;
        //         END IF;

        //         BEGIN
        //             -- Insert into workflow_request_queues and capture the request ID
        //             INSERT INTO workflow_request_queues (
        //                 user_id,
        //                 workflow_request_type,
        //                 workflow_id,
        //                 requisition_data_object,
        //                 workflow_request_status,
        //                 tenant_id,
        //                 created_at,
        //                 updated_at
        //             )
        //             VALUES (
        //                 p_user_id,
        //                 p_workflow_request_type_id,
        //                 p_workflow_id,
        //                 p_requisition_data_object,
        //                 'PENDING',
        //                 p_tenant_id,
        //                 p_current_time,
        //                 p_current_time
        //             )
        //             RETURNING id INTO request_id;

        //             -- Return success response
        //             RETURN QUERY SELECT 'SUCCESS', 'Data inserted successfully', request_id;
        //         EXCEPTION
        //             WHEN OTHERS THEN
        //                 error_message := SQLERRM;
        //                 RETURN QUERY SELECT 'ERROR', 'Error during insert: ' || error_message, NULL::BIGINT;
        //         END;
        //     END;
        //     $$;
        //     "
        // );

        DB::unprepared(<<<SQL
        CREATE OR REPLACE FUNCTION workflow_request_submit(
            IN p_user_id INT,
            IN p_workflow_request_type_id INT,
            IN p_workflow_id INT,
            IN p_workflow_details_row_id INT,
            IN p_asset_requisition_id TEXT,
            IN p_requisition_data_object JSONB,
            IN p_tenant_id BIGINT,
            IN p_current_time TIMESTAMP WITH TIME ZONE,
            IN p_request_comment TEXT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            request_id BIGINT,
            related_users JSONB
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            error_message TEXT;
            request_id BIGINT;
            user_rec RECORD;
            related_users JSONB := '[]'::JSONB;
        BEGIN
            IF p_user_id IS NULL OR p_user_id <= 0 THEN
                RETURN QUERY SELECT 'ERROR', 'User ID cannot be NULL or less than or equal to 0', NULL::BIGINT, NULL::JSONB;
                RETURN;
            END IF;
        
            IF p_workflow_request_type_id IS NULL OR p_workflow_request_type_id <= 0 THEN
                RETURN QUERY SELECT 'ERROR', 'Workflow request type ID cannot be NULL or less than or equal to 0', NULL::BIGINT, NULL::JSONB;
                RETURN;
            END IF;
        
            IF p_workflow_id IS NULL OR p_workflow_id <= 0 THEN
                RETURN QUERY SELECT 'ERROR', 'Workflow ID cannot be NULL or less than or equal to 0', NULL::BIGINT, NULL::JSONB;
                RETURN;
            END IF;
        
            IF p_requisition_data_object IS NULL THEN
                RETURN QUERY SELECT 'ERROR', 'Requisition data object cannot be NULL', NULL::BIGINT, NULL::JSONB;
                RETURN;
            END IF;
        
            IF p_request_comment IS NOT NULL AND LENGTH(p_request_comment) > 500 THEN
                RETURN QUERY SELECT 'ERROR', 'Request comment exceeds the maximum length of 500 characters', NULL::BIGINT, NULL::JSONB;
                RETURN;
            END IF;
        
            BEGIN
                INSERT INTO workflow_request_queues (
                    user_id,
                    workflow_request_type,
                    workflow_id,
                    requisition_data_object,
                    workflow_request_status,
                    tenant_id,
                    created_at,
                    updated_at
                )
                VALUES (
                    p_user_id,
                    p_workflow_request_type_id,
                    p_workflow_id,
                    p_requisition_data_object,
                    'PENDING',
                    p_tenant_id,
                    p_current_time,
                    p_current_time
                )
                RETURNING workflow_request_queues.id INTO request_id;
        
                FOR user_rec IN SELECT * FROM get_users_from_workflow_detail(p_workflow_details_row_id)
                LOOP
                    related_users := related_users || to_jsonb(user_rec);
                END LOOP;
        
                RETURN QUERY SELECT 'SUCCESS'::TEXT, 'Data inserted successfully'::TEXT, request_id::BIGINT, related_users::JSONB;
            EXCEPTION
                WHEN OTHERS THEN
                    error_message := SQLERRM;
                    RETURN QUERY SELECT 'ERROR'::TEXT, 'Error during insert: ' || error_message, NULL::BIGINT, NULL::JSONB;
            END;
        END;
        $$;
        SQL);
    }
 
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS workflow_request_submit');
    }
};