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
                -- Drop all versions of the function before recreating
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_finalized_asset_details_data'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION get_finalized_asset_details_data(
            
                p_finalize_id BIGINT,
                p_tenant_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                flz_item_id BIGINT,
                finalize_items_id BIGINT,
                procurement_id BIGINT,
                asset_requisition_id BIGINT,
                asset_name VARCHAR,
                requested_quantity INTEGER,
                available_quantity NUMERIC,
                available_date DATE,
                normal_price_per_item NUMERIC,
                with_tax_price_per_item NUMERIC,
                delivery_cost NUMERIC,
                total_cost NUMERIC,
                can_full_fill_requested_quantity BOOLEAN,
                message_from_supplier TEXT,
                supplier_terms_and_conditions JSONB,
                supplier_attachment JSONB,
                procurement_number VARCHAR,
                requisition_number VARCHAR,
                supplier JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_record_count INTEGER := 0;
            BEGIN

                --  Count matching records for the given finalize item (only where available quantity >= 1)
                SELECT COUNT(*)
                INTO v_record_count
                FROM procurement_finalize_items pfi
                WHERE pfi.id = p_finalize_id
                AND pfi.deleted_at IS NULL;
            

                --  If no records found
                IF v_record_count = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'No asset items found for the given finalize record'::TEXT AS message,
                        NULL::BIGINT AS flz_item_id,
                        NULL::BIGINT AS finalize_items_id,
                        NULL::BIGINT AS procurement_id,
                        NULL::BIGINT AS asset_requisition_id,
                        NULL::VARCHAR AS asset_name,
                        NULL::INTEGER AS requested_quantity,
                        NULL::NUMERIC AS available_quantity,
                        NULL::DATE AS available_date,
                        NULL::NUMERIC AS normal_price_per_item,
                        NULL::NUMERIC AS with_tax_price_per_item,
                        NULL::NUMERIC AS delivery_cost,
                        NULL::NUMERIC AS total_cost,
                        NULL::BOOLEAN AS can_full_fill_requested_quantity,
                        NULL::TEXT AS message_from_supplier,
                        NULL::JSONB AS supplier_terms_and_conditions,
                        NULL::JSONB AS supplier_attachment,
                        NULL::VARCHAR AS procurement_number,
                        NULL::VARCHAR AS requisition_number,
                        NULL::JSONB AS supplier;
                    RETURN;
                END IF;

                -- Main data query (drill down via finalize_items_id -> procurement_attempt_request_items)
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Asset items fetched successfully'::TEXT AS message,
                    pfi.id AS flz_item_id,
                    pfi.finalize_items_id,
                    pari.procurement_id,
                    ari.asset_requisition_id,
                    ari.item_name AS asset_name,
                    pfi.finalized_qty AS requested_quantity,
                    pari.available_quantity AS available_quantity,
                    pari.available_date,
                    pari.normal_price_per_item,
                    pari.with_tax_price_per_item,
                    pari.delivery_cost,
                    ROUND(((pari.requested_quantity * pari.with_tax_price_per_item) + pari.delivery_cost), 2) AS total_cost,
                    pari.can_full_fill_requested_quantity,
                    pari.message_from_supplier,
                    pari.supplier_terms_and_conditions,
                    pari.supplier_attachment,
                    p.request_id AS procurement_number,
                    ar.requisition_id AS requisition_number,
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
                    ON pari.procurement_id = p.id
                LEFT JOIN asset_requisitions ar
                    ON ari.asset_requisition_id = ar.id
                WHERE pfi.id = p_finalize_id
                AND (p_tenant_id IS NULL OR s.tenant_id = p_tenant_id)
                AND pfi.deleted_at IS NULL;

            EXCEPTION
                WHEN OTHERS THEN
                    RETURN QUERY SELECT 
                        'ERROR'::TEXT AS status,
                        ('Database error: ' || SQLERRM)::TEXT AS message,
                        NULL::BIGINT AS flz_item_id,
                        NULL::BIGINT AS finalize_items_id,
                        NULL::BIGINT AS procurement_id,
                        NULL::BIGINT AS asset_requisition_id,
                        NULL::VARCHAR AS asset_name,
                        NULL::INTEGER AS requested_quantity,
                        NULL::NUMERIC AS available_quantity,
                        NULL::DATE AS available_date,
                        NULL::NUMERIC AS normal_price_per_item,
                        NULL::NUMERIC AS with_tax_price_per_item,
                        NULL::NUMERIC AS delivery_cost,
                        NULL::NUMERIC AS total_cost,
                        NULL::BOOLEAN AS can_full_fill_requested_quantity,
                        NULL::TEXT AS message_from_supplier,
                        NULL::JSONB AS supplier_terms_and_conditions,
                        NULL::JSONB AS supplier_attachment,
                        NULL::VARCHAR AS procurement_number,
                        NULL::VARCHAR AS requisition_number,
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
        DB::unprepared(<<<SQL
        DO $$
        DECLARE
            r RECORD;
        BEGIN
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'get_finalized_asset_details_data'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;
        SQL);
    }
};
