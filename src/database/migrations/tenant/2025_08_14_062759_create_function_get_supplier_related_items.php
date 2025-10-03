<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<SQL
        CREATE OR REPLACE FUNCTION get_supplier_related_items(
            IN p_tenant_id BIGINT,
            IN p_supplier_id BIGINT
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            supplier_items JSON
        )
        LANGUAGE plpgsql
        AS $$
        BEGIN
            -- Validate tenant
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT
                    'FAILURE', 'Invalid tenant ID provided', NULL::JSON;
                RETURN;
            END IF;

            -- Validate supplier
            IF p_supplier_id IS NULL OR p_supplier_id <= 0 THEN
                RETURN QUERY SELECT
                    'FAILURE', 'Invalid supplier ID provided', NULL::JSON;
                RETURN;
            END IF;

            -- Check supplier existence
            IF NOT EXISTS (
                SELECT 1 FROM suppliers s
                WHERE s.id = p_supplier_id
                AND s.tenant_id = p_tenant_id
                AND s.deleted_at IS NULL
            ) THEN
                RETURN QUERY SELECT
                    'FAILURE', 'Supplier not found', NULL::JSON;
                RETURN;
            END IF;

            -- Return supplier related items
            RETURN QUERY
            SELECT
                'SUCCESS'::TEXT,
                'Supplier related items fetched successfully'::TEXT,
                COALESCE(
                    (
                        SELECT json_agg(
                            json_build_object(
                                'id', i.id,
                                'item_id', i.item_id,
                                'item_name', i.item_name,
                                'type_id', i.type_id,
                                'type_name', t.name,
                                'category_id', i.category_id,
                                'category_name', c.name,
                                'item_description', i.item_description,
                                'purchase_price', i.purchase_price,
                                'selling_price', i.selling_price,
                                'unit_of_measure_id', i.unit_of_measure_id,
                                'low_stock_alert', i.low_stock_alert,
                                'over_stock_alert', i.over_stock_alert,
                                'image_links', i.image_links
                            )
                        )
                        FROM items i
                        LEFT JOIN item_categories c 
                            ON i.category_id = c.id AND c.deleted_at IS NULL
                        LEFT JOIN item_types t 
                            ON i.type_id = t.id AND t.deleted_at IS NULL
                        INNER JOIN suppliers_for_item si 
                            ON i.id = si.master_item_id 
                            AND si.supplier_id = p_supplier_id
                            AND si.deleted_at IS NULL
                        WHERE i.tenant_id = p_tenant_id
                        AND i.deleted_at IS NULL
                        AND i.isactive = TRUE
                    ),
                    '[]'::JSON
                );
        END;
        $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_supplier_related_items(BIGINT, BIGINT)');
    }
};
