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
                WHERE proname = 'get_purchase_order_details_for_supplier'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;



        CREATE OR REPLACE FUNCTION get_purchase_order_details_for_supplier(
            IN p_tenant_id BIGINT,
            IN p_po_number VARCHAR(255),
            IN p_supplier_email VARCHAR(255)
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            po_details JSONB,
            po_items JSONB
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            po_record RECORD;
            po_id_found BIGINT;
        BEGIN
            -- Validate input parameters
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN QUERY
                SELECT 
                    'FAILURE'::TEXT AS status,
                    'Invalid tenant ID provided'::TEXT AS message,
                    NULL::JSONB AS po_details,
                    NULL::JSONB AS po_items;
                RETURN;
            END IF;

            IF p_po_number IS NULL OR p_po_number = '' THEN
                RETURN QUERY
                SELECT 
                    'FAILURE'::TEXT AS status,
                    'Purchase order number is required'::TEXT AS message,
                    NULL::JSONB AS po_details,
                    NULL::JSONB AS po_items;
                RETURN;
            END IF;

            IF p_supplier_email IS NULL OR p_supplier_email = '' THEN
                RETURN QUERY
                SELECT 
                    'FAILURE'::TEXT AS status,
                    'Supplier email is required'::TEXT AS message,
                    NULL::JSONB AS po_details,
                    NULL::JSONB AS po_items;
                RETURN;
            END IF;

            -- Get purchase order details with supplier information
            SELECT 
                po.*,
                s.name as supplier_name,
                s.email as supplier_email,
                po.id as po_id
            INTO po_record
            FROM purchasing_orders po
            INNER JOIN suppliers s ON po.supplier_id = s.id
            WHERE po.po_number = p_po_number
            AND po.tenant_id = p_tenant_id
            AND s.email = p_supplier_email
            AND po.deleted_at IS NULL
            AND s.deleted_at IS NULL;

            -- Check if purchase order exists
            IF NOT FOUND THEN
                RETURN QUERY
                SELECT 
                    'FAILURE'::TEXT AS status,
                    'Purchase order not found or supplier email does not match'::TEXT AS message,
                    NULL::JSONB AS po_details,
                    NULL::JSONB AS po_items;
                RETURN;
            END IF;

            po_id_found := po_record.po_id;

            -- Return success with purchase order details and items
            RETURN QUERY
            SELECT 
                'SUCCESS'::TEXT AS status,
                'Purchase order details retrieved successfully'::TEXT AS message,
                jsonb_build_object(
                    'id', po_record.id,
                    'po_number', po_record.po_number,
                    'supplier_id', po_record.supplier_id,
                    'supplier_name', po_record.supplier_name,
                    'supplier_email', po_record.supplier_email,
                    'submit_officer_comment', po_record.submit_officer_comment,
                    'purchasing_officer_comment', po_record.purchasing_officer_comment,
                    'supplier_res_status', po_record.supplier_res_status,
                    'supplier_comment', po_record.supplier_comment,
                    'due_date', po_record.due_date,
                    'total_amount', po_record.total_amount,
                    'status', po_record.status,
                    'tenant_id', po_record.tenant_id,
                    'isactive', po_record.isactive,
                    'attempt_id', po_record.attempt_id,
                    'created_by', po_record.created_by,
                    'updated_by', po_record.updated_by,
                    'finalized_report', po_record.finalized_report,
                    'created_at', po_record.created_at,
                    'updated_at', po_record.updated_at
                ) AS po_details,
                COALESCE(
                    (
                        SELECT jsonb_agg(
                            jsonb_build_object(
                                'item_id', poi.id,
                                'item_name', ari.item_name,
                                'business_purpose', ari.business_purpose,
                                'reason_for_purchase', ari.reason,
                                'business_impact', ari.business_impact,
                                'po_id', poi.po_id,
                                'procurement_finalized_item', poi.procurement_finalized_item,
                                'procurement_attempt_request_item_id', poi.procurement_attempt_request_item_id,
                                'requisition_id', poi.requisition_id,
                                'requisition_number', ar.requisition_id,
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
                                'isactive', poi.isactive,
                                'procurement_id', pfi.procurement_id,
                                'procurement_number', p.request_id,
                                'created_at', poi.created_at,
                                'updated_at', poi.updated_at
                            )
                        )
                        FROM purchasing_order_items poi
                        LEFT JOIN procurement_finalize_items pfi ON pfi.id = poi.procurement_finalized_item
                        LEFT JOIN procurements p ON p.id = pfi.procurement_id
                        LEFT JOIN asset_requisitions_items ari ON ari.id = pfi.asset_requisitions_item_id
                        LEFT JOIN asset_requisitions ar ON ar.id = ari.asset_requisition_id
                        LEFT JOIN procurement_attempt_request_items pari ON pari.id = pfi.finalize_items_id
                        WHERE poi.po_id = po_id_found
                        AND poi.deleted_at IS NULL
                    ),
                    '[]'::JSONB
                ) AS po_items;

        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_purchase_order_details_for_customer(BIGINT, VARCHAR(255), VARCHAR(255));");
    }
};
