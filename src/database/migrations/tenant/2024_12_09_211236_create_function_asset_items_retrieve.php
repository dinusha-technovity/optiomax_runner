<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // DB::unprepared( 
        //     "CREATE OR REPLACE PROCEDURE store_procedure_asset_items_retrieve( 
        //         IN _tenant_id BIGINT,
        //         IN p_asset_item_id INT DEFAULT NULL  
        //     ) 
        //     AS $$
        //     BEGIN
        //         DROP TABLE IF EXISTS asset_items_from_store_procedure;
            
        //         IF p_asset_item_id IS NOT NULL AND p_asset_item_id <= 0 THEN
        //             RAISE EXCEPTION 'Invalid p_asset_item_id: %', p_asset_item_id;
        //         END IF;
            
        //         CREATE TEMP TABLE asset_items_from_store_procedure AS
        //         SELECT
        //             ai.id,
        //             a.id as asset_id,
        //             a.name as asset_name,
        //             ai.model_number,
        //             ai.serial_number,
        //             ai.thumbnail_image,
        //             ai.qr_code,
        //             ai.item_value,
        //             ai.item_documents,
        //             ai.created_at as register_date,
        //             a.assets_type as assets_type_id,
        //             ast.name as assets_type_name,
        //             a.category as category_id,
        //             ac.name as category_name,
        //             a.sub_category as sub_category_id,
        //             assc.name as sub_category_name,
        //             a.asset_value,
        //             a.asset_description,
        //             a.asset_details,
        //             a.asset_classification,
        //             a.reading_parameters,
        //             ai.supplier as supplier_id,
        //             s.name as supplier_name,
        //             ai.purchase_order_number,
        //             ai.purchase_cost,
        //             ai.purchase_type as purchase_type_id,
        //             arat.name as purchase_type_name,
        //             ai.received_condition,
        //             ai.warranty,
        //             ai.warranty_exparing_at,
        //             ai.other_purchase_details,
        //             ai.purchase_document,
        //             ai.insurance_number,
        //             ai.insurance_exparing_at,
        //             ai.insurance_document,
        //             ai.expected_life_time,
        //             ai.depreciation_value,
        //             ai.responsible_person as responsible_person_id,
        //             u.name as responsible_person_name,
        //             u.profile_image as responsible_person_profile_image,
        //             ai.asset_location_latitude,
        //             ai.asset_location_longitude,
        //             ai.department as department_id,
        //             o.data as department_data,
        //             ai.registered_by as registered_by_id,
        //             ur.name as registered_by_name,
        //             ur.profile_image as registered_by_profile_image
        //         FROM
        //             asset_items ai
        //         INNER JOIN
        //             assets a ON ai.asset_id = a.id
        //         INNER JOIN
        //             assets_types ast ON a.assets_type = ast.id
        //         INNER JOIN
        //             asset_categories ac ON a.category = ac.id
        //         INNER JOIN
        //             asset_sub_categories assc ON a.sub_category = assc.id
        //         INNER JOIN
        //             suppliers s ON ai.supplier = s.id
        //         INNER JOIN
        //             asset_requisition_availability_types arat ON ai.purchase_type = arat.id
        //         INNER JOIN
        //             users u ON ai.responsible_person = u.id
        //         INNER JOIN
        //             organization o ON ai.department = o.id
        //         INNER JOIN
        //             users ur ON ai.registered_by = ur.id
        //         WHERE
        //             (ai.id = p_asset_item_id OR p_asset_item_id IS NULL OR p_asset_item_id = 0)
        //             AND ai.tenant_id = _tenant_id
        //             AND ai.deleted_at IS NULL
        //             AND ai.isactive = TRUE
        //         GROUP BY
        //         ai.id, a.id, ast.id, ac.id, assc.id, s.id, arat.id, u.id, o.id, ur.id;
        //     END;
        //     $$ LANGUAGE plpgsql;"
        // ); 
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_asset_items(
                _tenant_id BIGINT,
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
                asset_item_classification JSON,
                reading_parameters JSON,
                supplier_id BIGINT,
                supplier_name TEXT,
                purchase_order_number TEXT,
                purchase_cost NUMERIC,
                purchase_type_id BIGINT,
                purchase_type_name TEXT,
                received_condition TEXT,
                warranty TEXT,
                warranty_exparing_at TIMESTAMP,
                other_purchase_details TEXT,
                purchase_document JSON,
                insurance_number TEXT,
                insurance_exparing_at TIMESTAMP,
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
                registered_by_profile_image TEXT
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Validate tenant ID
                IF _tenant_id IS NULL OR _tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::JSON, 
                        NULL::TEXT, NULL::NUMERIC, NULL::JSON, NULL::TIMESTAMP,
                        NULL::BIGINT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::BIGINT, 
                        NULL::TEXT, NULL::NUMERIC, NULL::TEXT, NULL::JSON, NULL::JSON, NULL::JSON, 
                        NULL::JSON, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::NUMERIC, 
                        NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TIMESTAMP,
                        NULL::TEXT, NULL::JSON, NULL::TEXT, NULL::TIMESTAMP, NULL::JSON,
                        NULL::NUMERIC, NULL::NUMERIC, NULL::BIGINT, NULL::TEXT, NULL::TEXT,
                        NULL::TEXT, NULL::TEXT, NULL::BIGINT, NULL::JSON, NULL::BIGINT, 
                        NULL::TEXT, NULL::TEXT;
                    RETURN;
                END IF;
        
                -- Validate asset item ID
                IF p_asset_item_id IS NOT NULL AND p_asset_item_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid p_asset_item_id provided'::TEXT AS message,
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::JSON, 
                        NULL::TEXT, NULL::NUMERIC, NULL::JSON, NULL::TIMESTAMP,
                        NULL::BIGINT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::BIGINT, 
                        NULL::TEXT, NULL::NUMERIC, NULL::TEXT, NULL::JSON, NULL::JSON, NULL::JSON, 
                        NULL::JSON, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::NUMERIC, 
                        NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TIMESTAMP,
                        NULL::TEXT, NULL::JSON, NULL::TEXT, NULL::TIMESTAMP, NULL::JSON,
                        NULL::NUMERIC, NULL::NUMERIC, NULL::BIGINT, NULL::TEXT, NULL::TEXT,
                        NULL::TEXT, NULL::TEXT, NULL::BIGINT, NULL::JSON, NULL::BIGINT, 
                        NULL::TEXT, NULL::TEXT;
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
                    ai.item_value::NUMERIC, 
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
                    ai.asset_classification::JSON AS asset_item_classification,
                    a.reading_parameters::JSON,
                    ai.supplier AS supplier_id, 
                    s.name::TEXT AS supplier_name, 
                    ai.purchase_order_number::TEXT,
                    ai.purchase_cost::NUMERIC, 
                    ai.purchase_type AS purchase_type_id, 
                    arat.name::TEXT AS purchase_type_name, 
                    ai.received_condition::TEXT, 
                    ai.warranty::TEXT, 
                    ai.warranty_exparing_at::TIMESTAMP,
                    ai.other_purchase_details::TEXT, 
                    ai.purchase_document::JSON,
                    ai.insurance_number::TEXT, 
                    ai.insurance_exparing_at::TIMESTAMP, 
                    ai.insurance_document::JSON,
                    ai.expected_life_time::TEXT, 
                    ai.depreciation_value::NUMERIC, 
                    ai.responsible_person AS responsible_person_id, 
                    u.name::TEXT AS responsible_person_name, 
                    u.profile_image::TEXT AS responsible_person_profile_image, 
                    ai.asset_location_latitude::TEXT, 
                    ai.asset_location_longitude::TEXT, 
                    ai.department AS department_id, 
                    o.data::JSON AS department_data, 
                    ai.registered_by AS registered_by_id, 
                    ur.name::TEXT AS registered_by_name, 
                    ur.profile_image::TEXT AS registered_by_profile_image
                FROM asset_items ai
                INNER JOIN assets a ON ai.asset_id = a.id
                INNER JOIN assets_types ast ON a.assets_type = ast.id
                INNER JOIN asset_categories ac ON a.category = ac.id
                INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
                INNER JOIN suppliers s ON ai.supplier = s.id
                INNER JOIN asset_requisition_availability_types arat ON ai.purchase_type = arat.id
                INNER JOIN users u ON ai.responsible_person = u.id
                INNER JOIN organization o ON ai.department = o.id
                INNER JOIN users ur ON ai.registered_by = ur.id
                WHERE (ai.id = p_asset_item_id OR p_asset_item_id IS NULL OR p_asset_item_id = 0)
                    AND ai.tenant_id = _tenant_id
                    AND ai.deleted_at IS NULL
                    AND ai.isactive = TRUE
                GROUP BY ai.id, a.id, ast.id, ac.id, assc.id, s.id, arat.id, u.id, o.id, ur.id;
            END;
            $$;
        SQL);       

    } 

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_items');
    }
};