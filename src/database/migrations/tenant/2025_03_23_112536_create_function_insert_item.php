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
        DB::statement(' CREATE SEQUENCE IF NOT EXISTS item_id_seq START 1;'); 
        
        DB::unprepared(<<<SQL
        
            CREATE OR REPLACE FUNCTION insert_item(
            p_tenant_id BIGINT DEFAULT NULL,
            p_item_name TEXT DEFAULT NULL,
            p_item_description TEXT DEFAULT NULL,
            p_category_id BIGINT DEFAULT NULL,
            p_type_id BIGINT DEFAULT NULL,
            p_unit_of_measure_id BIGINT DEFAULT NULL,
            p_purchase_price NUMERIC(15,2) DEFAULT NULL,
            p_purchase_price_currency_id BIGINT DEFAULT NULL,
            p_selling_price NUMERIC(15,2) DEFAULT NULL,
            p_selling_price_currency_id BIGINT DEFAULT NULL,
            p_max_inventory_level INTEGER DEFAULT NULL,
            p_min_inventory_level INTEGER DEFAULT NULL,
            p_re_order_level INTEGER DEFAULT NULL,
            p_low_stock_alert BOOLEAN DEFAULT FALSE,
            p_over_stock_alert BOOLEAN DEFAULT FALSE,
            p_image_links JSONB DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            id BIGINT, 
            item_id TEXT,
            item_data JSON
        ) 
        LANGUAGE plpgsql 
        AS $$
        DECLARE 
            v_item_id TEXT;
            v_auto_id BIGINT;
            v_item_data JSON;
            v_item_record RECORD;
        BEGIN
            -- Insert the new item and retrieve the auto-incremented ID
            INSERT INTO items (
                item_name, item_description, tenant_id, category_id, type_id, unit_of_measure_id, 
                purchase_price, purchase_price_currency_id, selling_price, selling_price_currency_id, 
                max_inventory_level, min_inventory_level, re_order_level, 
                low_stock_alert, over_stock_alert, image_links, created_at, updated_at
            ) 
            VALUES (
                p_item_name, p_item_description, p_tenant_id, p_category_id, p_type_id, p_unit_of_measure_id, 
                ROUND(p_purchase_price, 2), p_purchase_price_currency_id, 
                ROUND(p_selling_price, 2), p_selling_price_currency_id, 
                p_max_inventory_level, p_min_inventory_level, p_re_order_level, 
                p_low_stock_alert, p_over_stock_alert, p_image_links, NOW(), NOW()
            )
            RETURNING items.id INTO v_auto_id;

            -- Generate formatted item_id (ITEM-0001)
            v_item_id := 'ITEM-' || LPAD(v_auto_id::TEXT, 4, '0');

            -- Update the row with the formatted item_id
            UPDATE items 
            SET item_id = v_item_id 
            WHERE items.id = v_auto_id;

            -- Get the full item data to return using a specific record variable
            SELECT * INTO v_item_record FROM items WHERE items.id = v_auto_id;
            v_item_data := row_to_json(v_item_record);

            -- Return success response with both IDs and full item data
            RETURN QUERY 
            SELECT 'SUCCESS'::TEXT, 'Item successfully inserted.'::TEXT, 
                v_auto_id, v_item_id, v_item_data;

        EXCEPTION
            WHEN others THEN
                -- Return error response in case of failure
                RETURN QUERY 
                SELECT 'FAILURE'::TEXT, SQLERRM::TEXT, NULL::BIGINT, NULL::TEXT, NULL::JSON;
        END;
        $$;
    SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP SEQUENCE IF EXISTS item_id_seq;');
        DB::unprepared("DROP FUNCTION IF EXISTS insert_item");

    }
};
