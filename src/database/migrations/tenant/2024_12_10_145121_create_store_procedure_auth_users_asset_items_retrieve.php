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
        DB::unprepared( 
            "CREATE OR REPLACE PROCEDURE store_procedure_auth_users_asset_items_retrieve( 
                IN p_auth_user_id INT,  
                IN p_tenant_id BIGINT,
                IN p_asset_item_id INT DEFAULT NULL  
            ) 
            AS $$
            BEGIN
                DROP TABLE IF EXISTS auth_users_asset_items_from_store_procedure;
            
                IF p_asset_item_id IS NOT NULL AND p_asset_item_id <= 0 THEN
                    RAISE EXCEPTION 'Invalid p_asset_item_id: %', p_asset_item_id;
                END IF;
            
                CREATE TEMP TABLE auth_users_asset_items_from_store_procedure AS
                SELECT
                    ai.id,
                    a.id as asset_id,
                    a.name as asset_name,
                    ai.model_number,
                    ai.serial_number,
                    ai.thumbnail_image,
                    ai.qr_code,
                    ai.item_value,
                    ai.item_documents,
                    ai.created_at as register_date,
                    a.assets_type as assets_type_id,
                    ast.name as assets_type_name,
                    a.category as category_id,
                    ac.name as category_name,
                    a.sub_category as sub_category_id,
                    assc.name as sub_category_name,
                    a.asset_value,
                    a.asset_description,
                    a.asset_details,
                    a.asset_classification,
                    a.reading_parameters,
                    ai.supplier as supplier_id,
                    s.name as supplier_name,
                    ai.purchase_order_number,
                    ai.purchase_cost,
                    ai.purchase_type as purchase_type_id,
                    arat.name as purchase_type_name,
                    ai.received_condition,
                    ai.warranty,
                    ai.warranty_exparing_at,
                    ai.other_purchase_details,
                    ai.purchase_document,
                    ai.insurance_number,
                    ai.insurance_exparing_at,
                    ai.insurance_document,
                    ai.expected_life_time,
                    ai.depreciation_value,
                    ai.responsible_person as responsible_person_id,
                    u.name as responsible_person_name,
                    u.profile_image as responsible_person_profile_image,
                    ai.asset_location_latitude,
                    ai.asset_location_longitude,
                    ai.department as department_id,
                    o.data as department_data,
                    ai.registered_by as registered_by_id,
                    ur.name as registered_by_name,
                    ur.profile_image as registered_by_profile_image
                FROM
                    asset_items ai
                INNER JOIN
                    assets a ON ai.asset_id = a.id
                INNER JOIN
                    assets_types ast ON a.assets_type = ast.id
                INNER JOIN
                    asset_categories ac ON a.category = ac.id
                INNER JOIN
                    asset_sub_categories assc ON a.sub_category = assc.id
                INNER JOIN
                    suppliers s ON ai.supplier = s.id
                INNER JOIN
                    asset_requisition_availability_types arat ON ai.purchase_type = arat.id
                INNER JOIN
                    users u ON ai.responsible_person = u.id
                INNER JOIN
                    organization o ON ai.department = o.id
                INNER JOIN
                    users ur ON ai.registered_by = ur.id
                WHERE
                    (ai.id = p_asset_item_id OR p_asset_item_id IS NULL OR p_asset_item_id = 0)
                    AND ai.responsible_person = p_auth_user_id
                    AND ai.tenant_id = p_tenant_id
                    AND ai.deleted_at IS NULL
                    AND ai.isactive = TRUE
                GROUP BY
                ai.id, a.id, ast.id, ac.id, assc.id, s.id, arat.id, u.id, o.id, ur.id;
            END;
            $$ LANGUAGE plpgsql;"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS store_procedure_auth_users_asset_items_retrieve');
    }
};
