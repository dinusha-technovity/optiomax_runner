<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<SQL
            DROP FUNCTION IF EXISTS get_supplier_related_assets(
                p_tenant_id BIGINT,
                p_supplier_id BIGINT
            );

            CREATE OR REPLACE FUNCTION get_supplier_related_assets(
                p_tenant_id BIGINT,
                p_supplier_id BIGINT
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                supplier_items JSON
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Validation
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT, 'Invalid tenant ID provided'::TEXT,
                        NULL::JSON;
                    RETURN;
                END IF;

                IF p_supplier_id IS NULL OR p_supplier_id <= 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT, 'Invalid supplier ID provided'::TEXT,
                        NULL::JSON;
                    RETURN;
                END IF;

                -- Check supplier existence
                IF NOT EXISTS (
                    SELECT 1 FROM suppliers s
                    WHERE s.id = p_supplier_id AND s.tenant_id = p_tenant_id
                        AND s.deleted_at IS NULL
                ) THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT, 'Supplier not found'::TEXT,
                        NULL::JSON;
                    RETURN;
                END IF;

                -- Return the supplier related assets
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT,
                    'Supplier related assets fetched successfully'::TEXT,
                    COALESCE(
                        (SELECT json_agg(
                            json_build_object(
                                'id', ai.id,
                                'asset_id', ai.asset_id,
                                'model_number', ai.model_number,
                                'serial_number', ai.serial_number,
                                'asset_tag', ai.asset_tag,
                                'thumbnail_image', ai.thumbnail_image,
                                'item_value', ai.item_value,
                                'item_value_currency_id', ai.item_value_currency_id,
                                'purchase_cost', ai.purchase_cost,
                                'purchase_cost_currency_id', ai.purchase_cost_currency_id,
                                'purchase_order_number', ai.purchase_order_number,
                                'warranty', ai.warranty,
                                'warranty_exparing_at', ai.warranty_exparing_at,
                                'insurance_number', ai.insurance_number,
                                'insurance_exparing_at', ai.insurance_exparing_at,
                                'manufacturer', ai.manufacturer,
                                'responsible_person', ai.responsible_person,
                                'department', ai.department,
                                'asset_location_latitude', ai.asset_location_latitude,
                                'asset_location_longitude', ai.asset_location_longitude,
                                'created_at', ai.created_at,
                                'updated_at', ai.updated_at
                            )
                        )
                        FROM asset_items ai
                        WHERE ai.supplier = p_supplier_id 
                        AND ai.tenant_id = p_tenant_id
                        AND ai.deleted_at IS NULL),
                        '[]'::JSON
                    )::JSON;
            END;
            $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_supplier_related_assets');
    }
};
