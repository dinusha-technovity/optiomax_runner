<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    { 
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_auth_user_asset_items(
                p_auth_user_id INT,
                p_tenant_id BIGINT,
                p_asset_item_id INT DEFAULT NULL
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
                asset_value NUMERIC,
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
                asset_reading_parameters JSON
            ) 
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Validate auth user ID
                IF NOT EXISTS (
                    SELECT 1 
                    FROM users u
                    WHERE u.id = p_auth_user_id 
                    AND u.tenant_id = p_tenant_id
                    AND u.deleted_at IS NULL
                    AND u.is_user_active = TRUE
                ) THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid auth user ID provided. User does not exist or is not active.'::TEXT AS message,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL;
                    RETURN;
                END IF;
        
                -- Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL;
                    RETURN;
                END IF;
        
                -- Validate asset item ID
                IF p_asset_item_id IS NOT NULL AND p_asset_item_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid asset item ID provided'::TEXT AS message,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL, NULL, NULL, NULL, NULL,
                        NULL;
                    RETURN;
                END IF;
        
                -- Return the matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Asset items retrieved successfully'::TEXT AS message,
                    ai.id,
                    a.id AS asset_id,
                    a.name::TEXT AS asset_name,
                    ai.model_number::TEXT,
                    ai.serial_number::TEXT,
                    ai.thumbnail_image::JSON,
                    ai.qr_code::TEXT,
                    ai.item_value,
                    ai.item_documents::JSON,
                    ai.created_at AS register_date,
                    a.assets_type AS assets_type_id,
                    ast.name::TEXT AS assets_type_name,
                    a.category AS category_id,
                    ac.name::TEXT AS category_name,
                    a.sub_category AS sub_category_id,
                    assc.name::TEXT AS sub_category_name,
                    a.asset_value,
                    a.asset_description::TEXT,
                    a.asset_details::JSON,
                    a.asset_classification::JSON,
                    ai.supplier AS supplier_id,
                    s.name::TEXT AS supplier_name,
                    ai.purchase_order_number::TEXT,
                    ai.purchase_cost,
                    ai.purchase_type AS purchase_type_id,
                    arat.name::TEXT AS purchase_type_name,
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
                    ai.responsible_person AS responsible_person_id,
                    u.name::TEXT AS responsible_person_name,
                    u.profile_image::TEXT AS responsible_person_profile_image,
                    ai.asset_location_latitude::TEXT,
                    ai.asset_location_longitude::TEXT,
                    ai.department AS department_id,
                    o.data::JSON AS department_data,
                    ai.registered_by AS registered_by_id,
                    ur.name::TEXT AS registered_by_name,
                    ur.profile_image::TEXT AS registered_by_profile_image,
                    ac.reading_parameters::JSON,
                    assc.reading_parameters::JSON,
                    a.reading_parameters::JSON
                FROM
                    asset_items ai
                INNER JOIN assets a ON ai.asset_id = a.id
                INNER JOIN assets_types ast ON a.assets_type = ast.id
                INNER JOIN asset_categories ac ON a.category = ac.id
                INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
                INNER JOIN suppliers s ON ai.supplier = s.id
                INNER JOIN asset_requisition_availability_types arat ON ai.purchase_type = arat.id
                INNER JOIN users u ON ai.responsible_person = u.id
                INNER JOIN organization o ON ai.department = o.id
                INNER JOIN users ur ON ai.registered_by = ur.id
                WHERE
                    (ai.id = p_asset_item_id OR p_asset_item_id IS NULL)
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