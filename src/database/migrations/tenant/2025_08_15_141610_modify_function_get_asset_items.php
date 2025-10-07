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
            DROP FUNCTION IF EXISTS get_asset_items(
                        _tenant_id BIGINT,
                        p_asset_item_id BIGINT
                    );

            CREATE OR REPLACE FUNCTION get_asset_items(
                _tenant_id BIGINT,
                p_asset_item_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                asset_id BIGINT,
                asset_name VARCHAR,
                model_number VARCHAR,
                serial_number VARCHAR,
                thumbnail_image JSONB,
                qr_code VARCHAR,
                item_value NUMERIC(12,2),
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
                supplier_id BIGINT,
                supplier_name VARCHAR,
                purchase_order_number VARCHAR,
                purchase_cost NUMERIC(12,2),
                purchase_type_id BIGINT,
                purchase_type_name VARCHAR,
                received_condition_id BIGINT,
                received_condition_name VARCHAR,
                warranty VARCHAR,
                warranty_expiring_at DATE,
                other_purchase_details TEXT,
                purchase_document JSONB,
                insurance_number VARCHAR,
                insurance_expiring_at DATE,
                insurance_document JSONB,
                expected_life_time VARCHAR,
                depreciation_value NUMERIC(5,2),
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
                purchase_cost_currency_id BIGINT,
                purchase_cost_currency_code VARCHAR,
                purchase_cost_currency_symbol VARCHAR,
                warrenty_condition_type_id BIGINT,
                warrenty_condition_type_name VARCHAR,
                item_value_currency_id BIGINT,
                item_value_currency_code VARCHAR,
                item_value_currency_symbol VARCHAR,
                warrenty_usage_name VARCHAR,
                warranty_usage_value VARCHAR,
                manufacturer VARCHAR,
                depreciation_method_id BIGINT,
                depreciation_method_name VARCHAR,
                consumables_kpi JSONB,
                maintenance_kpi JSONB,
                service_support_kpi JSONB,
                asset_requisition_item_id BIGINT,
                asset_requisition_id BIGINT,
                procurement_id BIGINT,

                expectedLifeTimeUnit BIGINT,
                salvage_value NUMERIC(14,2),
                depreciationStartDate DATE,
                declineRate NUMERIC(14,2),
                total_estimated_units NUMERIC(14,2),
                asset_consumables JSONB


            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Validate tenant ID
                IF _tenant_id IS NULL OR _tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT, NULL::BIGINT, NULL::VARCHAR,18, NULL::VARCHAR, NULL::VARCHAR, 
                        NULL::JSONB, NULL::VARCHAR, NULL::NUMERIC, NULL::JSONB, NULL::TIMESTAMP,
                        NULL::BIGINT, NULL::VARCHAR, NULL::BIGINT, NULL::VARCHAR, NULL::BIGINT, 
                        NULL::VARCHAR, NULL::TEXT, NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::JSONB,
                        NULL::BIGINT, NULL::VARCHAR, NULL::VARCHAR, NULL::NUMERIC, NULL::BIGINT, 
                        NULL::VARCHAR, NULL::BIGINT, NULL::VARCHAR, NULL::VARCHAR, NULL::DATE, 
                        NULL::TEXT, NULL::JSONB, NULL::VARCHAR, NULL::DATE, NULL::JSONB, NULL::VARCHAR, 
                        NULL::NUMERIC, NULL::BIGINT, NULL::VARCHAR, NULL::VARCHAR, NULL::VARCHAR, 
                        NULL::VARCHAR, NULL::BIGINT, NULL::JSONB, NULL::BIGINT, NULL::VARCHAR, 
                        NULL::VARCHAR, NULL::VARCHAR, NULL::BIGINT, NULL::VARCHAR, NULL::VARCHAR,
                        NULL::BIGINT, NULL::VARCHAR, NULL::BIGINT, NULL::VARCHAR, NULL::VARCHAR,
                        NULL::VARCHAR, NULL::VARCHAR, NULL::BIGINT, NULL::VARCHAR,
                        NULL::JSONB, NULL::JSONB, NULL::JSONB , NULL::BIGINT, NULL::BIGINT, NULL::BIGINT,
                        NULL::BIGINT,  NULL::NUMERIC, NULL::DATE, NULL::NUMERIC, NULL::NUMERIC, NULL::JSONB;
                    RETURN;
                END IF;

                -- Validate asset item ID
                IF p_asset_item_id IS NOT NULL AND p_asset_item_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid asset item ID provided'::TEXT AS message,
                        NULL::BIGINT, NULL::BIGINT, NULL::VARCHAR, NULL::VARCHAR, NULL::VARCHAR, 
                        NULL::JSONB, NULL::VARCHAR, NULL::NUMERIC, NULL::JSONB, NULL::TIMESTAMP,
                        NULL::BIGINT, NULL::VARCHAR, NULL::BIGINT, NULL::VARCHAR, NULL::BIGINT, 
                        NULL::VARCHAR, NULL::TEXT, NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::JSONB,
                        NULL::BIGINT, NULL::VARCHAR, NULL::VARCHAR, NULL::NUMERIC, NULL::BIGINT, 
                        NULL::VARCHAR, NULL::BIGINT, NULL::VARCHAR, NULL::VARCHAR, NULL::DATE, 
                        NULL::TEXT, NULL::JSONB, NULL::VARCHAR, NULL::DATE, NULL::JSONB, NULL::VARCHAR, 
                        NULL::NUMERIC, NULL::BIGINT, NULL::VARCHAR, NULL::VARCHAR, NULL::VARCHAR, 
                        NULL::VARCHAR, NULL::BIGINT, NULL::JSONB, NULL::BIGINT, NULL::VARCHAR, 
                        NULL::VARCHAR, NULL::VARCHAR, NULL::BIGINT, NULL::VARCHAR, NULL::VARCHAR,
                        NULL::BIGINT, NULL::VARCHAR, NULL::BIGINT, NULL::VARCHAR, NULL::VARCHAR,
                        NULL::VARCHAR, NULL::VARCHAR, NULL::BIGINT, NULL::VARCHAR,
                        NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::BIGINT, NULL::BIGINT, NULL::BIGINT,
                        NULL::BIGINT,  NULL::NUMERIC, NULL::DATE, NULL::NUMERIC, NULL::NUMERIC, NULL::JSONB;
                    RETURN;
                END IF;

                -- Return the matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Asset items retrieved successfully'::TEXT AS message,
                    ai.id, 
                    a.id AS asset_id, 
                    a.name AS asset_name, 
                    ai.model_number, 
                    ai.serial_number,
                    ai.thumbnail_image,
                    ai.qr_code,
                    ai.item_value, 
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
                    ai.supplier AS supplier_id, 
                    s.name AS supplier_name, 
                    ai.purchase_order_number,
                    ai.purchase_cost, 
                    ai.purchase_type AS purchase_type_id, 
                    arat.name AS purchase_type_name, 
                    ai.received_condition::BIGINT AS received_condition_id, 
                    arct.name AS received_condition_name, 
                    ai.warranty, 
                    ai.warranty_exparing_at,
                    ai.other_purchase_details, 
                    ai.purchase_document,
                    ai.insurance_number, 
                    ai.insurance_exparing_at, 
                    ai.insurance_document,
                    ai.expected_life_time, 
                    ai.depreciation_value, 
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
                    ai.purchase_cost_currency_id,
                    pcc.code AS purchase_cost_currency_code,
                    pcc.symbol AS purchase_cost_currency_symbol,
                    ai.warrenty_condition_type_id,
                    wct.name AS warrenty_condition_type_name,
                    ai.item_value_currency_id,
                    ivc.code AS item_value_currency_code,
                    ivc.symbol AS item_value_currency_symbol,
                    ai.warrenty_usage_name,
                    ai.warranty_usage_value,
                    ai.manufacturer,
                    ai.depreciation_method::BIGINT AS depreciation_method_id,
                    dmt.name AS depreciation_method_name,
                    ai.consumables_kpi,
                    ai.maintenance_kpi,
                    ai.service_support_kpi,
                    ai.asset_requisition_item_id,
                    ai.asset_requisition_id,
                    ai.procurement_id,
                    ai.expected_life_time_unit,
                    ai.salvage_value,
                    ai.depreciation_start_date,
                    ai.decline_rate,
                    ai.total_estimated_units,
                    (
                        SELECT COALESCE(
                            jsonb_agg(
                                jsonb_build_object(
                                    'id', aic.consumable_id
                                )
                            ), '[]'::jsonb
                        )
                        FROM asset_item_consumables aic
                        INNER JOIN assets a_cons ON aic.consumable_id = a_cons.id
                        WHERE aic.asset_item_id = ai.id
                          AND aic.tenant_id = _tenant_id
                          AND aic.is_active = TRUE
                          AND aic.deleted_at IS NULL
                    ) AS asset_consumables
                FROM asset_items ai
                INNER JOIN assets a ON ai.asset_id = a.id
                INNER JOIN asset_categories ac ON a.category = ac.id
                INNER JOIN assets_types ast ON ac.assets_type = ast.id
                INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
                INNER JOIN suppliers s ON ai.supplier = s.id
                INNER JOIN asset_requisition_availability_types arat ON ai.purchase_type = arat.id
                LEFT JOIN asset_received_condition_types arct ON ai.received_condition::BIGINT = arct.id
                LEFT JOIN depreciation_method_table dmt ON ai.depreciation_method::BIGINT = dmt.id
                INNER JOIN users u ON ai.responsible_person = u.id
                INNER JOIN organization o ON ai.department = o.id
                INNER JOIN users ur ON ai.registered_by = ur.id
                INNER JOIN warrenty_condition_types wct ON ai.warrenty_condition_type_id = wct.id
                INNER JOIN currencies pcc ON ai.purchase_cost_currency_id = pcc.id
                INNER JOIN currencies ivc ON ai.item_value_currency_id = ivc.id
                WHERE (ai.id = p_asset_item_id OR p_asset_item_id IS NULL)
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
       DB::unprepared('DROP FUNCTION IF EXISTS get_asset_items( BIGINT, BIGINT);');
        
    }
};
