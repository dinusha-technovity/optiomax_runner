<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_asset_items'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_asset_items(
                p_tenant_id BIGINT,
                p_asset_item_id BIGINT DEFAULT NULL,
                p_page_no INT DEFAULT 1,
                p_page_size INT DEFAULT 10,
                p_search TEXT DEFAULT NULL,
                p_asset_id BIGINT DEFAULT NULL,
                p_prefetch_mode TEXT DEFAULT 'both',  -- options: 'none', 'after', 'both'
                p_sort_by TEXT DEFAULT NULL
        )
        RETURNS JSON
        LANGUAGE plpgsql
        AS $$
        DECLARE
            asset_item_count INT;
            v_total_pages INT;
            v_data_prev JSON := '[]'::json;
            v_data_curr JSON := '[]'::json;
            v_data_next JSON := '[]'::json;
            v_offset_prev INT;
            v_offset_curr INT;
            v_offset_next INT;
            v_order_clause TEXT := 'ORDER BY ai.id DESC'; -- default sorting

            -- Temporary variables for dynamic SQL
            inner_sql TEXT;
        BEGIN
            -- Determine order by clause
            CASE LOWER(TRIM(p_sort_by))
                WHEN 'newest' THEN v_order_clause := 'ORDER BY ai.id DESC';
                WHEN 'oldest' THEN v_order_clause := 'ORDER BY ai.id ASC';
                WHEN 'az' THEN v_order_clause := 'ORDER BY a.name ASC NULLS LAST';
                WHEN 'za' THEN v_order_clause := 'ORDER BY a.name DESC NULLS LAST';
                ELSE v_order_clause := 'ORDER BY ai.id DESC';
            END CASE;

            -- Validate tenant
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN json_build_object(
                    'status', 'FAILURE',
                    'message', 'Invalid tenant ID',
                    'data', json_build_object('previous', '[]'::json, 'current', '[]'::json, 'next', '[]'::json)
                );
            END IF;

            p_page_no := GREATEST(p_page_no, 1);
            p_page_size := GREATEST(p_page_size, 1);

            -- Count total records
            SELECT COUNT(*) INTO asset_item_count
            FROM asset_items ai
            INNER JOIN assets a ON ai.asset_id = a.id
            WHERE ai.tenant_id = p_tenant_id
            AND ai.deleted_at IS NULL
            AND ai.isactive = TRUE
            AND (p_asset_item_id IS NULL OR ai.id = p_asset_item_id)
            AND (p_asset_id IS NULL OR a.id = p_asset_id)
            AND (
                    p_search IS NULL OR
                    a.name ILIKE '%' || p_search || '%' OR
                    ai.model_number ILIKE '%' || p_search || '%' OR
                    ai.serial_number ILIKE '%' || p_search || '%' OR
                    ai.asset_tag ILIKE '%' || p_search || '%'
                );

            -- Changed: Return SUCCESS even when no records found
            IF asset_item_count = 0 THEN
                RETURN json_build_object(
                    'status', 'SUCCESS',
                    'message', 'No asset items found',
                    'meta', json_build_object(
                        'total_records', 0,
                        'total_pages', 0,
                        'current_page', p_page_no,
                        'page_size', p_page_size,
                        'prefetch_mode', p_prefetch_mode,
                        'sort_by', p_sort_by
                    ),
                    'data', json_build_object('previous', '[]'::json, 'current', '[]'::json, 'next', '[]'::json)
                );
            END IF;

            v_total_pages := CEIL(asset_item_count::DECIMAL / p_page_size);

            -- Calculate offsets
            v_offset_curr := (p_page_no - 1) * p_page_size;
            v_offset_prev := GREATEST(v_offset_curr - p_page_size, 0);
            v_offset_next := p_page_no * p_page_size;

            -- Build inner query for current page
            inner_sql := format('
                SELECT
                    ai.id,
                    ai.booking_availability,
                    CASE 
                        WHEN ai.assignee_type_id IS NOT NULL THEN
                            json_build_object(
                                ''id'', ai.assignee_type_id,
                                ''name'', at.name
                            )
                        ELSE NULL
                    END AS assignee_type,
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
                                    ''id'', aic.consumable_id
                                )
                            ), ''[]''::jsonb
                        )
                        FROM asset_item_consumables aic
                        WHERE aic.asset_item_id = ai.id
                          AND aic.tenant_id = ai.tenant_id
                          AND aic.is_active = TRUE
                          AND aic.deleted_at IS NULL
                    ) AS asset_consumables
                FROM asset_items ai
                INNER JOIN assets a ON ai.asset_id = a.id
                LEFT JOIN assignee_types at ON ai.assignee_type_id = at.id
                INNER JOIN asset_categories ac ON a.category = ac.id
                LEFT JOIN assets_types ast ON ac.assets_type = ast.id
                INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
                LEFT JOIN suppliers s ON ai.supplier = s.id
                INNER JOIN asset_requisition_availability_types arat ON ai.purchase_type = arat.id
                LEFT JOIN asset_received_condition_types arct ON ai.received_condition::BIGINT = arct.id
                LEFT JOIN depreciation_method_table dmt ON ai.depreciation_method::BIGINT = dmt.id
                LEFT JOIN users u ON ai.responsible_person = u.id
                LEFT JOIN organization o ON ai.department = o.id
                INNER JOIN users ur ON ai.registered_by = ur.id
                LEFT JOIN warrenty_condition_types wct ON ai.warrenty_condition_type_id = wct.id
                INNER JOIN currencies pcc ON ai.purchase_cost_currency_id = pcc.id
                INNER JOIN currencies ivc ON ai.item_value_currency_id = ivc.id
                WHERE ai.tenant_id = %L
                AND ai.deleted_at IS NULL
                AND ai.isactive = TRUE
                AND (%L IS NULL OR ai.id = %L)
                AND (%L IS NULL OR a.id = %L)
                AND (
                        %L IS NULL OR
                        a.name ILIKE ''%%'' || %L || ''%%'' OR
                        ai.model_number ILIKE ''%%'' || %L || ''%%'' OR
                        ai.serial_number ILIKE ''%%'' || %L || ''%%'' OR
                        ai.asset_tag ILIKE ''%%'' || %L || ''%%''
                    )
                %s
                LIMIT %s OFFSET %s
            ',
                p_tenant_id,
                p_asset_item_id, p_asset_item_id,
                p_asset_id, p_asset_id,
                p_search, p_search, p_search, p_search, p_search,
                v_order_clause,
                p_page_size, v_offset_curr
            );

            EXECUTE 'SELECT json_agg(row_to_json(t)) FROM (' || inner_sql || ') t'
            INTO v_data_curr;

            v_data_curr := COALESCE(v_data_curr, '[]'::json);

            -------------------------------------------------------------------
            -- PREVIOUS PAGE (if needed)
            -------------------------------------------------------------------
            IF p_prefetch_mode = 'both' AND p_page_no > 1 THEN
                inner_sql := format('
                    SELECT
                        ai.id,
                        ai.booking_availability,
                        CASE 
                            WHEN ai.assignee_type_id IS NOT NULL THEN
                                json_build_object(
                                    ''id'', ai.assignee_type_id,
                                    ''name'', at.name
                                )
                            ELSE NULL
                        END AS assignee_type,
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
                                        ''id'', aic.consumable_id
                                    )
                                ), ''[]''::jsonb
                            )
                            FROM asset_item_consumables aic
                            WHERE aic.asset_item_id = ai.id
                              AND aic.tenant_id = ai.tenant_id
                              AND aic.is_active = TRUE
                              AND aic.deleted_at IS NULL
                        ) AS asset_consumables
                    FROM asset_items ai
                    INNER JOIN assets a ON ai.asset_id = a.id
                    LEFT JOIN assignee_types at ON ai.assignee_type_id = at.id
                    INNER JOIN asset_categories ac ON a.category = ac.id
                    LEFT JOIN assets_types ast ON ac.assets_type = ast.id
                    INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
                    LEFT JOIN suppliers s ON ai.supplier = s.id
                    INNER JOIN asset_requisition_availability_types arat ON ai.purchase_type = arat.id
                    LEFT JOIN asset_received_condition_types arct ON ai.received_condition::BIGINT = arct.id
                    LEFT JOIN depreciation_method_table dmt ON ai.depreciation_method::BIGINT = dmt.id
                    LEFT JOIN users u ON ai.responsible_person = u.id
                    LEFT JOIN organization o ON ai.department = o.id
                    INNER JOIN users ur ON ai.registered_by = ur.id
                    LEFT JOIN warrenty_condition_types wct ON ai.warrenty_condition_type_id = wct.id
                    INNER JOIN currencies pcc ON ai.purchase_cost_currency_id = pcc.id
                    INNER JOIN currencies ivc ON ai.item_value_currency_id = ivc.id
                    WHERE ai.tenant_id = %L
                    AND ai.deleted_at IS NULL
                    AND ai.isactive = TRUE
                    AND (%L IS NULL OR ai.id = %L)
                    AND (%L IS NULL OR a.id = %L)
                    AND (
                            %L IS NULL OR
                            a.name ILIKE ''%%'' || %L || ''%%'' OR
                            ai.model_number ILIKE ''%%'' || %L || ''%%'' OR
                            ai.serial_number ILIKE ''%%'' || %L || ''%%'' OR
                            ai.asset_tag ILIKE ''%%'' || %L || ''%%''
                        )
                    %s
                    LIMIT %s OFFSET %s
                ',
                    p_tenant_id,
                    p_asset_item_id, p_asset_item_id,
                    p_asset_id, p_asset_id,
                    p_search, p_search, p_search, p_search, p_search,
                    v_order_clause,
                    p_page_size, v_offset_prev
                );

                EXECUTE 'SELECT json_agg(row_to_json(t)) FROM (' || inner_sql || ') t'
                INTO v_data_prev;

                v_data_prev := COALESCE(v_data_prev, '[]'::json);
            END IF;

            -------------------------------------------------------------------
            -- NEXT PAGE (if needed)
            -------------------------------------------------------------------
            IF p_prefetch_mode IN ('both', 'after') AND p_page_no < v_total_pages THEN
                inner_sql := format('
                    SELECT
                        ai.id,
                        ai.booking_availability,
                        CASE 
                            WHEN ai.assignee_type_id IS NOT NULL THEN
                                json_build_object(
                                    ''id'', ai.assignee_type_id,
                                    ''name'', at.name
                                )
                            ELSE NULL
                        END AS assignee_type,
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
                                        ''id'', aic.consumable_id
                                    )
                                ), ''[]''::jsonb
                            )
                            FROM asset_item_consumables aic
                            WHERE aic.asset_item_id = ai.id
                              AND aic.tenant_id = ai.tenant_id
                              AND aic.is_active = TRUE
                              AND aic.deleted_at IS NULL
                        ) AS asset_consumables
                    FROM asset_items ai
                    INNER JOIN assets a ON ai.asset_id = a.id
                    LEFT JOIN assignee_types at ON ai.assignee_type_id = at.id
                    INNER JOIN asset_categories ac ON a.category = ac.id
                    LEFT JOIN assets_types ast ON ac.assets_type = ast.id
                    INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
                    LEFT JOIN suppliers s ON ai.supplier = s.id
                    INNER JOIN asset_requisition_availability_types arat ON ai.purchase_type = arat.id
                    LEFT JOIN asset_received_condition_types arct ON ai.received_condition::BIGINT = arct.id
                    LEFT JOIN depreciation_method_table dmt ON ai.depreciation_method::BIGINT = dmt.id
                    LEFT JOIN users u ON ai.responsible_person = u.id
                    LEFT JOIN organization o ON ai.department = o.id
                    INNER JOIN users ur ON ai.registered_by = ur.id
                    LEFT JOIN warrenty_condition_types wct ON ai.warrenty_condition_type_id = wct.id
                    INNER JOIN currencies pcc ON ai.purchase_cost_currency_id = pcc.id
                    INNER JOIN currencies ivc ON ai.item_value_currency_id = ivc.id
                    WHERE ai.tenant_id = %L
                    AND ai.deleted_at IS NULL
                    AND ai.isactive = TRUE
                    AND (%L IS NULL OR ai.id = %L)
                    AND (%L IS NULL OR a.id = %L)
                    AND (
                            %L IS NULL OR
                            a.name ILIKE ''%%'' || %L || ''%%'' OR
                            ai.model_number ILIKE ''%%'' || %L || ''%%'' OR
                            ai.serial_number ILIKE ''%%'' || %L || ''%%'' OR
                            ai.asset_tag ILIKE ''%%'' || %L || ''%%''
                        )
                    %s
                    LIMIT %s OFFSET %s
                ',
                    p_tenant_id,
                    p_asset_item_id, p_asset_item_id,
                    p_asset_id, p_asset_id,
                    p_search, p_search, p_search, p_search, p_search,
                    v_order_clause,
                    p_page_size, v_offset_next
                );

                EXECUTE 'SELECT json_agg(row_to_json(t)) FROM (' || inner_sql || ') t'
                INTO v_data_next;

                v_data_next := COALESCE(v_data_next, '[]'::json);
            END IF;

            -------------------------------------------------------------------
            -- FINAL JSON OUTPUT
            -------------------------------------------------------------------
            RETURN json_build_object(
                'status', 'SUCCESS',
                'message', 'Asset items fetched successfully',
                'meta', json_build_object(
                    'total_records', asset_item_count,
                    'total_pages', v_total_pages,
                    'current_page', p_page_no,
                    'page_size', p_page_size,
                    'prefetch_mode', p_prefetch_mode,
                    'sort_by', p_sort_by
                ),
                'data', json_build_object(
                    'previous', v_data_prev,
                    'current', v_data_curr,
                    'next', v_data_next
                )
            );
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed - function will be recreated by previous migration if rolled back
    }
};