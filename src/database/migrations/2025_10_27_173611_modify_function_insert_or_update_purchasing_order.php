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
                WHERE proname = 'insert_or_update_purchasing_order'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;


        CREATE OR REPLACE FUNCTION insert_or_update_purchasing_order(
            IN p_id BIGINT DEFAULT NULL,
            IN p_supplier_id BIGINT DEFAULT NULL,
            IN p_submit_officer_comment TEXT DEFAULT NULL,
            IN p_due_date TIMESTAMPTZ DEFAULT NULL,
            IN p_total_amount NUMERIC(18,2) DEFAULT 0,
            IN p_status TEXT DEFAULT 'Pending',
            IN p_tenant_id BIGINT DEFAULT NULL,
            IN p_created_by BIGINT DEFAULT NULL,
            IN p_updated_by BIGINT DEFAULT NULL,
            IN p_selected_requisition_items JSONB DEFAULT NULL,
            IN p_overall_tax_id BIGINT DEFAULT NULL,
            IN p_overall_tax_rate NUMERIC(10,4) DEFAULT 0,
            IN p_overall_discount_percentage NUMERIC(5,2) DEFAULT 0,
            IN p_current_time TIMESTAMPTZ DEFAULT now()
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            po_id BIGINT,
            po_number TEXT,
            supplier_data JSONB
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_po_id BIGINT;
            v_po_number TEXT;
            v_exists BOOLEAN := FALSE;
            v_action_type TEXT;
            v_po_data JSONB;
            v_log_success BOOLEAN := FALSE;
            v_error_message TEXT;
            v_year TEXT;
            v_item JSONB;
            v_finalized_item_id BIGINT;
            v_procurement_attempt_request_item_id BIGINT;
            v_organization_id BIGINT;
            v_asset_requisition_item_id BIGINT;
            v_item_name TEXT;
            v_supplier_name TEXT;
            v_supplier_email TEXT;
            v_supplier_data JSONB;
        BEGIN
            -- Validate required fields
            IF p_supplier_id IS NULL THEN
                RETURN QUERY SELECT 
                    'ERROR'::TEXT,
                    'Supplier ID is required'::TEXT,
                    NULL::BIGINT,
                    NULL::TEXT,
                    NULL::JSONB;
                RETURN;
            END IF;

            IF p_tenant_id IS NULL THEN
                RETURN QUERY SELECT 
                    'ERROR'::TEXT,
                    'Tenant ID is required'::TEXT,
                    NULL::BIGINT,
                    NULL::TEXT,
                    NULL::JSONB;
                RETURN;
            END IF;

            IF p_created_by IS NULL THEN
                RETURN QUERY SELECT 
                    'ERROR'::TEXT,
                    'Created by user ID is required'::TEXT,
                    NULL::BIGINT,
                    NULL::TEXT,
                    NULL::JSONB;
                RETURN;
            END IF;

            -- Validate status
            IF p_status NOT IN ('Draft', 'Pending', 'Supplier_respond_complete', 'Awaiting_Approval', 
                                'Approved', 'Rejected', 'Cancelled', 'Released') THEN
                RETURN QUERY SELECT 
                    'ERROR'::TEXT,
                    'Invalid status. Must be one of: Draft, Pending, Supplier_respond_complete, Awaiting_Approval, Approved, Rejected, Cancelled, Released'::TEXT,
                    NULL::BIGINT,
                    NULL::TEXT,
                    NULL::JSONB;
                RETURN;
            END IF;

            -- Validate total amount
            IF p_total_amount < 0 THEN
                RETURN QUERY SELECT 
                    'ERROR'::TEXT,
                    'Total amount cannot be negative'::TEXT,
                    NULL::BIGINT,
                    NULL::TEXT,
                    NULL::JSONB;
                RETURN;
            END IF;

            -- Check if updating existing purchasing order
            IF p_id IS NOT NULL AND p_id > 0 THEN
                SELECT EXISTS (
                    SELECT 1 FROM purchasing_orders 
                    WHERE id = p_id 
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL
                ) INTO v_exists;
                
                IF NOT v_exists THEN
                    RETURN QUERY SELECT 
                        'ERROR'::TEXT,
                        'Purchasing order not found or access denied'::TEXT,
                        NULL::BIGINT,
                        NULL::TEXT,
                        NULL::JSONB;
                    RETURN;
                END IF;
            END IF;

            -- Update existing purchasing order
            IF v_exists THEN
                -- Get existing PO number
                SELECT po_number INTO v_po_number
                FROM purchasing_orders
                WHERE id = p_id;

                -- Update purchasing order
                UPDATE purchasing_orders SET
                    supplier_id = p_supplier_id,
                    submit_officer_comment = p_submit_officer_comment,
                    due_date = p_due_date,
                    total_amount = p_total_amount,
                    status = p_status,
                    updated_by = p_updated_by,
                    updated_at = p_current_time,
                    overall_tax_id = p_overall_tax_id,
                    overall_tax_rate = COALESCE(p_overall_tax_rate, 0), 
                    overall_discount_percentage = p_overall_discount_percentage
                WHERE id = p_id
                RETURNING id INTO v_po_id;

                -- Delete existing items for this PO
                DELETE FROM purchasing_order_items
                WHERE po_id = v_po_id
                AND deleted_at IS NULL;

                -- Insert updated items
                IF p_selected_requisition_items IS NOT NULL THEN
                    FOR v_item IN SELECT * FROM jsonb_array_elements(p_selected_requisition_items)
                    LOOP
                        -- Get finalized_item_id, procurement_attempt_request_item_id, and asset_requisitions_item_id directly
                        SELECT 
                            pfi.id,
                            pfi.finalize_items_id,
                            pfi.asset_requisitions_item_id
                        INTO 
                            v_finalized_item_id,
                            v_procurement_attempt_request_item_id,
                            v_asset_requisition_item_id
                        FROM procurement_finalize_items pfi
                        WHERE pfi.id = (v_item->>'item_id')::BIGINT
                        AND pfi.deleted_at IS NULL;

                        -- Get organization_id directly from asset_requisitions_items
                        IF v_asset_requisition_item_id IS NOT NULL THEN
                            SELECT 
                                ari.organization
                            INTO 
                                v_organization_id
                            FROM asset_requisitions_items ari
                            WHERE ari.id = v_asset_requisition_item_id
                            AND ari.deleted_at IS NULL;
                        END IF;

                        -- Insert purchasing order item
                        INSERT INTO purchasing_order_items (
                            po_id,
                            procurement_finalized_item,
                            procurement_attempt_request_item_id,
                            requisition_id,
                            quantity,
                            unit_price,
                            total_price,
                            remarks,
                            organization_id,
                            responded_supplier_id,
                            approved_quantity,
                            created_at,
                            updated_at,
                            tax_id,
                            tax_rate,
                            discount_percentage
                        ) VALUES (
                            v_po_id,
                            v_finalized_item_id,
                            v_procurement_attempt_request_item_id,
                            (v_item->>'p_requisition_id')::BIGINT,
                            (v_item->>'quantity')::NUMERIC(14,2),
                            (v_item->>'p_unit_price')::NUMERIC(18,2),
                            (v_item->>'total_price')::NUMERIC(18,2),
                            v_item->>'remark',
                            v_organization_id,
                            p_supplier_id,
                            (v_item->>'quantity')::NUMERIC(14,2),
                            p_current_time,
                            p_current_time,
                            (v_item->>'tax_id')::BIGINT,
                            COALESCE(v_item->>'tax_rate',0)::NUMERIC(10,4),
                            COALESCE(v_item->>'discount_percentage',0)::NUMERIC(5,2)
                        );

                    -- update finalized item is_po_submit and pending_purchasing_qty
                        UPDATE procurement_finalize_items pfi
                        SET is_po_submit = true,
                            pending_purchasing_qty = pfi.pending_purchasing_qty - (v_item->>'quantity')::INTEGER,
                            updated_at = p_current_time
                        WHERE id = (v_item->>'item_id')::BIGINT
                        AND deleted_at IS NULL;

                        -- Reset variables for next iteration
                        v_finalized_item_id := NULL;
                        v_procurement_attempt_request_item_id := NULL;
                        v_organization_id := NULL;
                        v_asset_requisition_item_id := NULL;
                    
                    END LOOP;
                END IF;

                v_action_type := 'updated';
                
                -- Prepare PO data for logging
                v_po_data := jsonb_build_object(
                    'po_id', v_po_id,
                    'po_number', v_po_number,
                    'supplier_id', p_supplier_id,
                    'total_amount', p_total_amount,
                    'status', p_status,
                    'action', 'update'
                );

                -- Fetch supplier data for return
                SELECT s.name, s.email
                INTO v_supplier_name, v_supplier_email
                FROM suppliers s
                WHERE s.id = p_supplier_id
                AND s.deleted_at IS NULL
                LIMIT 1;

                v_supplier_data := jsonb_build_object(
                    'supplier_name', v_supplier_name,
                    'supplier_email', v_supplier_email
                );

                -- Log the activity (with error handling)
                BEGIN
                    PERFORM log_activity(
                        'purchasing_order.' || v_action_type,
                        'Purchasing Order ' || v_action_type || ': ' || v_po_number,
                        'purchasing_order',
                        v_po_id,
                        'user',
                        p_updated_by,
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
                    'Purchasing order updated successfully'::TEXT,
                    v_po_id,
                    v_po_number,
                    v_supplier_data;

            -- Create new purchasing order
            ELSE
                -- Extract year from current time
                v_year := EXTRACT(YEAR FROM p_current_time)::TEXT;
                
                -- Generate PO number
                v_po_number := generate_po_number(v_year);

                -- Insert new purchasing order
                INSERT INTO purchasing_orders (
                    po_number,
                    supplier_id,
                    submit_officer_comment,
                    due_date,
                    total_amount,
                    status,
                    tenant_id,
                    created_by,
                    isactive,
                    created_at,
                    updated_at,
                    overall_tax_id,
                    overall_tax_rate,
                    overall_discount_percentage
                ) VALUES (
                    v_po_number,
                    p_supplier_id,
                    p_submit_officer_comment,
                    p_due_date,
                    p_total_amount,
                    'Pending', -- Always set to Pending on creation
                    p_tenant_id,
                    p_created_by,
                    true,
                    p_current_time,
                    p_current_time,
                    p_overall_tax_id,
                    COALESCE(p_overall_tax_rate, 0), 
                COALESCE(p_overall_discount_percentage, 0) 
                ) RETURNING id INTO v_po_id;

                -- Insert purchasing order items
                IF p_selected_requisition_items IS NOT NULL THEN
                    FOR v_item IN SELECT * FROM jsonb_array_elements(p_selected_requisition_items)
                    LOOP
                        -- Get finalized_item_id, procurement_attempt_request_item_id, and asset_requisitions_item_id directly
                        SELECT 
                            pfi.id,
                            pfi.finalize_items_id,
                            pfi.asset_requisitions_item_id
                        INTO 
                            v_finalized_item_id,
                            v_procurement_attempt_request_item_id,
                            v_asset_requisition_item_id
                        FROM procurement_finalize_items pfi
                        WHERE pfi.id = (v_item->>'item_id')::BIGINT
                        AND pfi.deleted_at IS NULL;

                        -- Get organization_id directly from asset_requisitions_items
                        IF v_asset_requisition_item_id IS NOT NULL THEN
                            SELECT 
                                ari.organization
                            INTO 
                                v_organization_id
                            FROM asset_requisitions_items ari
                            WHERE ari.id = v_asset_requisition_item_id
                            AND ari.deleted_at IS NULL;
                        END IF;

                        -- Insert purchasing order item
                        INSERT INTO purchasing_order_items (
                            po_id,
                            procurement_finalized_item,
                            procurement_attempt_request_item_id,
                            requisition_id,
                            quantity,
                            unit_price,
                            total_price,
                            remarks,
                            organization_id,
                            responded_supplier_id,
                            approved_quantity,
                            created_at,
                            updated_at,
                            tax_id,
                            tax_rate,
                            discount_percentage
                        ) VALUES (
                            v_po_id,
                            v_finalized_item_id,
                            v_procurement_attempt_request_item_id,
                            (v_item->>'p_requisition_id')::BIGINT,
                            (v_item->>'quantity')::NUMERIC(14,2),
                            (v_item->>'p_unit_price')::NUMERIC(18,2),
                            (v_item->>'total_price')::NUMERIC(18,2),
                            v_item->>'remark',
                            v_organization_id,
                            p_supplier_id,
                            (v_item->>'available_quantity')::NUMERIC(14,2),
                            p_current_time,
                            p_current_time,
                            (v_item->>'tax_id')::BIGINT,
                            COALESCE((v_item->>'tax_rate')::NUMERIC(10,4), 0),
                            COALESCE((v_item->>'discount_percentage')::NUMERIC(5,2), 0)
                        );

                        --update finalized item is_po_submit and pending_purchasing_qty
                        UPDATE procurement_finalize_items pfi
                        SET is_po_submit = true,
                            pending_purchasing_qty = pfi.pending_purchasing_qty - (v_item->>'quantity')::INTEGER,
                            updated_at = p_current_time
                        WHERE id = (v_item->>'item_id')::BIGINT
                        AND deleted_at IS NULL;

                        -- Reset variables for next iteration
                        v_finalized_item_id := NULL;
                        v_procurement_attempt_request_item_id := NULL;
                        v_organization_id := NULL;
                        v_asset_requisition_item_id := NULL;
                        v_item_name := NULL;

                    END LOOP;
                END IF;

                v_action_type := 'created';
                
                -- Prepare PO data for logging
                v_po_data := jsonb_build_object(
                    'po_id', v_po_id,
                    'po_number', v_po_number,
                    'supplier_id', p_supplier_id,
                    'total_amount', p_total_amount,
                    'status', 'Pending',
                    'action', 'create'
                );

                -- Fetch supplier data
                SELECT 
                    s.name,
                    s.email
                INTO 
                    v_supplier_name,
                    v_supplier_email
                FROM suppliers s
                WHERE s.id = p_supplier_id
                AND s.deleted_at IS NULL
                LIMIT 1;

                -- Build supplier data JSON
                v_supplier_data := jsonb_build_object(
                    'supplier_name', v_supplier_name,
                    'supplier_email', v_supplier_email
                );

                -- Log the activity (with error handling)
                BEGIN
                    PERFORM log_activity(
                        'purchasing_order.' || v_action_type,
                        'Purchasing Order ' || v_action_type || ': ' || v_po_number,
                        'purchasing_order',
                        v_po_id,
                        'user',
                        p_created_by,
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
                    'Purchasing order created successfully'::TEXT,
                    v_po_id,
                    v_po_number,
                    v_supplier_data;
            END IF;

        EXCEPTION
            WHEN OTHERS THEN
                RETURN QUERY SELECT 
                    'ERROR'::TEXT,
                    ('Database error: ' || SQLERRM)::TEXT,
                    NULL::BIGINT,
                    NULL::TEXT,
                    NULL::JSONB;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(<<<SQL
        DO $$
        DECLARE
            r RECORD;
        BEGIN
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'insert_or_update_purchasing_order'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        SQL);
    }
};
