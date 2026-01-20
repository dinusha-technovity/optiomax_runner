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

        DO $$
        DECLARE
            r RECORD;
        BEGIN
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'get_workflow_request_queues_data'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_workflow_request_queues_data(
            p_workflow_request_type INT,
            p_workflow_status TEXT,
            p_tenant_id BIGINT,
            p_user_name TEXT,
            p_auth_user_id BIGINT,
            p_queue_id BIGINT
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            id BIGINT,
            user_id BIGINT,
            requisition_data_object JSONB,
            workflow_request_status CHAR
        )
        LANGUAGE plpgsql
        AS $$
        BEGIN
            -- Validate tenant_id
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN QUERY
                SELECT 'FAILURE', 'Invalid tenant ID', NULL, NULL, NULL, NULL;
                RETURN;
            END IF;

            RETURN QUERY
            WITH data AS (
                SELECT
                    wrq.id,
                    wrq.user_id,

                    -- 🔹 Merge existing requisition data with workflow status + details
                    wrq.requisition_data_object::jsonb
                    || jsonb_build_object(
                        'workflow_request_status', wrq.workflow_request_status::text,
                        'workflow_details',
                        CASE
                            WHEN wrqd.id IS NOT NULL THEN
                                jsonb_build_object(
                                    'approver_user_id', wrqd.approver_user_id,
                                    'approver_name', u.user_name,
                                    'comment_for_action', wrqd.comment_for_action,
                                    'created_at', wrqd.created_at,
                                    'updated_at', wrqd.updated_at
                                )
                            ELSE NULL
                        END
                    ) AS requisition_data_object,

                    wrq.workflow_request_status,
                    wrq.created_at,
                    wrq.updated_at

                FROM workflow_request_queues wrq
                LEFT JOIN workflow_request_queue_details wrqd
                    ON wrqd.request_id = wrq.id
                LEFT JOIN users u
                    ON wrqd.approver_user_id = u.id
                WHERE wrq.tenant_id = p_tenant_id
                AND wrq.isactive = TRUE
                AND wrq.user_id = p_auth_user_id
                AND wrq.workflow_request_type = p_workflow_request_type
                AND wrq.workflow_request_status = ANY(string_to_array(p_workflow_status, ','))
                AND (
                        (p_queue_id = 0 AND wrq.user_id = p_auth_user_id) OR
                        (p_queue_id = -1) OR
                        (p_queue_id > 0 AND wrq.id = p_queue_id)
                    )
                ORDER BY wrq.created_at DESC
            )
            SELECT
                'SUCCESS',
                'Workflow request queues retrieved successfully',
                d.id,
                d.user_id,
                d.requisition_data_object,
                d.workflow_request_status
            FROM data d;

            -- Logging (unchanged)
            BEGIN
                PERFORM log_activity(
                    'view_workflow_request_queue',
                    format(
                        'Viewed workflow request queue%s by %s',
                        CASE
                            WHEN p_queue_id = -1 THEN ' (all)'
                            WHEN p_queue_id = 0 THEN ' (user-specific)'
                            ELSE format(' ID %s', p_queue_id)
                        END,
                        p_user_name
                    ),
                    'workflow_request_queues',
                    CASE WHEN p_queue_id > 0 THEN p_queue_id ELSE NULL END,
                    'user',
                    p_auth_user_id,
                    NULL,
                    p_tenant_id
                );
            EXCEPTION WHEN OTHERS THEN
                NULL;
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
         DB::unprepared('DROP FUNCTION IF EXISTS get_workflow_request_queues_data(INT, CHAR, BIGINT, TEXT, BIGINT, BIGINT);');
    }
};
