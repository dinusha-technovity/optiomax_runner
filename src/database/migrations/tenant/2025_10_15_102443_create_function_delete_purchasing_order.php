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
                WHERE proname = 'delete_purchasing_order'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION delete_purchasing_order(
            p_tenant_id BIGINT,
            p_purchasing_order_id BIGINT,
            p_deleted_by BIGINT
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_deleted_count INT := 0;
            v_items_deleted_count INT := 0;
            v_log_success BOOLEAN := FALSE;
            v_error_message TEXT;
            v_log_data JSONB;
            v_po_number VARCHAR(50);
            v_supplier_id BIGINT;
        BEGIN
            -- Validate tenant ID
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT AS status,
                    'Invalid tenant ID provided'::TEXT AS message;
                RETURN;
            END IF;

            -- Validate purchasing order ID
            IF p_purchasing_order_id IS NULL OR p_purchasing_order_id <= 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT AS status,
                    'Invalid purchasing order ID provided'::TEXT AS message;
                RETURN;
            END IF;

            -- Validate deleted_by user ID
            IF p_deleted_by IS NULL OR p_deleted_by <= 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT AS status,
                    'Invalid deleted_by user ID provided'::TEXT AS message;
                RETURN;
            END IF;

            -- Check if purchasing order exists and get its details
            SELECT po.po_number, po.supplier_id INTO v_po_number, v_supplier_id
            FROM purchasing_orders po
            WHERE po.id = p_purchasing_order_id
            AND po.tenant_id = p_tenant_id
            AND po.deleted_at IS NULL 
            AND po.isactive = TRUE;

            IF v_po_number IS NULL THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT AS status,
                    'Purchasing order not found or already deleted'::TEXT AS message;
                RETURN;
            END IF;

            -- Soft delete purchasing order items
            UPDATE purchasing_order_items
            SET 
                isactive = FALSE,
                deleted_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE po_id = p_purchasing_order_id
            AND deleted_at IS NULL
            AND isactive = TRUE;

            GET DIAGNOSTICS v_items_deleted_count = ROW_COUNT;

            -- Soft delete purchasing order
            UPDATE purchasing_orders
            SET 
                isactive = FALSE,
                deleted_at = CURRENT_TIMESTAMP,
                updated_by = p_deleted_by,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = p_purchasing_order_id
            AND tenant_id = p_tenant_id
            AND deleted_at IS NULL
            AND isactive = TRUE;

            GET DIAGNOSTICS v_deleted_count = ROW_COUNT;

            -- Check if deletion was successful
            IF v_deleted_count > 0 THEN
                -- Prepare data for logging
                v_log_data := jsonb_build_object(
                    'purchasing_order_id', p_purchasing_order_id,
                    'po_number', v_po_number,
                    'supplier_id', v_supplier_id,
                    'deleted_by', p_deleted_by,
                    'items_deleted_count', v_items_deleted_count,
                    'action', 'purchasing_order_deleted'
                );

                -- Log the activity (with error handling)
                BEGIN
                    PERFORM log_activity(
                        'purchasing_order.deleted',
                        'Purchasing order ' || v_po_number || ' (ID: ' || p_purchasing_order_id || ') deleted successfully',
                        'purchasing_order',
                        p_purchasing_order_id,
                        'user',
                        p_deleted_by,
                        v_log_data,
                        p_tenant_id
                    );
                    v_log_success := TRUE;
                EXCEPTION WHEN OTHERS THEN
                    v_log_success := FALSE;
                    v_error_message := 'Logging failed: ' || SQLERRM;
                END;

                RETURN QUERY SELECT 
                    'SUCCESS'::TEXT AS status,
                    format('Purchasing order deleted successfully. Items deleted: %s', v_items_deleted_count)::TEXT AS message;
            ELSE
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT AS status,
                    'Failed to delete purchasing order'::TEXT AS message;
            END IF;

        EXCEPTION
            WHEN OTHERS THEN
                RETURN QUERY SELECT 
                    'ERROR'::TEXT AS status,
                    ('Database error: ' || SQLERRM)::TEXT AS message;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         DB::unprepared('DROP FUNCTION IF EXISTS delete_purchasing_order(BIGINT, BIGINT, BIGINT);');
    }
};
