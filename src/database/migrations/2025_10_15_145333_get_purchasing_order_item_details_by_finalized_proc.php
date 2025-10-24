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
        //purchase_order -> Procurement_finalize_items->asset_requisition_item_id->asset_requisitions_items

        DB::unprepared(<<<SQL
        DO $$
        DECLARE
            r RECORD;
        BEGIN
            -- Drop all versions of the function before recreating
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'get_supplier_finalized_asset_items'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_supplier_finalized_asset_items(
            p_supplier_id BIGINT,
            p_tenant_id BIGINT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            flz_item_id BIGINT,
            procurement_id BIGINT,
            asset_requisition_id BIGINT,
            asset_name VARCHAR,
            requested_quantity INTEGER,
            available_quantity INTEGER,
            with_tax_price_per_item NUMERIC,
            delivery_cost NUMERIC,
            total_cost NUMERIC,
            procurement_code VARCHAR,
            asset_requisition_code VARCHAR,
            supplier JSONB
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_record_count INTEGER := 0;
        BEGIN
            --  Validate supplier ID
            IF p_supplier_id IS NULL OR p_supplier_id <= 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT AS status,
                    'Invalid supplier ID provided'::TEXT AS message,
                    NULL::BIGINT AS flz_item_id,
                    NULL::BIGINT AS procurement_id,
                    NULL::BIGINT AS asset_requisition_id,
                    NULL::VARCHAR AS asset_name,
                    NULL::INTEGER AS requested_quantity,
                    NULL::INTEGER AS available_quantity,
                    NULL::NUMERIC AS with_tax_price_per_item,
                    NULL::NUMERIC AS delivery_cost,
                    NULL::NUMERIC AS total_cost,
                    NULL::VARCHAR AS procurement_code,
                    NULL::VARCHAR AS asset_requisition_code,
                    NULL::JSONB AS supplier;
                RETURN;
            END IF;

            --  Count matching records (only where available quantity >= 1)
            SELECT COUNT(*)
            INTO v_record_count
            FROM procurement_finalize_items pfi
            WHERE pfi.supplier_id = p_supplier_id
            AND pfi.deleted_at IS NULL
            AND pfi.pending_purchasing_qty >= 1;  -- <-- updated filter

            --  If no records found
            IF v_record_count = 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT AS status,
                    'No asset items found for the given supplier'::TEXT AS message,
                    NULL::BIGINT AS flz_item_id,
                    NULL::BIGINT AS procurement_id,
                    NULL::BIGINT AS asset_requisition_id,
                    NULL::VARCHAR AS asset_name,
                    NULL::INTEGER AS requested_quantity,
                    NULL::INTEGER AS available_quantity,
                    NULL::NUMERIC AS with_tax_price_per_item,
                    NULL::NUMERIC AS delivery_cost,
                    NULL::NUMERIC AS total_cost,
                    NULL::VARCHAR AS procurement_code,
                    NULL::VARCHAR AS asset_requisition_code,
                    NULL::JSONB AS supplier;
                RETURN;
            END IF;

            -- Main data query (filter out items with available_quantity < 1)
            RETURN QUERY
            SELECT
                'SUCCESS'::TEXT AS status,
                'Supplier asset items fetched successfully'::TEXT AS message,
                pfi.id AS flz_item_id,
                pfi.procurement_id,
                ari.asset_requisition_id,
                ari.item_name AS asset_name,
                pfi.finalized_qty AS requested_quantity,
                pfi.pending_purchasing_qty AS available_quantity,
                pari.with_tax_price_per_item,
                pari.delivery_cost,
                ROUND(((pari.requested_quantity * pari.with_tax_price_per_item) + pari.delivery_cost), 2) AS total_cost,
                p.request_id AS procurement_code,
                ar.requisition_id AS asset_requisition_code,
                jsonb_build_object(
                    'id', s.id,
                    'name', s.name,
                    'email', s.email,
                    'contact_no', s.contact_no
                ) AS supplier
            FROM procurement_finalize_items pfi
            LEFT JOIN procurement_attempt_request_items pari 
                ON pfi.finalize_items_id = pari.id
            LEFT JOIN asset_requisitions_items ari 
                ON pfi.asset_requisitions_item_id = ari.id
            LEFT JOIN suppliers s 
                ON pfi.supplier_id = s.id
            LEFT JOIN procurements p
                ON pfi.procurement_id = p.id
            LEFT JOIN asset_requisitions ar
                ON ari.asset_requisition_id = ar.id
            WHERE pfi.supplier_id = p_supplier_id
            AND (p_tenant_id IS NULL OR s.tenant_id = p_tenant_id)
            AND pfi.pending_purchasing_qty >= 1              -- Only get items with quantity >= 1
            AND pfi.is_po_completed = FALSE
            AND pfi.deleted_at IS NULL;

        EXCEPTION
            WHEN OTHERS THEN
                RETURN QUERY SELECT 
                    'ERROR'::TEXT AS status,
                    ('Database error: ' || SQLERRM)::TEXT AS message,
                    NULL::BIGINT AS flz_item_id,
                    NULL::BIGINT AS procurement_id,
                    NULL::BIGINT AS asset_requisition_id,
                    NULL::VARCHAR AS asset_name,
                    NULL::INTEGER AS requested_quantity,
                    NULL::INTEGER AS available_quantity,
                    NULL::NUMERIC AS with_tax_price_per_item,
                    NULL::NUMERIC AS delivery_cost,
                    NULL::NUMERIC AS total_cost,
                    NULL::VARCHAR AS procurement_code,
                    NULL::VARCHAR AS asset_requisition_code,
                    NULL::JSONB AS supplier;
        END;
        $$;
        SQL);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
          DB::unprepared('DROP FUNCTION IF EXISTS get_supplier_finalized_asset_items(BIGINT, BIGINT, BIGINT);');
    }
};
