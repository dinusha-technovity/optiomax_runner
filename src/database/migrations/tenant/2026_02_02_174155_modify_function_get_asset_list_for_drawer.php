<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
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
                WHERE proname = 'get_asset_list_for_drawer'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_asset_list_for_drawer(
            p_tenant_id BIGINT,
            p_asset_item_id BIGINT DEFAULT NULL,
            p_user_id BIGINT DEFAULT NULL,
            p_action TEXT DEFAULT NULL,
            p_page_no INT DEFAULT 1,
            p_page_size INT DEFAULT 10,
            p_search TEXT DEFAULT NULL,
            p_prefetch_mode TEXT DEFAULT 'both',
            p_sort_by TEXT DEFAULT 'newest',
            p_category_id BIGINT DEFAULT NULL
        )
        RETURNS JSON
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_total_records INT := 0;
            v_total_pages INT := 0;
            v_data_prev JSON := '[]'::JSON;
            v_data_curr JSON := '[]'::JSON;
            v_data_next JSON := '[]'::JSON;
            v_offset_prev INT := 0;
            v_offset_curr INT := 0;
            v_offset_next INT := 0;
            v_order_clause TEXT := 'ORDER BY ai.id DESC';
            v_message TEXT := '';
            v_base_query TEXT := '';
            v_search_clause TEXT := '';
            v_category_clause TEXT := '';
            v_categories_data JSON := '[]'::JSON;
        BEGIN
            ----------------------------------------------------------------
            -- Validations
            ----------------------------------------------------------------
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN json_build_object(
                    'status', 'FAILURE',
                    'message', 'Invalid tenant ID provided',
                    'success', FALSE,
                    'data', json_build_object(
                        'previous', '[]',
                        'current', '[]',
                        'next', '[]'
                    ),
                    'pagination', json_build_object(
                        'current_page', 0,
                        'total_pages', 0,
                        'total_records', 0,
                        'page_size', p_page_size
                    )
                );
            END IF;

            ----------------------------------------------------------------
            -- Sorting Logic
            ----------------------------------------------------------------
            CASE LOWER(TRIM(p_sort_by))
                WHEN 'newest' THEN v_order_clause := 'ORDER BY ai.id DESC';
                WHEN 'oldest' THEN v_order_clause := 'ORDER BY ai.id ASC';
                WHEN 'az' THEN v_order_clause := 'ORDER BY a.name ASC NULLS LAST';
                WHEN 'za' THEN v_order_clause := 'ORDER BY a.name DESC NULLS LAST';
                ELSE v_order_clause := 'ORDER BY ai.id DESC';
            END CASE;

            p_page_no := GREATEST(p_page_no, 1);
            p_page_size := GREATEST(p_page_size, 1);

            ----------------------------------------------------------------
            -- Search clause
            ----------------------------------------------------------------
            IF p_search IS NOT NULL AND LENGTH(TRIM(p_search)) > 0 THEN
                v_search_clause := format(
                    'AND (a.name ILIKE %L OR ai.model_number ILIKE %L OR ai.serial_number ILIKE %L OR ai.asset_tag ILIKE %L)',
                    '%' || p_search || '%',
                    '%' || p_search || '%',
                    '%' || p_search || '%',
                    '%' || p_search || '%'
                );
            END IF;

            ----------------------------------------------------------------
            -- Category clause
            ----------------------------------------------------------------
            IF p_category_id IS NOT NULL AND p_category_id > 0 THEN
                v_category_clause := format('AND a.category = %s', p_category_id);
            END IF;

            ----------------------------------------------------------------
            -- Build base query based on action
            ----------------------------------------------------------------
            IF p_action = 'responsible' THEN
                v_base_query := format($b$
                    FROM asset_items ai
                    INNER JOIN assets a ON ai.asset_id = a.id
                    INNER JOIN asset_categories ac ON a.category = ac.id
                        INNER JOIN assets_types ast ON ac.assets_type = ast.id
                    INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
                    WHERE ai.responsible_person = %s
                        AND (ai.id = %L OR %L IS NULL OR %L <= 0)
                        AND ai.tenant_id = %L
                        AND ai.deleted_at IS NULL
                        AND ai.isactive = TRUE
                        AND a.deleted_at IS NULL
                        AND a.isactive = TRUE
                        %s
                        %s
                $b$, p_user_id, p_asset_item_id, p_asset_item_id, p_asset_item_id, p_tenant_id, v_search_clause, v_category_clause);
                v_message := 'Assets assigned to responsible_person retrieved successfully';

            ELSIF p_action = 'user_related' THEN
                v_base_query := format($b$
                    FROM maintenance_team_members mtm
                    JOIN maintenance_teams mt ON mt.id = mtm.team_id
                    JOIN maintenance_team_related_asset_groups mtag ON mtag.team_id = mt.id
                    JOIN assets a ON a.id = mtag.asset_group_id
                    JOIN asset_items ai ON ai.asset_id = a.id
                    INNER JOIN asset_categories ac ON a.category = ac.id
                    INNER JOIN assets_types ast ON ac.assets_type = ast.id
                    INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
                    WHERE mtm.user_id = %L
                        AND mtm.tenant_id = %L
                        AND mtm.deleted_at IS NULL AND mtm.isactive = TRUE
                        AND mt.deleted_at IS NULL AND mt.isactive = TRUE
                        AND mtag.deleted_at IS NULL AND mtag.isactive = TRUE
                        AND a.deleted_at IS NULL AND a.isactive = TRUE
                        AND ai.deleted_at IS NULL AND ai.isactive = TRUE
                        %s
                        %s
                $b$, p_user_id, p_tenant_id, v_search_clause, v_category_clause);
                v_message := 'User-related asset items retrieved successfully';

            ELSIF p_action = 'availability' THEN
                v_base_query := format($b$
                    FROM asset_items ai
                    INNER JOIN assets a ON ai.asset_id = a.id
                    INNER JOIN asset_categories ac ON a.category = ac.id
                    INNER JOIN assets_types ast ON ac.assets_type = ast.id
                    INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
                    WHERE (ai.id = %L OR %L IS NULL OR %L <= 0)
                        AND ai.tenant_id = %L
                        AND ai.deleted_at IS NULL
                        AND ai.isactive = TRUE
                        AND a.deleted_at IS NULL
                        AND a.isactive = TRUE
                        AND EXISTS (
                            SELECT 1
                            FROM asset_availability_schedules aas
                            WHERE aas.asset_id = ai.id
                            AND aas.tenant_id = %L
                            AND aas.deleted_at IS NULL
                            AND aas.is_active = TRUE
                            AND aas.publish_status = 'PUBLISHED'
                            AND EXISTS (
                                SELECT 1 FROM asset_availability_configurations cfg
                                WHERE cfg.asset_items_id = ai.id
                                AND cfg.visibility_id IN (1, 2, 3)
                            )
                            AND EXISTS (
                                SELECT 1
                                FROM asset_availability_schedule_occurrences o
                                WHERE o.schedule_id = aas.id
                                    AND o.deleted_at IS NULL
                                    AND o.is_cancelled = FALSE
                                    AND o.isactive = TRUE
                            )
                        )
                        %s
                        %s
                $b$, p_asset_item_id, p_asset_item_id, p_asset_item_id, p_tenant_id, p_tenant_id, v_search_clause, v_category_clause);
                v_message := 'Assets with published schedules and correct visibility assigned to responsible_person retrieved successfully';

            ELSIF p_action = 'availability_external' THEN
                v_base_query := format($b$
                    FROM asset_items ai
                    INNER JOIN assets a ON ai.asset_id = a.id
                    INNER JOIN asset_categories ac ON a.category = ac.id
                    INNER JOIN assets_types ast ON ac.assets_type = ast.id
                    INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
                    WHERE (ai.id = %L OR %L IS NULL OR %L <= 0)
                        AND ai.tenant_id = %L
                        AND ai.deleted_at IS NULL
                        AND ai.isactive = TRUE
                        AND a.deleted_at IS NULL
                        AND a.isactive = TRUE
                        AND EXISTS (
                            SELECT 1
                            FROM asset_availability_schedules aas
                            WHERE aas.asset_id = ai.id
                            AND aas.tenant_id = %L
                            AND aas.deleted_at IS NULL
                            AND aas.is_active = TRUE
                            AND aas.publish_status = 'PUBLISHED'
                            AND EXISTS (
                                SELECT 1 FROM asset_availability_configurations cfg
                                WHERE cfg.asset_items_id = ai.id
                                AND cfg.visibility_id IN (2, 3)
                            )
                            AND EXISTS (
                                SELECT 1
                                FROM asset_availability_schedule_occurrences o
                                WHERE o.schedule_id = aas.id
                                    AND o.deleted_at IS NULL
                                    AND o.is_cancelled = FALSE
                                    AND o.isactive = TRUE
                            )
                        )
                        %s
                        %s
                $b$, p_asset_item_id, p_asset_item_id, p_asset_item_id, p_tenant_id, p_tenant_id, v_search_clause, v_category_clause);
                v_message := 'Assets with published schedules and correct visibility assigned to responsible_person retrieved successfully';

            ELSIF p_action = 'availability_internal' THEN
                v_base_query := format($b$
                    FROM asset_items ai
                    INNER JOIN assets a ON ai.asset_id = a.id
                    INNER JOIN asset_categories ac ON a.category = ac.id
                    INNER JOIN assets_types ast ON ac.assets_type = ast.id
                    INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
                    WHERE (ai.id = %L OR %L IS NULL OR %L <= 0)
                        AND ai.tenant_id = %L
                        AND ai.deleted_at IS NULL
                        AND ai.isactive = TRUE
                        AND a.deleted_at IS NULL
                        AND a.isactive = TRUE
                        AND EXISTS (
                            SELECT 1
                            FROM asset_availability_schedules aas
                            WHERE aas.asset_id = ai.id
                            AND aas.tenant_id = %L
                            AND aas.deleted_at IS NULL
                            AND aas.is_active = TRUE
                            AND aas.publish_status = 'PUBLISHED'
                            AND EXISTS (
                                SELECT 1 FROM asset_availability_configurations cfg
                                WHERE cfg.asset_items_id = ai.id
                                AND cfg.visibility_id IN (1, 3)
                            )
                            AND EXISTS (
                                SELECT 1
                                FROM asset_availability_schedule_occurrences o
                                WHERE o.schedule_id = aas.id
                                    AND o.deleted_at IS NULL
                                    AND o.is_cancelled = FALSE
                                    AND o.isactive = TRUE
                            )
                        )
                        %s
                        %s
                $b$, p_asset_item_id, p_asset_item_id, p_asset_item_id, p_tenant_id, p_tenant_id, v_search_clause, v_category_clause);
                v_message := 'Assets with published schedules and correct visibility assigned to responsible_person retrieved successfully';


               ELSIF p_action = 'booking_availability' THEN
                v_base_query := format($b$                     FROM asset_items ai
                    INNER JOIN assets a ON ai.asset_id = a.id
                    INNER JOIN asset_categories ac ON a.category = ac.id
                    INNER JOIN assets_types ast ON ac.assets_type = ast.id
                    INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
                    WHERE ai.booking_availability = TRUE
                        AND ai.responsible_person = %s
                        AND (ai.id = %L OR %L IS NULL OR %L <= 0)
                        AND ai.tenant_id = %L
                        AND ai.deleted_at IS NULL
                        AND ai.isactive = TRUE
                        AND a.deleted_at IS NULL
                        AND a.isactive = TRUE
                        %s
                        %s
                $b$,
                    p_user_id,
                    p_asset_item_id,
                    p_asset_item_id,
                    p_asset_item_id,
                    p_tenant_id,
                    v_search_clause,
                    v_category_clause
                );

                v_message := 'Assets marked with booking availability retrieved successfully';

            ELSIF p_action = 'my_scheduled_assets' THEN
                v_base_query := format($b$
                    FROM asset_items ai
                    INNER JOIN assets a ON ai.asset_id = a.id
                    INNER JOIN asset_categories ac ON a.category = ac.id
                    INNER JOIN assets_types ast ON ac.assets_type = ast.id
                    INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
                    WHERE (ai.id = %L OR %L IS NULL OR %L <= 0)
                        AND ai.tenant_id = %L
                        AND ai.deleted_at IS NULL
                        AND ai.isactive = TRUE
                        AND a.deleted_at IS NULL
                        AND a.isactive = TRUE
                        AND EXISTS (
                            SELECT 1
                            FROM employee_asset_scheduling eas
                            INNER JOIN asset_schedule_related_employees asre ON eas.id = asre.asset_schedule_id
                            INNER JOIN users emp ON asre.employee_id = emp.id
                            WHERE eas.asset_id = ai.id
                                AND emp.id = %L
                                AND eas.tenant_id = %L
                                AND eas.deleted_at IS NULL
                                AND eas.is_active = TRUE
                                AND emp.employee_account_enabled = TRUE
                        )
                        %s
                        %s
                $b$, p_asset_item_id, p_asset_item_id, p_asset_item_id, p_tenant_id, p_user_id, p_tenant_id, v_search_clause, v_category_clause);
                v_message := 'My scheduled assets retrieved successfully';

            ELSE
                -- Default case: fetch normally
                v_base_query := format($b$
                    FROM asset_items ai
                    INNER JOIN assets a ON ai.asset_id = a.id
                    INNER JOIN asset_categories ac ON a.category = ac.id
                    INNER JOIN assets_types ast ON ac.assets_type = ast.id
                    INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
                    WHERE (%L IS NULL OR %L <= 0 OR ai.id = %L)
                        AND ai.tenant_id = %L
                        AND ai.deleted_at IS NULL
                        AND ai.isactive = TRUE
                        AND a.deleted_at IS NULL
                        AND a.isactive = TRUE
                        %s
                        %s
                $b$, p_asset_item_id, p_asset_item_id, p_asset_item_id, p_tenant_id, v_search_clause, v_category_clause);
                v_message := 'Asset items retrieved successfully';
            END IF;

            ----------------------------------------------------------------
            -- Count total records
            ----------------------------------------------------------------
            EXECUTE format($s$
                SELECT COUNT(DISTINCT ai.id)
                %s
            $s$, v_base_query)
            INTO v_total_records;

            ----------------------------------------------------------------
            -- Categories list (A → Z) based on current filters
            ----------------------------------------------------------------
        SELECT COALESCE(
            json_agg(
                json_build_object(
                    'category_id', ac.id,
                    'category_name', ac.name
                )
                ORDER BY ac.name ASC
            ),
            '[]'::JSON
        ) INTO v_categories_data
        FROM (
            SELECT DISTINCT ac.id, ac.name
            FROM asset_categories ac
            JOIN assets a ON a.category = ac.id
            JOIN asset_items ai ON ai.asset_id = a.id
            WHERE ai.tenant_id = p_tenant_id
            AND ai.deleted_at IS NULL
            AND ai.isactive = TRUE
            AND a.deleted_at IS NULL
            AND a.isactive = TRUE
            AND ac.deleted_at IS NULL
            AND ac.isactive = TRUE
        ) ac;
            v_categories_data := COALESCE(v_categories_data, '[]'::JSON);

            IF v_total_records = 0 THEN
                RETURN json_build_object(
                    'status', 'SUCCESS',
                    'message', 'No asset items found',
                    'success', TRUE,
                    'data', json_build_object(
                        'previous', '[]',
                        'current', '[]',
                        'next', '[]'
                    ),
                    'pagination', json_build_object(
                        'current_page', p_page_no,
                        'total_pages', 0,
                        'total_records', 0,
                        'page_size', p_page_size
                    ),
                    'categories', v_categories_data
                );
            END IF;

            ----------------------------------------------------------------
            -- Pagination
            ----------------------------------------------------------------
            v_total_pages := CEIL(v_total_records::DECIMAL / p_page_size);
            v_offset_curr := (p_page_no - 1) * p_page_size;
            v_offset_prev := GREATEST(v_offset_curr - p_page_size, 0);
            v_offset_next := p_page_no * p_page_size;

            ----------------------------------------------------------------
            -- Current page data
            ----------------------------------------------------------------
            EXECUTE format($s$
                SELECT COALESCE(json_agg(t), '[]'::JSON)
                FROM (
                    SELECT
                        'SUCCESS'::TEXT as status,
                        %L::TEXT as message,
                        ai.id,
                        a.id as asset_id,
                        a.name as asset_name,
                        ai.model_number,
                        ai.serial_number,
                        ai.thumbnail_image,
                        ai.qr_code,
                        ai.asset_tag,
                        ac.assets_type as assets_type_id,
                        ast.name as assets_type_name,
                        a.category as category_id,
                        ac.name as category_name,
                        a.sub_category as sub_category_id,
                        assc.name as sub_category_name
                    %s
                    GROUP BY ai.id, a.id, a.name, ai.model_number, ai.serial_number, ai.thumbnail_image, ai.qr_code, ai.asset_tag,
                            ac.assets_type, ast.name, a.category, ac.name, a.sub_category, assc.name
                    %s
                    LIMIT %s OFFSET %s
                ) t
            $s$, v_message, v_base_query, v_order_clause, p_page_size, v_offset_curr)
            INTO v_data_curr;
            v_data_curr := COALESCE(v_data_curr, '[]'::JSON);

            ----------------------------------------------------------------
            -- Previous page (if prefetch)
            ----------------------------------------------------------------
            IF p_prefetch_mode = 'both' AND p_page_no > 1 THEN
                EXECUTE format($s$
                    SELECT COALESCE(json_agg(t), '[]'::JSON)
                    FROM (
                        SELECT
                            'SUCCESS'::TEXT as status,
                            %L::TEXT as message,
                            ai.id,
                            a.id as asset_id,
                            a.name as asset_name,
                            ai.model_number,
                            ai.serial_number,
                            ai.thumbnail_image,
                            ai.qr_code,
                            ai.asset_tag,
                            ac.assets_type as assets_type_id,
                            ast.name as assets_type_name,
                            a.category as category_id,
                            ac.name as category_name,
                            a.sub_category as sub_category_id,
                            assc.name as sub_category_name
                        %s
                        GROUP BY ai.id, a.id, a.name, ai.model_number, ai.serial_number, ai.thumbnail_image, ai.qr_code, ai.asset_tag,
                                ac.assets_type, ast.name, a.category, ac.name, a.sub_category, assc.name
                        %s
                        LIMIT %s OFFSET %s
                    ) t
                $s$, v_message, v_base_query, v_order_clause, p_page_size, v_offset_prev)
                INTO v_data_prev;
                v_data_prev := COALESCE(v_data_prev, '[]'::JSON);
            END IF;

            ----------------------------------------------------------------
            -- Next page (if prefetch)
            ----------------------------------------------------------------
            IF (p_prefetch_mode = 'both' OR p_prefetch_mode = 'after') AND p_page_no < v_total_pages THEN
                EXECUTE format($s$
                    SELECT COALESCE(json_agg(t), '[]'::JSON)
                    FROM (
                        SELECT
                            'SUCCESS'::TEXT as status,
                            %L::TEXT as message,
                            ai.id,
                            a.id as asset_id,
                            a.name as asset_name,
                            ai.model_number,
                            ai.serial_number,
                            ai.thumbnail_image,
                            ai.qr_code,
                            ai.asset_tag,
                            ac.assets_type as assets_type_id,
                            ast.name as assets_type_name,
                            a.category as category_id,
                            ac.name as category_name,
                            a.sub_category as sub_category_id,
                            assc.name as sub_category_name
                        %s
                        GROUP BY ai.id, a.id, a.name, ai.model_number, ai.serial_number, ai.thumbnail_image, ai.qr_code, ai.asset_tag,
                                ac.assets_type, ast.name, a.category, ac.name, a.sub_category, assc.name
                        %s
                        LIMIT %s OFFSET %s
                    ) t
                $s$, v_message, v_base_query, v_order_clause, p_page_size, v_offset_next)
                INTO v_data_next;
                v_data_next := COALESCE(v_data_next, '[]'::JSON);
            END IF;

            ----------------------------------------------------------------
            -- Return final result
            ----------------------------------------------------------------
            RETURN json_build_object(
                'status', 'SUCCESS',
                'message', v_message,
                'success', TRUE,
                'data', json_build_object(
                    'previous', v_data_prev,
                    'current', v_data_curr,
                    'next', v_data_next
                ),
                'pagination', json_build_object(
                    'current_page', p_page_no,
                    'total_pages', v_total_pages,
                    'total_records', v_total_records,
                    'page_size', p_page_size
                ),
                'categories', v_categories_data
            );

        END;
        $$;

        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_list_for_drawer(BIGINT, BIGINT, BIGINT, TEXT, INT, INT, TEXT, TEXT, TEXT, BIGINT);');
    }
};