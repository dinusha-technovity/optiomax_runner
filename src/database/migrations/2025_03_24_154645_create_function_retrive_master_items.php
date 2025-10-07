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
        CREATE OR REPLACE FUNCTION get_items_with_details(
            IN p_tenant_id BIGINT DEFAULT NULL,
            IN p_item_id BIGINT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            id BIGINT,
            item_id VARCHAR,
            item_name VARCHAR,
            type_name VARCHAR,
            category_name VARCHAR,
            item_description TEXT,
            is_active BOOLEAN,
            purchase_price NUMERIC(15,2),
            selling_price NUMERIC(15,2),
            max_inventory_level INTEGER,
            min_inventory_level INTEGER,
            re_order_level INTEGER,
            -- Additional fields from get_item_details
            type_id BIGINT,
            category_id BIGINT,
            unit_of_measure_id BIGINT,
            purchase_price_currency_id BIGINT,
            selling_price_currency_id BIGINT,
            low_stock_alert BOOLEAN,
            over_stock_alert BOOLEAN,
            image_links JSONB,
            suppliers JSONB
        )
        LANGUAGE plpgsql
        AS $$
        BEGIN
            -- Case 1: Validate tenant ID is provided and valid
            IF p_tenant_id IS NULL OR p_tenant_id < 0 THEN
                RETURN QUERY 
                SELECT 
                    'FAILURE'::TEXT AS status,
                    'Invalid tenant ID provided'::TEXT AS message,
                    NULL::BIGINT,
                    NULL::VARCHAR,
                    NULL::VARCHAR,
                    NULL::VARCHAR,
                    NULL::VARCHAR,
                    NULL::TEXT,
                    NULL::BOOLEAN,
                    NULL::NUMERIC(15,2),
                    NULL::NUMERIC(15,2),
                    NULL::INTEGER,
                    NULL::INTEGER,
                    NULL::INTEGER,
                    -- Additional fields
                    NULL::BIGINT,
                    NULL::BIGINT,
                    NULL::BIGINT,
                    NULL::BIGINT,
                    NULL::BIGINT,
                    NULL::BOOLEAN,
                    NULL::BOOLEAN,
                    NULL::JSONB,
                    NULL::JSONB;
                RETURN;
            END IF;

            -- Case 2: Validate item ID if provided
            IF p_item_id IS NOT NULL AND p_item_id < 0 THEN
                RETURN QUERY 
                SELECT 
                    'FAILURE'::TEXT AS status,
                    'Invalid item ID provided'::TEXT AS message,
                    NULL::BIGINT,
                    NULL::VARCHAR,
                    NULL::VARCHAR,
                    NULL::VARCHAR,
                    NULL::VARCHAR,
                    NULL::TEXT,
                    NULL::BOOLEAN,
                    NULL::NUMERIC(15,2),
                    NULL::NUMERIC(15,2),
                    NULL::INTEGER,
                    NULL::INTEGER,
                    NULL::INTEGER,
                    -- Additional fields
                    NULL::BIGINT,
                    NULL::BIGINT,
                    NULL::BIGINT,
                    NULL::BIGINT,
                    NULL::BIGINT,
                    NULL::BOOLEAN,
                    NULL::BOOLEAN,
                    NULL::JSONB,
                    NULL::JSONB;
                RETURN;
            END IF;

            -- Case 3: Retrieve items with details
            RETURN QUERY
            SELECT 
                'SUCCESS'::TEXT AS status,
                CASE 
                    WHEN p_item_id IS NULL THEN 'Items fetched successfully'::TEXT
                    ELSE 'Item details fetched successfully'::TEXT
                END AS message,
                i.id,
                i.item_id,
                i.item_name,
                t.name AS type_name,
                c.name AS category_name,
                i.item_description,
                i.isactive AS is_active,
                i.purchase_price,
                i.selling_price,
                i.max_inventory_level,
                i.min_inventory_level,
                i.re_order_level,
                -- Additional fields
                i.type_id,
                i.category_id,
                i.unit_of_measure_id,
                i.purchase_price_currency_id,
                i.selling_price_currency_id,
                i.low_stock_alert,
                i.over_stock_alert,
                i.image_links,
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
            LEFT JOIN item_categories c ON i.category_id = c.id AND c.deleted_at IS NULL
            LEFT JOIN item_types t ON i.type_id = t.id AND t.deleted_at IS NULL
            LEFT JOIN suppliers_for_item si ON i.id = si.master_item_id AND si.deleted_at IS NULL
            WHERE i.tenant_id = p_tenant_id 
            AND (p_item_id IS NULL OR i.id = p_item_id)
            AND i.isactive = TRUE 
            AND i.deleted_at IS NULL
            GROUP BY i.id, t.name, c.name;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_items_with_details(BIGINT);");
    }
};
