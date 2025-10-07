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
            CREATE OR REPLACE FUNCTION workflow_request_rejected(
                p_approver_user_id INT,
                p_request_id INT,
                p_workflow_node_id BIGINT,
                p_asset_requisition_id TEXT,
                p_approver_comment TEXT,
                p_request_type_id INT,
                p_status TEXT,
                p_user_name TEXT DEFAULT NULL,
                p_tenant_id BIGINT DEFAULT NULL,
                p_current_time TIMESTAMPTZ DEFAULT now()
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                result_data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_old_data JSONB;
                v_new_data JSONB;
                v_error_message TEXT;
                v_rows_updated INT;
                v_requisition_data JSONB;
            BEGIN
                -- =========================
                -- VALIDATIONS
                -- =========================
                IF p_approver_user_id IS NULL OR p_approver_user_id <= 0 THEN
                    RETURN QUERY SELECT 'ERROR', 'Approver User ID is required', NULL::JSONB;
                    RETURN;
                END IF;

                IF p_request_id IS NULL OR p_request_id <= 0 THEN
                    RETURN QUERY SELECT 'ERROR', 'Request ID is required', NULL::JSONB;
                    RETURN;
                END IF;

                IF p_asset_requisition_id IS NULL OR btrim(p_asset_requisition_id) = '' THEN
                    RETURN QUERY SELECT 'ERROR', 'Asset requisition ID is required', NULL::JSONB;
                    RETURN;
                END IF;

                IF p_workflow_node_id IS NULL THEN
                    RETURN QUERY SELECT 'ERROR', 'Workflow node ID is required', NULL::JSONB;
                    RETURN;
                END IF;

                -- =========================
                -- CAPTURE OLD DATA AND REQUISITION DATA
                -- =========================
                SELECT to_jsonb(w), w.requisition_data_object
                INTO v_old_data, v_requisition_data
                FROM public.workflow_request_queues w
                WHERE id = p_request_id;

                -- =========================
                -- UPDATE workflow_request_queue_details
                -- =========================
                UPDATE public.workflow_request_queue_details
                SET approver_user_id = p_approver_user_id,
                    comment_for_action = p_approver_comment,
                    request_status_from_level = 'REJECT',
                    updated_at = p_current_time
                WHERE request_id = p_request_id
                AND workflow_node_id = p_workflow_node_id
                AND request_status_from_level = 'PENDING';

                -- =========================
                -- UPDATE workflow_request_queues
                -- =========================
                UPDATE public.workflow_request_queues
                SET workflow_request_status = 'REJECT',
                    updated_at = p_current_time
                WHERE id = p_request_id
                AND workflow_request_status = 'PENDING';

                GET DIAGNOSTICS v_rows_updated = ROW_COUNT;
                IF v_rows_updated = 0 THEN
                    RETURN QUERY SELECT 'ERROR', format(
                        'No workflow queue updated for request_id=%s, workflow_node_id=%s',
                        p_request_id, p_workflow_node_id
                    ), NULL::JSONB;
                    RETURN;
                END IF;

                -- =========================
                -- REQUEST TYPE SPECIFIC UPDATES
                -- =========================
                IF p_status = 'REJECT' THEN
                    CASE p_request_type_id
                        WHEN 1 THEN
                            UPDATE public.asset_requisitions
                            SET requisition_status = 'REJECT',
                                updated_at = p_current_time
                            WHERE requisition_id = p_asset_requisition_id;
                        WHEN 2 THEN
                            UPDATE public.suppliers
                            SET supplier_reg_status = 'REJECT',
                                updated_at = p_current_time
                            WHERE supplier_reg_no = p_asset_requisition_id;
                        WHEN 3 THEN
                            UPDATE public.procurements
                            SET procurement_status = 'REJECT',
                                updated_at = p_current_time
                            WHERE request_id = p_asset_requisition_id;
                        WHEN 4 THEN
                            UPDATE public.work_orders
                            SET status = 'REJECT',
                                updated_at = p_current_time
                            WHERE request_id = p_asset_requisition_id;
                    END CASE;
                END IF;

                -- =========================
                -- CAPTURE NEW DATA
                -- =========================
                SELECT to_jsonb(w) INTO v_new_data
                FROM public.workflow_request_queues w
                WHERE id = p_request_id;

                -- =========================
                -- LOG ACTIVITY (Safe)
                -- =========================
                BEGIN
                    PERFORM log_activity(
                        'workflow_request.rejected',
                        format('Workflow request %s rejected by %s', p_request_id, COALESCE(p_user_name, p_approver_user_id::TEXT)),
                        'workflow_request',
                        p_request_id,
                        'user',
                        p_approver_user_id,
                        jsonb_build_object('old_data', v_old_data, 'new_data', v_new_data),
                        p_tenant_id
                    );
                EXCEPTION WHEN OTHERS THEN
                    v_error_message := SQLERRM;
                    RAISE NOTICE 'Log activity failed: %', v_error_message;
                END;

                -- =========================
                -- SUCCESS
                -- =========================
                RETURN QUERY
                SELECT 'SUCCESS', 
                    'Request rejected successfully', 
                    jsonb_build_object(
                        'request_id', p_request_id,
                        'requisition_data', v_requisition_data
                    );

            EXCEPTION
                WHEN OTHERS THEN
                    RETURN QUERY SELECT 'ERROR', 'Database error: ' || SQLERRM, NULL::JSONB;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS store_function_workflow_request_rejected(INT, INT, BIGINT, TEXT, TEXT);');
    }
};
