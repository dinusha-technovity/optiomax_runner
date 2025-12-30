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
                    CREATE OR REPLACE FUNCTION get_asset_items_public_data(
                        _tenant_id BIGINT,
                        p_asset_item_id BIGINT DEFAULT NULL
                    )
                    RETURNS TABLE (
                        status TEXT,
                        message TEXT,
                        id BIGINT,
                        booking_availability BOOLEAN,
                        assignee_type JSONB,
                        asset_id BIGINT,
                        asset_name VARCHAR,
                        model_number VARCHAR,
                        serial_number VARCHAR,
                        thumbnail_image JSONB,
                        qr_code JSONB,
                        item_documents JSONB,
                        register_date TIMESTAMP,
                        assets_type_id BIGINT,
                        assets_type_name VARCHAR,
                        category_id BIGINT,
                        category_name VARCHAR,
                        sub_category_id BIGINT,
                        sub_category_name VARCHAR,
                        asset_description TEXT,
                        asset_details JSONB,
                        asset_classification JSONB,
                        asset_item_classification JSONB,
                        reading_parameters JSONB,
                        purchase_type_id BIGINT,
                        purchase_type_name VARCHAR,
                        received_condition_id BIGINT,
                        received_condition_name VARCHAR,
                        warranty VARCHAR,
                        warranty_expiring_at DATE,
                        insurance_number VARCHAR,
                        insurance_expiring_at DATE,
                        expected_life_time VARCHAR,
                        responsible_person_id BIGINT,
                        responsible_person_name VARCHAR,
                        responsible_person_profile_image VARCHAR,
                        asset_location_latitude VARCHAR,
                        asset_location_longitude VARCHAR,
                        department_id BIGINT,
                        department_data JSONB,
                        registered_by_id BIGINT,
                        registered_by_name VARCHAR,
                        registered_by_profile_image VARCHAR,
                        asset_tag VARCHAR,
                        warrenty_condition_type_id BIGINT,
                        warrenty_condition_type_name VARCHAR,
                        warrenty_usage_name VARCHAR,
                        warranty_usage_value VARCHAR,
                        manufacturer VARCHAR,
                        depreciation_method_id BIGINT,
                        depreciation_method_name VARCHAR,
                        consumables_kpi JSONB,
                        maintenance_kpi JSONB,
                        service_support_kpi JSONB,
                        expectedLifeTimeUnit BIGINT,
                        asset_group_data JSONB,
                        work_orders_data JSONB
                    )
                    LANGUAGE plpgsql
                    AS $$
                    BEGIN
                        -- Validate tenant ID
                        IF _tenant_id IS NULL OR _tenant_id <= 0 THEN
                            RETURN QUERY SELECT 
                                'FAILURE'::TEXT AS status,
                                'Invalid tenant ID provided'::TEXT AS message,
                                NULL::BIGINT, NULL::BOOLEAN, NULL::JSONB, NULL::BIGINT, NULL::VARCHAR, NULL::VARCHAR, NULL::VARCHAR, 
                                NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::TIMESTAMP,
                                NULL::BIGINT, NULL::VARCHAR, NULL::BIGINT, NULL::VARCHAR, NULL::BIGINT, 
                                NULL::VARCHAR, NULL::TEXT, NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::JSONB,
                                NULL::BIGINT, NULL::VARCHAR, NULL::BIGINT, NULL::VARCHAR, NULL::VARCHAR, 
                                NULL::DATE, NULL::VARCHAR, NULL::DATE, NULL::VARCHAR, 
                                NULL::BIGINT, NULL::VARCHAR, NULL::VARCHAR, NULL::VARCHAR, 
                                NULL::VARCHAR, NULL::BIGINT, NULL::JSONB, NULL::BIGINT, NULL::VARCHAR, 
                                NULL::VARCHAR, NULL::VARCHAR, NULL::BIGINT, NULL::VARCHAR,
                                NULL::VARCHAR, NULL::VARCHAR, NULL::VARCHAR, NULL::BIGINT, NULL::VARCHAR,
                                NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                            RETURN;
                        END IF;

                        -- Validate asset item ID (allow 0 to get all items)
                        IF p_asset_item_id IS NOT NULL AND p_asset_item_id < 0 THEN
                            RETURN QUERY SELECT 
                                'FAILURE'::TEXT AS status,
                                'Invalid asset item ID provided'::TEXT AS message,
                                NULL::BIGINT, NULL::BOOLEAN, NULL::JSONB, NULL::BIGINT, NULL::VARCHAR, NULL::VARCHAR, NULL::VARCHAR, 
                                NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::TIMESTAMP,
                                NULL::BIGINT, NULL::VARCHAR, NULL::BIGINT, NULL::VARCHAR, NULL::BIGINT, 
                                NULL::VARCHAR, NULL::TEXT, NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::JSONB,
                                NULL::BIGINT, NULL::VARCHAR, NULL::BIGINT, NULL::VARCHAR, NULL::VARCHAR, 
                                NULL::DATE, NULL::VARCHAR, NULL::DATE, NULL::VARCHAR, 
                                NULL::BIGINT, NULL::VARCHAR, NULL::VARCHAR, NULL::VARCHAR, 
                                NULL::VARCHAR, NULL::BIGINT, NULL::JSONB, NULL::BIGINT, NULL::VARCHAR, 
                                NULL::VARCHAR, NULL::VARCHAR, NULL::BIGINT, NULL::VARCHAR,
                                NULL::VARCHAR, NULL::VARCHAR, NULL::VARCHAR, NULL::BIGINT, NULL::VARCHAR,
                                NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                            RETURN;
                        END IF;

                        -- Return the matching records (without sensitive financial data)
                        RETURN QUERY
                        SELECT
                            'SUCCESS'::TEXT AS status,
                            'Asset items retrieved successfully'::TEXT AS message,
                            ai.id,
                            ai.booking_availability,
                            CASE 
                                WHEN ai.assignee_type_id IS NOT NULL THEN
                                    jsonb_build_object(
                                        'id', ai.assignee_type_id,
                                        'name', at.name
                                    )
                                ELSE NULL
                            END AS assignee_type,
                            a.id AS asset_id, 
                            a.name AS asset_name, 
                            ai.model_number, 
                            ai.serial_number,
                            ai.thumbnail_image,
                            ai.qr_code,
                            ai.item_documents, 
                            ai.created_at AS register_date, 
                            ac.assets_type AS assets_type_id, 
                            ast.name AS assets_type_name, 
                            a.category AS category_id, 
                            ac.name AS category_name, 
                            a.sub_category AS sub_category_id, 
                            assc.name AS sub_category_name, 
                            a.asset_description, 
                            a.asset_details,
                            ai.asset_classification,
                            ai.asset_classification AS asset_item_classification,
                            ai.reading_parameters,
                            ai.purchase_type AS purchase_type_id, 
                            arat.name AS purchase_type_name, 
                            ai.received_condition::BIGINT AS received_condition_id, 
                            arct.name AS received_condition_name, 
                            ai.warranty, 
                            ai.warranty_exparing_at,
                            ai.insurance_number, 
                            ai.insurance_exparing_at, 
                            ai.expected_life_time, 
                            ai.responsible_person AS responsible_person_id, 
                            u.name AS responsible_person_name, 
                            u.profile_image AS responsible_person_profile_image, 
                            ai.asset_location_latitude, 
                            ai.asset_location_longitude, 
                            ai.department AS department_id, 
                            o.data AS department_data, 
                            ai.registered_by AS registered_by_id, 
                            ur.name AS registered_by_name, 
                            ur.profile_image AS registered_by_profile_image,
                            ai.asset_tag,
                            ai.warrenty_condition_type_id,
                            wct.name AS warrenty_condition_type_name,
                            ai.warrenty_usage_name,
                            ai.warranty_usage_value,
                            ai.manufacturer,
                            ai.depreciation_method::BIGINT AS depreciation_method_id,
                            dmt.name AS depreciation_method_name,
                            ai.consumables_kpi,
                            ai.maintenance_kpi,
                            ai.service_support_kpi,
                            ai.expected_life_time_unit,

                            CASE 
                                WHEN a.id IS NOT NULL THEN 
                                    jsonb_build_object(
                                        'id', a.id,
                                        'name', a.name,
                                        'category', jsonb_build_object(
                                            'id', ac.id,
                                            'name', ac.name
                                            ),
                                            'sub_category',jsonb_build_object(
                                            'id', assc.id,
                                            'name', assc.name
                                            ),
                                        'isactive', true
                                    )
                                ELSE NULL
                            END AS asset_group_data,

                        -- Get work orders related to this asset item
                        (
                            SELECT jsonb_agg(
                                jsonb_build_object(
                                    'id', wo.id,
                                    'work_order_number', wo.work_order_number,
                                    'title', wo.title,
                                    'description', wo.description,
                                    'status', wo.status,
                                    'priority', wo.priority,
                                    'type', wo.type,
                                    'work_order_start', wo.work_order_start,
                                    'work_order_end', wo.work_order_end,
                                    'created_at', wo.created_at,
                                    'updated_at', wo.updated_at,
                                    'technician', CASE 
                                        WHEN tech_user.id IS NOT NULL THEN
                                            jsonb_build_object(
                                                'id', tech_user.id,
                                                'name', tech_user.name,
                                                'email', tech_user.email,
                                                'profile_image', tech_user.profile_image
                                            )
                                        ELSE NULL
                                    END
                                ) ORDER BY wo.created_at DESC
                            )
                            FROM work_orders wo
                            LEFT JOIN users tech_user ON wo.technician_id = tech_user.id
                            WHERE wo.asset_item_id = ai.id
                            AND wo.tenant_id = _tenant_id
                            AND wo.isactive = TRUE
                            AND wo.deleted_at IS NULL
                        ) AS work_orders_data

                        FROM asset_items ai
                        INNER JOIN assets a ON ai.asset_id = a.id
                        LEFT JOIN assignee_types at ON ai.assignee_type_id = at.id
                        INNER JOIN asset_categories ac ON a.category = ac.id
                        INNER JOIN assets_types ast ON ac.assets_type = ast.id
                        INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
                        INNER JOIN asset_requisition_availability_types arat ON ai.purchase_type = arat.id
                        LEFT JOIN asset_received_condition_types arct ON ai.received_condition::BIGINT = arct.id
                        LEFT JOIN depreciation_method_table dmt ON ai.depreciation_method::BIGINT = dmt.id
                        INNER JOIN users u ON ai.responsible_person = u.id
                        INNER JOIN organization o ON ai.department = o.id
                        INNER JOIN users ur ON ai.registered_by = ur.id
                        INNER JOIN warrenty_condition_types wct ON ai.warrenty_condition_type_id = wct.id

                        WHERE ((ai.id = p_asset_item_id AND p_asset_item_id IS NOT NULL AND p_asset_item_id > 0) OR 
                               (p_asset_item_id IS NULL OR p_asset_item_id = 0))
                            AND ai.tenant_id = _tenant_id
                            AND ai.deleted_at IS NULL
                            AND ai.isactive = TRUE;
                    END;
                    $$
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_items_public_data(BIGINT, BIGINT);');
    }
};
