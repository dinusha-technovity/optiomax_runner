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
            CREATE OR REPLACE FUNCTION get_item_master_details_list(
                p_tenant_id BIGINT,
                p_item_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                item_id TEXT,
                item_name TEXT,
                item_description TEXT,
                purchase_price NUMERIC,
                selling_price NUMERIC,
                image_links JSONB,
                unit_of_measure TEXT,
                category_name TEXT
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Invalid tenant ID provided'::TEXT,
                        NULL, NULL, NULL, NULL, NULL, NULL, NULL;
                    RETURN;
                END IF;

                IF p_item_id IS NOT NULL AND p_item_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Invalid item ID provided'::TEXT,
                        NULL, NULL, NULL, NULL, NULL, NULL, NULL;
                    RETURN;
                END IF;

                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT,
                    'Items retrieved successfully'::TEXT,
                    i.id,
                    i.item_id::TEXT,
                    i.item_name::TEXT,
                    i.item_description::TEXT,
                    i.purchase_price::NUMERIC, 
                    i.selling_price::NUMERIC,
                    i.image_links::JSONB,
                    m.name::TEXT AS unit_of_measure,
                    ac.name::TEXT AS category_name
                FROM items i
                JOIN measurements m ON m.id = i.unit_of_measure_id
                JOIN asset_categories ac ON ac.id = i.category_id
                WHERE i.tenant_id = p_tenant_id
                AND (p_item_id IS NULL OR i.id = p_item_id)
                AND i.deleted_at IS NULL
                AND i.isactive = TRUE;
            END;
            $$
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       DB::unprepared('DROP FUNCTION IF EXISTS get_item_master_details_list( BIGINT, BIGINT);');
    }
};