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
                WHERE proname = 'purchasing_order_supplier_respond'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION purchasing_order_supplier_respond(
            IN p_po_id BIGINT,
            IN p_supplier_id BIGINT,
            IN p_items JSONB,
            IN p_supplier_comment TEXT DEFAULT NULL,
            IN p_supplier_res_status TEXT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_item JSONB;
            v_updated_count INT := 0;
        BEGIN
            -- Validate required fields
            IF p_po_id IS NULL OR p_supplier_id IS NULL THEN
                RETURN QUERY SELECT 'ERROR', 'PO ID and Supplier ID are required';
                RETURN;
            END IF;

            -- Update purchasing_order_items for each item in p_items
            IF p_items IS NOT NULL THEN
                FOR v_item IN SELECT * FROM jsonb_array_elements(p_items)
                LOOP
                    UPDATE purchasing_order_items SET
                        available_quantity = (v_item->>'available_quantity')::NUMERIC(14,2),
                        can_supply = (v_item->>'can_supply')::BOOLEAN,
                        responded_supplier_id = p_supplier_id,
                        response_message = v_item->>'response_message',
                        updated_at = NOW()
                    WHERE po_id = p_po_id
                        AND procurement_finalized_item = (v_item->>'item_id')::BIGINT
                        AND deleted_at IS NULL;
                    v_updated_count := v_updated_count + 1;
                END LOOP;
            END IF;

            -- Update purchasing_orders with supplier_comment and supplier_res_status
            UPDATE purchasing_orders SET
                supplier_comment = p_supplier_comment,
                supplier_res_status = p_supplier_res_status,
                updated_at = NOW()
            WHERE id = p_po_id
                AND deleted_at IS NULL;

            RETURN QUERY SELECT 'SUCCESS', 'Supplier response processed. Items updated: ' || v_updated_count;
        EXCEPTION
            WHEN OTHERS THEN
                RETURN QUERY SELECT 'ERROR', 'Database error: ' || SQLERRM;
        END;
        $$;

        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         DB::unprepared('DROP FUNCTION IF EXISTS purchasing_order_supplier_respond(BIGINT, BIGINT, JSONB, TEXT, TEXT);');
    }
};
