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
            DO \$\$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_auth_user_scheduled_assets'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END\$\$;

            CREATE OR REPLACE FUNCTION get_auth_user_scheduled_assets(
                p_auth_user_id INT,
                p_tenant_id BIGINT,
                p_asset_item_id INT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                asset_item_id BIGINT,
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
                schedules JSONB,
                has_current_schedule BOOLEAN
            ) 
            LANGUAGE plpgsql
            AS \$\$
            DECLARE
                v_employee_exists BOOLEAN;
                v_schedule_count INT;
            BEGIN
                -- Check if user has an employee record
                SELECT EXISTS(
                    SELECT 1 FROM employees 
                    WHERE user_id = p_auth_user_id 
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL 
                    AND isactive = TRUE
                ) INTO v_employee_exists;

                -- If no employee record, return error
                IF NOT v_employee_exists THEN
                    RETURN QUERY SELECT
                        'ERROR'::TEXT,
                        'No employee record found for this user'::TEXT,
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT,
                        NULL::TEXT, NULL::TEXT, NULL::JSON, NULL::TEXT, NULL::NUMERIC, NULL::JSON,
                        NULL::TIMESTAMP, NULL::BIGINT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::BIGINT,
                        NULL::TEXT, NULL::TEXT, NULL::JSON, NULL::JSON, NULL::BIGINT, NULL::TEXT, NULL::TEXT,
                        NULL::NUMERIC, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::DATE, NULL::TEXT,
                        NULL::JSON, NULL::TEXT, NULL::DATE, NULL::JSON, NULL::TEXT, NULL::NUMERIC, NULL::BIGINT,
                        NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::BIGINT, NULL::JSON, NULL::BIGINT,
                        NULL::TEXT, NULL::TEXT, NULL::JSON, NULL::JSON, NULL::JSON, NULL::JSON, NULL::TEXT,
                        NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT,
                        NULL::JSON, NULL::JSON, NULL::JSON, NULL::JSONB;
                    RETURN;
                END IF;

                -- Return assets grouped with all their schedules for the logged-in user
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT,
                    'Scheduled assets retrieved successfully'::TEXT,
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
                    (
                        SELECT jsonb_agg(
                            jsonb_build_object(
                                'schedule_id', eas2.id,
                                'schedule_start_datetime', eas2.start_datetime,
                                'schedule_end_datetime', eas2.end_datetime,
                                'schedule_status', eas2.status,
                                'schedule_note', eas2."Note",
                                'schedule_created_at', eas2.created_at,
                                'schedule_updated_at', eas2.updated_at,
                                'recurring_enabled', eas2.recurring_enabled,
                                'recurring_pattern', eas2.recurring_pattern,
                                'recurring_config', eas2.recurring_config,
                                'occurrence_id', easo2.id,
                                'occurrence_start', easo2.occurrence_start,
                                'occurrence_end', easo2.occurrence_end,
                                'is_cancelled', easo2.is_cancelled,
                                'assigned_employees', (
                                    SELECT jsonb_agg(
                                        jsonb_build_object(
                                            'employee_id', asre2.employee_id,
                                            'employee_name', e2.employee_name,
                                            'employee_number', e2.employee_number,
                                            'user_id', e2.user_id,
                                            'user_name', ue2.name,
                                            'user_profile_image', ue2.profile_image
                                        )
                                    )
                                    FROM asset_schedule_related_employees asre2
                                    LEFT JOIN employees e2 ON asre2.employee_id = e2.id
                                    LEFT JOIN users ue2 ON e2.user_id = ue2.id
                                    WHERE asre2.asset_schedule_id = eas2.id
                                )
                            ) ORDER BY easo2.occurrence_start ASC NULLS LAST, eas2.start_datetime ASC
                        )
                        FROM employee_asset_scheduling eas2
                        INNER JOIN asset_schedule_related_employees asre_filter ON eas2.id = asre_filter.asset_schedule_id
                        INNER JOIN employees emp_filter ON asre_filter.employee_id = emp_filter.id
                        LEFT JOIN employee_asset_scheduling_occurrences easo2 ON eas2.id = easo2.schedule_id
                        WHERE eas2.asset_id = ai.id
                            AND emp_filter.user_id = p_auth_user_id
                            AND eas2.tenant_id = p_tenant_id
                            AND eas2.deleted_at IS NULL
                            AND eas2.is_active = TRUE
                            AND emp_filter.deleted_at IS NULL
                            AND emp_filter.isactive = TRUE
                    ) AS schedules,
                    -- Check if there's a current active schedule occurrence
                    EXISTS (
                        SELECT 1
                        FROM employee_asset_scheduling eas3
                        INNER JOIN asset_schedule_related_employees asre3 ON eas3.id = asre3.asset_schedule_id
                        INNER JOIN employees emp3 ON asre3.employee_id = emp3.id
                        INNER JOIN employee_asset_scheduling_occurrences easo3 ON eas3.id = easo3.schedule_id
                        WHERE eas3.asset_id = ai.id
                            AND emp3.user_id = p_auth_user_id
                            AND eas3.tenant_id = p_tenant_id
                            AND eas3.deleted_at IS NULL
                            AND eas3.is_active = TRUE
                            AND emp3.deleted_at IS NULL
                            AND emp3.isactive = TRUE
                            AND easo3.deleted_at IS NULL
                            AND easo3.isactive = TRUE
                            AND easo3.is_cancelled = FALSE
                            AND (easo3.occurrence_start AT TIME ZONE 'UTC' AT TIME ZONE 'Asia/Colombo') <= NOW()
                            AND (easo3.occurrence_end AT TIME ZONE 'UTC' AT TIME ZONE 'Asia/Colombo') >= NOW()
                    ) AS has_current_schedule
                FROM
                    asset_items ai
                INNER JOIN assets a ON ai.asset_id = a.id
                LEFT JOIN asset_categories ac ON a.category = ac.id
                LEFT JOIN assets_types ast ON ac.assets_type = ast.id
                LEFT JOIN asset_sub_categories assc ON a.sub_category = assc.id
                LEFT JOIN suppliers s ON ai.supplier = s.id
                LEFT JOIN asset_requisition_availability_types arat ON ai.purchase_type = arat.id
                LEFT JOIN users u ON ai.responsible_person = u.id
                LEFT JOIN organization o ON ai.department = o.id
                LEFT JOIN users ur ON ai.registered_by = ur.id
                WHERE
                    ai.id IN (
                        SELECT DISTINCT eas.asset_id
                        FROM employee_asset_scheduling eas
                        INNER JOIN asset_schedule_related_employees asre ON eas.id = asre.asset_schedule_id
                        INNER JOIN employees emp ON asre.employee_id = emp.id
                        WHERE emp.user_id = p_auth_user_id
                            AND eas.tenant_id = p_tenant_id
                            AND eas.deleted_at IS NULL
                            AND eas.is_active = TRUE
                            AND emp.deleted_at IS NULL
                            AND emp.isactive = TRUE
                    )
                    AND (p_asset_item_id IS NULL OR ai.id = p_asset_item_id)
                    AND ai.tenant_id = p_tenant_id
                    AND ai.deleted_at IS NULL
                    AND ai.isactive = TRUE
                ORDER BY
                    -- Current schedule assets first (has_current_schedule = TRUE)
                    (
                        EXISTS (
                            SELECT 1
                            FROM employee_asset_scheduling eas_sort
                            INNER JOIN asset_schedule_related_employees asre_sort ON eas_sort.id = asre_sort.asset_schedule_id
                            INNER JOIN employees emp_sort ON asre_sort.employee_id = emp_sort.id
                            INNER JOIN employee_asset_scheduling_occurrences easo_sort ON eas_sort.id = easo_sort.schedule_id
                            WHERE eas_sort.asset_id = ai.id
                                AND emp_sort.user_id = p_auth_user_id
                                AND eas_sort.tenant_id = p_tenant_id
                                AND eas_sort.deleted_at IS NULL
                                AND eas_sort.is_active = TRUE
                                AND emp_sort.deleted_at IS NULL
                                AND emp_sort.isactive = TRUE
                                AND easo_sort.deleted_at IS NULL
                                AND easo_sort.isactive = TRUE
                                AND easo_sort.is_cancelled = FALSE
                                AND easo_sort.occurrence_start <= NOW()
                                AND easo_sort.occurrence_end >= NOW()
                        )
                    ) DESC,
                    ai.id ASC;
            END;
            \$\$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_auth_user_scheduled_assets');
    }
};