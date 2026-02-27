<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
                    WHERE proname = 'get_auth_user_asset_items'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION get_auth_user_asset_items(
                p_auth_user_id INT,
                p_tenant_id BIGINT,
                p_asset_item_id INT
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                asset_id BIGINT,
                asset_name TEXT,
                model_number TEXT,
                serial_number TEXT,
                thumbnail_image JSON,
                qr_code TEXT,
                item_value NUMERIC,
                item_documents JSON,
                register_date TIMESTAMP,
                assets_type_id BIGINT,
                assets_type_name TEXT,
                category_id BIGINT,
                category_name TEXT,
                sub_category_id BIGINT,
                sub_category_name TEXT,
                asset_description TEXT,
                asset_details JSON,
                asset_classification JSON,
                supplier_id BIGINT,
                supplier_name TEXT,
                purchase_order_number TEXT,
                purchase_cost NUMERIC,
                purchase_type_id BIGINT,
                purchase_type_name TEXT,
                received_condition TEXT,
                warranty TEXT,
                warranty_exparing_at DATE,
                other_purchase_details TEXT,
                purchase_document JSON,
                insurance_number TEXT,
                insurance_exparing_at DATE,
                insurance_document JSON,
                expected_life_time TEXT,
                depreciation_value NUMERIC,
                responsible_person_id BIGINT,
                responsible_person_name TEXT,
                responsible_person_profile_image TEXT,
                asset_location_latitude TEXT,
                asset_location_longitude TEXT,
                department_id BIGINT,
                department_data JSON,
                registered_by_id BIGINT,
                registered_by_name TEXT,
                registered_by_profile_image TEXT,
                categories_reading_parameters JSON,
                sub_categories_reading_parameters JSON,
                asset_reading_parameters JSON,
                asset_item_reading_parameters JSON,
                asset_tag TEXT,
                purchase_cost_currency_id BIGINT,
                warrenty_condition_type_id BIGINT,
                item_value_currency_id BIGINT,
                warrenty_usage_name TEXT,
                warranty_usage_value TEXT,
                manufacturer TEXT,
                depreciation_method TEXT,
                consumables_kpi JSON,
                maintenance_kpi JSON,
                service_support_kpi JSON,
                is_schedule_available BOOLEAN,
                assigned_users JSON
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN

                ------------------------------------------------------------------
                -- Validate Auth User
                ------------------------------------------------------------------
                IF NOT EXISTS (
                    SELECT 1 
                    FROM users u
                    WHERE u.id = p_auth_user_id
                    AND u.tenant_id = p_tenant_id
                    AND u.deleted_at IS NULL
                    AND u.is_user_active = TRUE
                ) THEN
                    RETURN QUERY SELECT
                        'FAILURE',
                        'Invalid auth user ID provided.',
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL;
                    RETURN;
                END IF;

                ------------------------------------------------------------------
                -- Validate Tenant
                ------------------------------------------------------------------
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE',
                        'Invalid tenant ID provided',
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL;
                    RETURN;
                END IF;

                ------------------------------------------------------------------
                -- Validate Asset Item ID
                ------------------------------------------------------------------
                IF p_asset_item_id IS NOT NULL AND p_asset_item_id <= 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE',
                        'Invalid asset item ID provided',
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL;
                    RETURN;
                END IF;

                ------------------------------------------------------------------
                -- Main Query
                ------------------------------------------------------------------
                RETURN QUERY
                SELECT
                    'SUCCESS',
                    'Asset items retrieved successfully',
                    ai.id,
                    a.id,
                    a.name::TEXT,
                    ai.model_number::TEXT,
                    ai.serial_number::TEXT,
                    ai.thumbnail_image::JSON,
                    ai.qr_code::TEXT,
                    ai.item_value,
                    ai.item_documents::JSON,
                    ai.created_at,
                    ac.assets_type,
                    ast.name::TEXT,
                    a.category,
                    ac.name::TEXT,
                    a.sub_category,
                    assc.name::TEXT,
                    a.asset_description::TEXT,
                    a.asset_details::JSON,
                    a.asset_classification::JSON,
                    ai.supplier,
                    s.name::TEXT,
                    ai.purchase_order_number::TEXT,
                    ai.purchase_cost,
                    ai.purchase_type,
                    arat.name::TEXT,
                    ai.received_condition::TEXT,
                    ai.warranty::TEXT,
                    ai.warranty_exparing_at,
                    ai.other_purchase_details::TEXT,
                    ai.purchase_document::JSON,
                    ai.insurance_number::TEXT,
                    ai.insurance_exparing_at,
                    ai.insurance_document::JSON,
                    ai.expected_life_time::TEXT,
                    ai.depreciation_value,
                    ai.responsible_person,
                    u.name::TEXT,
                    u.profile_image::TEXT,
                    ai.asset_location_latitude::TEXT,
                    ai.asset_location_longitude::TEXT,
                    ai.department,
                    o.data::JSON,
                    ai.registered_by,
                    ur.name::TEXT,
                    ur.profile_image::TEXT,
                    ac.reading_parameters::JSON,
                    assc.reading_parameters::JSON,
                    a.reading_parameters::JSON,
                    ai.reading_parameters::JSON,
                    ai.asset_tag::TEXT,
                    ai.purchase_cost_currency_id,
                    ai.warrenty_condition_type_id,
                    ai.item_value_currency_id,
                    ai.warrenty_usage_name::TEXT,
                    ai.warranty_usage_value::TEXT,
                    ai.manufacturer::TEXT,
                    ai.depreciation_method::TEXT,
                    ai.consumables_kpi::JSON,
                    ai.maintenance_kpi::JSON,
                    ai.service_support_kpi::JSON,
                    ai.is_schedule_available,
                    COALESCE((
                        SELECT json_agg(json_build_object('user_id', aiau.user_id))
                        FROM asset_item_assigned_users aiau
                        WHERE aiau.asset_item_id = ai.id
                        AND aiau.tenant_id = p_tenant_id
                        AND aiau.is_active = true
                        AND aiau.deleted_at IS NULL
                    ), '[]'::JSON) AS assigned_users
                FROM asset_items ai
                INNER JOIN assets a ON ai.asset_id = a.id
                INNER JOIN asset_categories ac ON a.category = ac.id
                INNER JOIN assets_types ast ON ac.assets_type = ast.id
                INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
                INNER JOIN suppliers s ON ai.supplier = s.id
                INNER JOIN asset_requisition_availability_types arat ON ai.purchase_type = arat.id
                INNER JOIN users u ON ai.responsible_person = u.id
                INNER JOIN organization o ON ai.department = o.id
                INNER JOIN users ur ON ai.registered_by = ur.id
                WHERE
                    (p_asset_item_id IS NULL OR ai.id = p_asset_item_id)
                    AND ai.responsible_person = p_auth_user_id
                    AND ai.tenant_id = p_tenant_id
                    AND ai.deleted_at IS NULL
                    AND ai.isactive = TRUE
                GROUP BY
                    ai.id, a.id, ast.id, ac.id, assc.id, s.id, arat.id, u.id, o.id, ur.id;

            END;
            $$;

            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_auth_user_asset_items');
    }
};
