<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
        DROP FUNCTION IF EXISTS workflow_request_submit(
            INT, INT, INT, INT, TEXT, JSONB, BIGINT, TIMESTAMPTZ, TEXT, JSONB
        );

        CREATE OR REPLACE FUNCTION workflow_request_submit(
            IN p_user_id INT,
            IN p_workflow_request_type_id INT,
            IN p_workflow_id INT,
            IN p_workflow_details_row_id INT,
            IN p_asset_requisition_id TEXT,
            IN p_requisition_data_object JSONB,
            IN p_tenant_id BIGINT,
            IN p_current_time TIMESTAMP WITH TIME ZONE,
            IN p_request_comment TEXT DEFAULT NULL,
            IN p_variable_values JSONB DEFAULT NULL
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
                    variable_values,
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
                    p_variable_values,
                    'PENDING',
                    p_tenant_id,
                    p_current_time,
                    p_current_time
                )
                RETURNING id INTO request_id;

                FOR user_rec IN SELECT * FROM get_users_from_workflow_detail(p_workflow_details_row_id)
                LOOP
                    related_users := related_users || to_jsonb(user_rec);
                END LOOP;

                RETURN QUERY SELECT 'SUCCESS', 'Data inserted successfully', request_id, related_users;
            EXCEPTION
                WHEN OTHERS THEN
                    error_message := SQLERRM;
                    RETURN QUERY SELECT 'ERROR', 'Error during insert: ' || error_message, NULL::BIGINT, NULL::JSONB;
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
        //
    }
};
