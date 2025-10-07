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
            CREATE OR REPLACE FUNCTION get_item_details(
            IN p_tenant_id BIGINT DEFAULT NULL,
            IN p_item_id BIGINT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            id BIGINT,
            item_id VARCHAR,
            item_name VARCHAR,
            item_description TEXT,
            type_id BIGINT,
            category_id BIGINT,
            isActive BOOlEAN,
            unit_of_measure_id BIGINT,
            purchase_price DECIMAL(15,2),
            purchase_price_currency_id BIGINT,
            selling_price DECIMAL(15,2),
            selling_price_currency_id BIGINT,
            max_inventory_level INT,
            min_inventory_level INT,
            re_order_level INT,
            low_stock_alert BOOLEAN,
            over_stock_alert BOOLEAN,
            suppliers JSONB
        )
        LANGUAGE plpgsql
        AS $$
        BEGIN
            -- Case 1: Return all items when both parameters are NULL
            IF p_tenant_id IS NULL AND p_item_id IS NULL THEN
                RETURN QUERY
                SELECT 
                    'SUCCESS'::TEXT AS status,
                    'All items fetched successfully'::TEXT AS message,
                    i.id,
                    i.item_id,
                    i.item_name,
                    i.item_description,
                    i.type_id,
                    i.category_id,
                    i.isactive,
                    i.unit_of_measure_id,
                    i.purchase_price,
                    i.purchase_price_currency_id,
                    i.selling_price,
                    i.selling_price_currency_id,
                    i.max_inventory_level,
                    i.min_inventory_level,
                    i.re_order_level,
                    i.low_stock_alert,
                    i.over_stock_alert,
                    
                    COALESCE(
                        jsonb_agg(
                            jsonb_build_object(
                                'supplier_id', si.supplier_id,
                                'lead_time', si.lead_time
                            )
                        ) FILTER (WHERE si.supplier_id IS NOT NULL),
                        '[]'::JSONB
                    ) AS suppliers
                FROM items i
                LEFT JOIN suppliers_for_item si ON i.id = si.master_item_id
                WHERE i.deleted_at IS NULL AND i.isactive = TRUE
                GROUP BY i.id;
                RETURN;
            END IF;

            -- Case 2: Validate tenant ID
            IF p_tenant_id IS NULL OR p_tenant_id < 0 THEN
                RETURN QUERY 
                SELECT 
                    'FAILURE'::TEXT AS status,
                    'Invalid tenant ID provided'::TEXT AS message,
                    NULL::BIGINT,
                    NULL::VARCHAR,
                    NULL::VARCHAR,
                    NULL::TEXT,
                    NULL::BIGINT,
                    NULL::BIGINT,
                    NULL::BOOLEAN,
                    NULL::BIGINT,
                    NULL::DECIMAL,
                    NULL::BIGINT,
                    NULL::DECIMAL,
                    NULL::BIGINT,
                    NULL::INT,
                    NULL::INT,
                NULL::INT,
                NULL::BOOLEAN,
                NULL::BOOLEAN,
                    NULL::JSONB;
                RETURN;
            END IF;

            -- Case 3: Validate item ID if provided
            IF p_item_id IS NOT NULL AND p_item_id < 0 THEN
                RETURN QUERY 
                SELECT 
                    'FAILURE'::TEXT AS status,
                    'Invalid item ID provided'::TEXT AS message,
                    NULL::BIGINT,
                    NULL::VARCHAR,
                    NULL::VARCHAR,
                    NULL::TEXT,
                    NULL::BIGINT,
                    NULL::BIGINT,
                    NULL::BOOLEAN,
                    NULL::BIGINT,
                    NULL::DECIMAL,
                    NULL::BIGINT,
                    NULL::DECIMAL,
                    NULL::BIGINT,
                    NULL::INT,
                    NULL::INT,
                NULL::INT,
                NULL::BOOLEAN,
                NULL::BOOLEAN,
                    NULL::JSONB;
                RETURN;
            END IF;

            -- Case 4: Return matching item details
            RETURN QUERY
            SELECT 
                'SUCCESS'::TEXT AS status,
                'Item details fetched successfully'::TEXT AS message,
                i.id,
                i.item_id,
                i.item_name,
                i.item_description,
                i.type_id,
                i.category_id,
                i.isactive,
                i.unit_of_measure_id,
                i.purchase_price,
                i.purchase_price_currency_id,
                i.selling_price,
                i.selling_price_currency_id,
                i.max_inventory_level,
                i.min_inventory_level,
                i.re_order_level,
                i.low_stock_alert,
                i.over_stock_alert,
                COALESCE(
                    jsonb_agg(
                        jsonb_build_object(
                            'supplier_id', si.supplier_id,
                            'lead_time', si.lead_time
                        )
                    ) FILTER (WHERE si.supplier_id IS NOT NULL),
                    '[]'::JSONB
                ) AS suppliers
            FROM items i
            LEFT JOIN suppliers_for_item si ON i.id = si.master_item_id
            WHERE (p_item_id IS NULL OR i.id = p_item_id)
            AND i.tenant_id = p_tenant_id
            AND i.deleted_at IS NULL
            AND i.isactive = TRUE
            GROUP BY i.id;
        END;

        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_item_details(BIGINT,BIGINT);");
    }
};
