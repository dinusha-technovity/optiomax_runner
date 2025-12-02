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
                WHERE proname = 'get_all_purchasing_orders'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_all_purchasing_orders(
            p_tenant_id BIGINT,
            p_user_id INT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            purchasing_order_id BIGINT,
            po_number VARCHAR(50),
            supplier JSONB,
            submit_officer_comment TEXT,
            purchasing_officer_comment TEXT,
            supplier_res_status VARCHAR(255),
            supplier_comment TEXT,
            due_date TIMESTAMPTZ,
            total_amount NUMERIC(18,2),
            po_status VARCHAR(30),
            attempt_id BIGINT,
            created_by BIGINT,
            updated_by BIGINT,
            finalized_report JSONB,
            created_at TIMESTAMP,
            updated_at TIMESTAMP,
            workflow_queue_id BIGINT,
            id BIGINT,
            items JSON
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_log_success BOOLEAN := FALSE;
            v_error_message TEXT;
            v_log_data JSONB;
            v_record_count INTEGER := 0;
        BEGIN
            -- Validate tenant ID
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 'Invalid tenant ID provided'::TEXT,
                    NULL::BIGINT, NULL::VARCHAR(50), NULL::JSONB, NULL::TEXT, NULL::TEXT, NULL::VARCHAR(255), NULL::TEXT, NULL::DATE, NULL::NUMERIC(18,2), NULL::VARCHAR(30), NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::JSONB, NULL::TIMESTAMP, NULL::TIMESTAMP, NULL::JSON;
                RETURN;
            END IF;

            -- Validate user ID
            IF p_user_id IS NOT NULL AND p_user_id < 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 'Invalid p_user_id provided'::TEXT,
                    NULL::BIGINT, NULL::VARCHAR(50), NULL::JSONB, NULL::TEXT, NULL::TEXT, NULL::VARCHAR(255), NULL::TEXT, NULL::DATE, NULL::NUMERIC(18,2), NULL::VARCHAR(30), NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::JSONB, NULL::TIMESTAMP, NULL::TIMESTAMP, NULL::JSON;
                RETURN;
            END IF;

            -- Count records
            SELECT COUNT(*) INTO v_record_count
            FROM purchasing_orders po
            WHERE po.tenant_id = p_tenant_id 
            AND (p_user_id IS NULL OR po.created_by = p_user_id)
            AND po.deleted_at IS NULL 
            AND po.isactive = TRUE;

            IF v_record_count = 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 'No matching purchasing orders found'::TEXT,
                    NULL::BIGINT, NULL::VARCHAR(50), NULL::JSONB, NULL::TEXT, NULL::TEXT, NULL::VARCHAR(255), NULL::TEXT, NULL::DATE, NULL::NUMERIC(18,2), NULL::VARCHAR(30), NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::JSONB, NULL::TIMESTAMP, NULL::TIMESTAMP, NULL::JSON;
                RETURN;
            END IF;

            -- Log data
            v_log_data := jsonb_build_object(
                'tenant_id', p_tenant_id,
                'user_id', p_user_id,
                'record_count', v_record_count,
                'action', 'get_all_purchasing_orders'
            );

            BEGIN
                PERFORM log_activity(
                    'purchasing_order.fetched',
                    'Fetched ' || v_record_count || ' purchasing order(s) for tenant ID: ' || p_tenant_id,
                    'purchasing_order',
                    NULL,
                    'user',
                    p_user_id,
                    v_log_data,
                    p_tenant_id
                );
                v_log_success := TRUE;
            EXCEPTION WHEN OTHERS THEN
                v_log_success := FALSE;
                v_error_message := 'Logging failed: ' || SQLERRM;
            END;

            -- Return main query
            RETURN QUERY
            SELECT
                'SUCCESS'::TEXT AS status,
                'Purchasing orders fetched successfully'::TEXT AS message,
                po.id::BIGINT AS purchasing_order_id,
                po.po_number::VARCHAR(50),
                
                -- Supplier JSON
                jsonb_build_object(
                    'id', s.id,
                    'name', s.name,
                    'email', s.email,
                    'contact_no', s.contact_no
                )::JSONB AS supplier,

                po.submit_officer_comment::TEXT,
                po.purchasing_officer_comment::TEXT,
                po.supplier_res_status::VARCHAR(255),
                po.supplier_comment::TEXT,
                po.due_date::TIMESTAMPTZ,
                po.total_amount::NUMERIC(18,2),
                po.status::VARCHAR(30) AS po_status,
                po.attempt_id::BIGINT,
                po.created_by::BIGINT,
                po.updated_by::BIGINT,
                po.finalized_report::JSONB,
                po.created_at::TIMESTAMP,
                po.updated_at::TIMESTAMP,
                po.workflow_queue_id::BIGINT,
                po.id::BIGINT,

                -- Items JSON
                COALESCE(
                    json_agg(
                        json_build_object(
                            'item_id', poi.id,
                            'item_name', ari.item_name,
                            'business_purpose', ari.business_purpose,
                            'reason_for_purchase', ari.reason,
                            'business_impact', ari.business_impact,
                            'po_id', poi.po_id,
                            'procurement_finalized_item', poi.procurement_finalized_item,
                            'procurement_attempt_request_item_id', poi.procurement_attempt_request_item_id,
                            'requisition_id', poi.requisition_id,
                            'quantity', poi.quantity,
                            'approved_quantity', poi.approved_quantity,
                            'unit_price', poi.unit_price,
                            'delivery_cost', pari.delivery_cost,
                            'total_price', poi.total_price,
                            'remarks', poi.remarks,
                            'organization_id', poi.organization_id,
                            'responded_supplier_id', poi.responded_supplier_id,
                            'can_supply', poi.can_supply,
                            'available_quantity', poi.available_quantity,
                            'item_availability_status', poi.item_availability_status,
                            'response_message', poi.response_message,
                            'respond_date', poi.respond_date,
                            'created_at', poi.created_at,
                            'updated_at', poi.updated_at
                        )
                    ) FILTER (WHERE poi.id IS NOT NULL), '[]'::JSON
                )::JSON AS items

            FROM purchasing_orders po
            LEFT JOIN purchasing_order_items poi ON po.id = poi.po_id
            LEFT JOIN suppliers s ON po.supplier_id = s.id
            LEFT JOIN procurement_finalize_items pfi ON pfi.id = poi.procurement_finalized_item
            LEFT JOIN asset_requisitions_items ari ON ari.id = pfi.asset_requisitions_item_id  
            LEFT JOIN procurement_attempt_request_items pari ON pari.id = pfi.finalize_items_id
            WHERE po.tenant_id = p_tenant_id
            AND (p_user_id IS NULL OR po.created_by = p_user_id)
            AND po.deleted_at IS NULL
            AND po.isactive = TRUE
            AND (poi.deleted_at IS NULL OR poi.id IS NULL)
            GROUP BY po.id, s.id;

        EXCEPTION
            WHEN OTHERS THEN
                RETURN QUERY SELECT 
                    'ERROR'::TEXT AS status,
                    ('Database error: ' || SQLERRM)::TEXT AS message,
                    NULL::BIGINT, NULL::VARCHAR(50), NULL::JSONB, NULL::TEXT, NULL::TEXT, NULL::VARCHAR(255), NULL::TEXT, NULL::DATE, NULL::NUMERIC(18,2), NULL::VARCHAR(30), NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::JSONB, NULL::TIMESTAMP, NULL::TIMESTAMP, NULL::JSON;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_all_purchasing_orders(BIGINT, INT);');

    }
};
