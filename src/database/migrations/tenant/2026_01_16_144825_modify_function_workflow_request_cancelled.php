<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
                        WHERE proname = 'workflow_request_cancelled'
                    LOOP
                        EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                    END LOOP;
                END$$;

                CREATE OR REPLACE FUNCTION workflow_request_cancelled(
                    IN p_canceller_user_id BIGINT,
                    IN p_request_id BIGINT,
                    IN p_workflow_type_id BIGINT,
                    IN p_requisition_id BIGINT,
                    IN p_canceller_comment TEXT,
                    IN p_tenant_id BIGINT,
                    IN p_user_name TEXT
                )
                RETURNS TABLE (
                    status TEXT,
                    message TEXT,
                    approval_users_list JSONB,
                    requisition_data JSONB
                )
                LANGUAGE plpgsql
                AS $$
                DECLARE
                    v_request_exists BOOLEAN := FALSE;
                    v_requisition_data JSONB;
                    v_approval_users JSONB := '[]'::JSONB;
                    queue_detail_record RECORD;
                    v_error_message TEXT;
                BEGIN
                    -- Validate inputs
                    IF p_canceller_user_id IS NULL THEN
                        RETURN QUERY SELECT 'ERROR', 'Canceller user ID is required', NULL::JSONB, NULL::JSONB;
                        RETURN;
                    END IF;

                    IF p_request_id IS NULL THEN
                        RETURN QUERY SELECT 'ERROR', 'Request ID is required', NULL::JSONB, NULL::JSONB;
                        RETURN;
                    END IF;

                    IF p_tenant_id IS NULL THEN
                        RETURN QUERY SELECT 'ERROR', 'Tenant ID is required', NULL::JSONB, NULL::JSONB;
                        RETURN;
                    END IF;

                    -- Check request exists
                    SELECT EXISTS (
                        SELECT 1 FROM workflow_request_queues 
                        WHERE id = p_request_id 
                        AND tenant_id = p_tenant_id
                        AND deleted_at IS NULL
                    ) INTO v_request_exists;

                    IF NOT v_request_exists THEN
                        RETURN QUERY SELECT 'ERROR', 'Workflow request not found or access denied', NULL::JSONB, NULL::JSONB;
                        RETURN;
                    END IF;

                    -- Get requisition data from workflow_request_queues
                    SELECT requisition_data_object
                    INTO v_requisition_data
                    FROM workflow_request_queues 
                    WHERE id = p_request_id 
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;

                    -- Try collecting approval users via queue details
                    FOR queue_detail_record IN
                        SELECT workflow_node_id
                        FROM workflow_request_queue_details
                        WHERE request_id = p_request_id
                        AND tenant_id = p_tenant_id
                        AND deleted_at IS NULL
                    LOOP
                        v_approval_users := v_approval_users || (
                            SELECT COALESCE(jsonb_agg(to_jsonb(u)), '[]'::jsonb)
                            FROM get_users_from_workflow_detail(queue_detail_record.workflow_node_id) u
                        );
                    END LOOP;

                    -- If still empty, fall back to workflow_type_id directly
                    IF jsonb_array_length(v_approval_users) = 0 THEN
                        v_approval_users := (
                            SELECT COALESCE(jsonb_agg(to_jsonb(u)), '[]'::jsonb)
                            FROM get_users_from_workflow_detail(p_workflow_type_id) u
                        );
                    END IF;

                    -- Remove duplicates
                    IF jsonb_array_length(v_approval_users) > 0 THEN
                        WITH unique_users AS (
                            SELECT DISTINCT ON ((user_obj ->> 'id')::BIGINT) user_obj
                            FROM jsonb_array_elements(v_approval_users) AS user_obj
                        )
                        SELECT jsonb_agg(user_obj)
                        INTO v_approval_users
                        FROM unique_users;
                    ELSE
                        v_approval_users := '[]'::JSONB;
                    END IF;

                    -- Update request + details
                    UPDATE workflow_request_queues 
                    SET workflow_request_status = 'CANCELLED',
                        updated_at = now()
                    WHERE id = p_request_id 
                    AND tenant_id = p_tenant_id;

                    UPDATE workflow_request_queue_details
                    SET request_status_from_level = 'CANCELLED',
                    approver_user_id= p_canceller_user_id,
                    comment_for_action= p_canceller_comment,
                        updated_at = now()
                    WHERE request_id = p_request_id;
                
                --==================LOGGING==================
                    BEGIN
                        PERFORM log_activity(
                            'workflow_request.cancelled',
                            format('Workflow request %s cancelled by %s', p_request_id, COALESCE(p_user_name, p_canceller_user_id::TEXT)),
                            'workflow_request',
                            p_request_id,
                            'user',
                            p_canceller_user_id,
                            jsonb_build_object('REASON OF CANCELLATION',p_canceller_comment,'requisition_data', v_requisition_data),
                            p_tenant_id
                        );
                    EXCEPTION WHEN OTHERS THEN
                        v_error_message := SQLERRM;
                        RAISE NOTICE 'Log activity failed: %', v_error_message;
                    END;
                    --==================LOGGING==================

                    -- Return result
                    RETURN QUERY SELECT 
                        'SUCCESS',
                        'Workflow request cancelled successfully',
                        v_approval_users,
                        v_requisition_data;

                EXCEPTION
                    WHEN OTHERS THEN
                        RETURN QUERY SELECT 'ERROR', 'Database error: ' || SQLERRM, NULL::JSONB, NULL::JSONB;
                END;
                $$;
        SQL);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS workflow_request_cancelled(BIGINT, BIGINT, BIGINT, BIGINT, TEXT, BIGINT, TEXT);");
    }
};
