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
                    DO $$
                DECLARE
                    r RECORD;
                BEGIN
                    FOR r IN
                        SELECT oid::regprocedure::text AS func_signature
                        FROM pg_proc
                        WHERE proname = 'get_asset_items_with_related_data'
                    LOOP
                        EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                    END LOOP;
                END$$;
                    

                    CREATE OR REPLACE FUNCTION get_asset_items_with_related_data(
                        _tenant_id BIGINT,
                        p_asset_item_id BIGINT DEFAULT NULL,
                        p_user_id BIGINT DEFAULT NULL
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

                        asset_requisition_data JSONB,
                        asset_group_data JSONB,
                        asset_requisition_item_data JSONB,
                        asset_procurenment_data JSONB,
                        supplier_data JSONB,
                        depreciation_method_data JSONB,
                        depreciation_history JSONB,
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
                                NULL::BIGINT,  NULL::NUMERIC, NULL::DATE, NULL::NUMERIC, NULL::NUMERIC, NULL::JSONB,
                                NULL::JSONB,NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::JSONB;
                            RETURN;
                        END IF;

                        -- Validate asset item ID (allow 0 to get all items)
                        IF p_asset_item_id IS NOT NULL AND p_asset_item_id < 0 THEN
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
                                NULL::BIGINT,  NULL::NUMERIC, NULL::DATE, NULL::NUMERIC, NULL::NUMERIC, NULL::JSONB,
                                NULL::JSONB,NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::JSONB;
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
                            -- Conditional: only show item_value if user is responsible person
                            CASE WHEN p_user_id IS NULL OR ai.responsible_person = p_user_id THEN ai.item_value ELSE NULL END AS item_value,
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
                            -- Conditional: only show supplier details if user is responsible person
                            CASE WHEN p_user_id IS NULL OR ai.responsible_person = p_user_id THEN ai.supplier ELSE NULL END AS supplier_id,
                            s.name AS supplier_name,
                            CASE WHEN p_user_id IS NULL OR ai.responsible_person = p_user_id THEN ai.purchase_order_number ELSE NULL END AS purchase_order_number,
                            -- Conditional: only show purchase_cost if user is responsible person
                            CASE WHEN p_user_id IS NULL OR ai.responsible_person = p_user_id THEN ai.purchase_cost ELSE NULL END AS purchase_cost,
                            ai.purchase_type AS purchase_type_id, 
                            arat.name AS purchase_type_name, 
                            ai.received_condition::BIGINT AS received_condition_id, 
                            arct.name AS received_condition_name, 
                            ai.warranty, 
                            ai.warranty_exparing_at,
                            CASE WHEN p_user_id IS NULL OR ai.responsible_person = p_user_id THEN ai.other_purchase_details ELSE NULL END AS other_purchase_details,
                            CASE WHEN p_user_id IS NULL OR ai.responsible_person = p_user_id THEN ai.purchase_document ELSE NULL END AS purchase_document,
                            ai.insurance_number, 
                            ai.insurance_exparing_at, 
                            CASE WHEN p_user_id IS NULL OR ai.responsible_person = p_user_id THEN ai.insurance_document ELSE NULL END AS insurance_document,
                            ai.expected_life_time, 
                            CASE WHEN p_user_id IS NULL OR ai.responsible_person = p_user_id THEN ai.depreciation_value ELSE NULL END AS depreciation_value,
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
                            CASE WHEN p_user_id IS NULL OR ai.responsible_person = p_user_id THEN ai.purchase_cost_currency_id ELSE NULL END AS purchase_cost_currency_id,
                            CASE WHEN p_user_id IS NULL OR ai.responsible_person = p_user_id THEN pcc.code ELSE NULL END AS purchase_cost_currency_code,
                            CASE WHEN p_user_id IS NULL OR ai.responsible_person = p_user_id THEN pcc.symbol ELSE NULL END AS purchase_cost_currency_symbol,
                            ai.warrenty_condition_type_id,
                            wct.name AS warrenty_condition_type_name,
                            CASE WHEN p_user_id IS NULL OR ai.responsible_person = p_user_id THEN ai.item_value_currency_id ELSE NULL END AS item_value_currency_id,
                            CASE WHEN p_user_id IS NULL OR ai.responsible_person = p_user_id THEN ivc.code ELSE NULL END AS item_value_currency_code,
                            CASE WHEN p_user_id IS NULL OR ai.responsible_person = p_user_id THEN ivc.symbol ELSE NULL END AS item_value_currency_symbol,
                            ai.warrenty_usage_name,
                            ai.warranty_usage_value,
                            ai.manufacturer,
                            ai.depreciation_method::BIGINT AS depreciation_method_id,
                            dmt.name AS depreciation_method_name,
                            ai.consumables_kpi,
                            ai.maintenance_kpi,
                            ai.service_support_kpi,
                            CASE WHEN p_user_id IS NULL OR ai.responsible_person = p_user_id THEN ai.asset_requisition_item_id ELSE NULL END AS asset_requisition_item_id,
                            CASE WHEN p_user_id IS NULL OR ai.responsible_person = p_user_id THEN ai.asset_requisition_id ELSE NULL END AS asset_requisition_id,
                            CASE WHEN p_user_id IS NULL OR ai.responsible_person = p_user_id THEN ai.procurement_id ELSE NULL END AS procurement_id,
                            ai.expected_life_time_unit,
                            CASE WHEN p_user_id IS NULL OR ai.responsible_person = p_user_id THEN ai.salvage_value ELSE NULL END AS salvage_value,
                            CASE WHEN p_user_id IS NULL OR ai.responsible_person = p_user_id THEN ai.depreciation_start_date ELSE NULL END AS depreciation_start_date,
                            CASE WHEN p_user_id IS NULL OR ai.responsible_person = p_user_id THEN ai.decline_rate ELSE NULL END AS decline_rate,
                            CASE WHEN p_user_id IS NULL OR ai.responsible_person = p_user_id THEN ai.total_estimated_units ELSE NULL END AS total_estimated_units,
                            -- Conditional: only show asset_requisition_data if user is responsible person
                            CASE 
                                WHEN (p_user_id IS NULL OR ai.responsible_person = p_user_id) AND ar.id IS NOT NULL THEN 
                                    jsonb_build_object(
                                        'id', ar.id,
                                        'isactive', ar.isactive,
                                        'tenant_id', ar.tenant_id,
                                        'created_at', ar.created_at,
                                        'deleted_at', ar.deleted_at,
                                        'updated_at', ar.updated_at,
                                        'requisition_by', ar.requisition_by,
                                        'requisition_id', ar.requisition_id,
                                        'requisition_date', ar.requisition_date,
                                        'requisition_status', ar.requisition_status,
                                        'requisition_by_user', jsonb_build_object(
                                            'id', arb.id,
                                            'name', arb.name,
                                            'email', arb.email,
                                            'profile_image', arb.profile_image
                                        )
                                    )
                                ELSE NULL
                            END AS asset_requisition_data,

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

                        -- to_jsonb(ari.*) AS asset_requisition_item_data,

                        -- Conditional: only show asset_requisition_item_data if user is responsible person
                        CASE 
                                WHEN (p_user_id IS NULL OR ai.responsible_person = p_user_id) AND ari.id IS NOT NULL THEN 
                                    jsonb_build_object(
                                        'id', ari.id,
                                        'budget', ari.budget,
                                        'budget_currency',ari.budget_currency,
                                        'reason' ,ari.reason,
                                        'item_name', ari.item_name,
                                        'required_date', ari.required_date
                                    )
                                ELSE NULL
                            END AS asset_requisition_item_data,

                        -- to_jsonb(procu.*) AS asset_procurenment_data,
                        -- Conditional: only show asset_procurenment_data if user is responsible person
                        CASE 
                                WHEN (p_user_id IS NULL OR ai.responsible_person = p_user_id) AND procu.id IS NOT NULL THEN 
                                    jsonb_build_object(
                                            'id', procu.id,
                                            'isactive', procu.isactive,
                                            'created_at', procu.created_at,
                                            'created_by', procu.created_by,
                                            'request_id', procu.request_id,
                                            'procurement_status', procu.procurement_status,
                                            'quotation_request_attempt_count', procu.quotation_request_attempt_count
                                    )
                                ELSE NULL
                            END AS asset_procurenment_data,

                        
                        -- to_jsonb(s.*) AS supplier_data
                        -- Conditional: only show supplier_data if user is responsible person
                            CASE 
                                WHEN (p_user_id IS NULL OR ai.responsible_person = p_user_id) AND s.id IS NOT NULL THEN 
                                    jsonb_build_object(
                                        'id', s.id,
                                            'city', s.city,
                                            'name', s.name,
                                            'email', s.email,
                                            'address', s.address,
                                            'country', s.country,
                                            'isactive', s.isactive,
                                            'contact_no', s.contact_no,
                                            'created_at', s.created_at,
                                            'updated_at', s.updated_at,
                                            'description', s.description,
                                            'supplier_fax', s.supplier_fax,
                                            'supplier_city', s.supplier_city,
                                            'supplier_type', s.supplier_type,
                                            'mobile_no_code', s.mobile_no_code,
                                            'contact_no_code', s.contact_no_code,
                                            'supplier_mobile', s.supplier_mobile,
                                            'supplier_rating', s.supplier_rating,
                                            'supplier_reg_no', s.supplier_reg_no,
                                            'supplier_tel_no', s.supplier_tel_no,
                                            'supplier_website', s.supplier_website,
                                            'supplier_reg_status', s.supplier_reg_status,
                                            'supplier_asset_classes', s.supplier_asset_classes,
                                            'supplier_business_name', s.supplier_business_name,
                                            'supplier_primary_email', s.supplier_primary_email,
                                            'supplier_secondary_email', s.supplier_secondary_email,
                                            'supplier_location_latitude', s.supplier_location_latitude,
                                            'supplier_location_longitude', s.supplier_location_longitude,
                                            'supplier_business_register_no', s.supplier_business_register_no
                                    )
                                ELSE NULL
                            END AS supplier_data,

                        -- Get latest depreciation schedule data
                        -- Conditional: only show depreciation_method_data if user is responsible person
                        CASE 
                            WHEN (p_user_id IS NULL OR ai.responsible_person = p_user_id) AND ads.id IS NOT NULL THEN 
                                to_jsonb(ads.*)
                            ELSE NULL
                        END AS depreciation_method_data,

                        -- Get depreciation history by year
                        -- Conditional: only show depreciation_history if user is responsible person
                        CASE
                            WHEN p_user_id IS NULL OR ai.responsible_person = p_user_id THEN
                                (
                                    SELECT jsonb_agg(
                                jsonb_build_object(
                                    'year', depreciation_year,
                                    'depreciate_cost', total_depreciation,
                                    'asset_item_currency_id', ai.item_value_currency_id
                                ) ORDER BY depreciation_year
                            )
                            FROM (
                                SELECT 
                                    EXTRACT(YEAR FROM ads_hist.record_date)::INT AS depreciation_year,
                                    SUM(ads_hist.depreciation_amount) AS total_depreciation
                                FROM asset_depreciation_schedules ads_hist
                                WHERE ads_hist.asset_item_id = ai.id
                                AND ads_hist.tenant_id = _tenant_id
                                GROUP BY EXTRACT(YEAR FROM ads_hist.record_date)
                                ORDER BY depreciation_year
                            ) yearly_depreciation
                                )
                            ELSE NULL
                        END AS depreciation_history,

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
                                    'est_cost', wo.est_cost,
                                    'created_at', wo.created_at,
                                    'updated_at', wo.updated_at,
                                    'technician', CASE 
                                        WHEN tech_user.id IS NOT NULL THEN
                                            jsonb_build_object(
                                                'id', tech_user.id,
                                                'name', tech_user.name,
                                                'email', tech_user.email
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
                        
                        LEFT JOIN asset_requisitions ar ON ai.asset_requisition_id = ar.id
                        LEFT JOIN asset_requisitions_items ari ON ai.asset_requisition_item_id = ari.id
                        LEFT JOIN procurements procu on ai.procurement_id = procu.id
                        LEFT JOIN users arb ON ar.requisition_by = arb.id
                        
                        -- Get the latest depreciation schedule for each asset item
                        LEFT JOIN LATERAL (
                            SELECT *
                            FROM asset_depreciation_schedules ads_inner
                            WHERE ads_inner.asset_item_id = ai.id
                            AND ads_inner.tenant_id = _tenant_id
                            ORDER BY ads_inner.calculated_at DESC, ads_inner.id DESC
                            LIMIT 1
                        ) ads ON true

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
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_items_with_related_data( BIGINT, BIGINT, BIGINT);');
    }
};
