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
                WHERE proname = 'submit_purchasing_order_supplier_feedback'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION submit_purchasing_order_supplier_feedback(
            IN p_po_id BIGINT,
            IN p_supplier_id BIGINT,
            IN p_overall_comment TEXT DEFAULT NULL,
            IN p_selected_items JSONB DEFAULT NULL,
            IN p_suppliers_email TEXT DEFAULT NULL,
            IN p_tenant_id BIGINT DEFAULT NULL,
            IN p_current_time TIMESTAMPTZ DEFAULT now()
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            purch_order_id BIGINT,
            updated_items_count INTEGER
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_po_exists BOOLEAN := FALSE;
            v_supplier_exists BOOLEAN := FALSE;
            v_item JSONB;
            v_po_item_id BIGINT;
            v_procurement_finalized_item_id BIGINT;
            v_supplier_response TEXT;
            v_response_message TEXT;
           
            v_item_respond_status TEXT;
            v_updated_items_count INTEGER := 0;
            v_error_message TEXT;
            v_po_data JSONB;
            v_log_success BOOLEAN := FALSE;
            v_can_supply BOOLEAN;
            v_item_availability_status TEXT;
            v_purchase_order_number TEXT;
        BEGIN
            -- Validate required fields
            IF p_po_id IS NULL THEN
                RETURN QUERY SELECT 
                    'ERROR'::TEXT,
                    'Purchase Order ID is required'::TEXT,
                    NULL::BIGINT,
                    NULL::INTEGER;
                RETURN;
            END IF;

            IF p_supplier_id IS NULL THEN
                RETURN QUERY SELECT 
                    'ERROR'::TEXT,
                    'Supplier ID is required'::TEXT,
                    NULL::BIGINT,
                    NULL::INTEGER;
                RETURN;
            END IF;


            -- Check if purchasing order exists
            SELECT EXISTS (
                SELECT 1 FROM purchasing_orders 
                WHERE id = p_po_id 
                AND supplier_id = p_supplier_id
                AND (p_tenant_id IS NULL OR tenant_id = p_tenant_id)
                AND deleted_at IS NULL
            ) INTO v_po_exists;
            
            IF NOT v_po_exists THEN
                RETURN QUERY SELECT 
                    'ERROR'::TEXT,
                    'Purchase order not found or access denied'::TEXT,
                    NULL::BIGINT,
                    NULL::INTEGER;
                RETURN;
            END IF;

            -- Check if supplier exists
            SELECT EXISTS (
                SELECT 1 FROM suppliers 
                WHERE id = p_supplier_id 
                AND (p_suppliers_email IS NULL OR email = p_suppliers_email)
                AND deleted_at IS NULL
            ) INTO v_supplier_exists;
            
            IF NOT v_supplier_exists THEN
                RETURN QUERY SELECT 
                    'ERROR'::TEXT,
                    'Supplier not found or email mismatch'::TEXT,
                    NULL::BIGINT,
                    NULL::INTEGER;
                RETURN;
            END IF;

            -- Update purchasing order with supplier response
            UPDATE purchasing_orders SET
                supplier_res_status = 'accept',
                supplier_comment = p_overall_comment,
                status = 'Supplier_respond_complete',
                updated_at = p_current_time
            WHERE id = p_po_id;

            -- Process selected items if provided
            IF p_selected_items IS NOT NULL THEN
                FOR v_item IN SELECT * FROM jsonb_array_elements(p_selected_items)
                LOOP
                    -- Extract item data
                    v_po_item_id := (v_item->>'item_id')::BIGINT;
                    v_procurement_finalized_item_id := (v_item->>'procurement_finalized_item_id')::BIGINT;
                    v_response_message := v_item->>'response_message';
                    
                    -- Set can_supply and item_availability_status based on response
                    v_can_supply := CASE 
                        WHEN v_supplier_response = 'accept' THEN TRUE
                        ELSE FALSE
                    END;

                    -- Update purchasing order item
                    UPDATE purchasing_order_items SET
                        can_supply = true,
                      
                        respond_date = p_current_time,
                        response_message = v_response_message,
                        item_availability_status = 'FULLY',
                        updated_at = p_current_time
                    WHERE purchasing_order_items.id = v_po_item_id 
                    AND purchasing_order_items.po_id = p_po_id
                    AND purchasing_order_items.procurement_finalized_item = v_procurement_finalized_item_id;

                    -- Check if update was successful
                    IF FOUND THEN
                        v_updated_items_count := v_updated_items_count + 1;
                    END IF;

                END LOOP;
            END IF;

            v_purchase_order_number := (SELECT po_number FROM purchasing_orders WHERE id = p_po_id);

            -- Update purchasing_order_supplier_request table
            UPDATE purchasing_order_supplier_request SET
                request_status = 'accepted',
                isactive = false,
                updated_at = p_current_time
            WHERE purchasing_order_number = v_purchase_order_number
            AND email = p_suppliers_email
            AND (p_tenant_id IS NULL OR tenant_id = p_tenant_id);

            -- Prepare data for logging
            v_po_data := jsonb_build_object(
                'purch_order_id', p_po_id,
                'supplier_id', p_supplier_id,
                'overall_response', 'accept',
                'overall_comment', p_overall_comment,
                'updated_items_count', v_updated_items_count,
                'action', 'supplier_feedback_submitted'
            );

            -- Log the activity (with error handling)
            BEGIN
                PERFORM log_activity(
                    'purchasing_order.supplier_feedback_submitted',
                    'Supplier feedback submitted for Purchase Order ID: ' || p_po_id,
                    'purchasing_order',
                    p_po_id,
                    'supplier',
                    p_supplier_id,
                    v_po_data,
                    p_tenant_id
                );
                v_log_success := TRUE;
            EXCEPTION WHEN OTHERS THEN
                v_log_success := FALSE;
                v_error_message := 'Logging failed: ' || SQLERRM;
            END;

            RETURN QUERY SELECT 
                'SUCCESS'::TEXT,
                ('Supplier feedback submitted successfully. Updated ' || v_updated_items_count || ' items.')::TEXT,
                p_po_id,
                v_updated_items_count;

        EXCEPTION
            WHEN OTHERS THEN
                RETURN QUERY SELECT 
                    'ERROR'::TEXT,
                    ('Database error: ' || SQLERRM)::TEXT,
                    NULL::BIGINT,
                    NULL::INTEGER;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS submit_purchasing_order_supplier_feedback(BIGINT, BIGINT, TEXT, TEXT, JSONB, TEXT, BIGINT, TIMESTAMPTZ);');
    }
};
