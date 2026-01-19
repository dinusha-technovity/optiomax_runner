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
        // 1. Drop every existing overload of get_employees
        DB::unprepared(<<<'SQL'
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_employees'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION get_employees(
                p_tenant_id BIGINT,
                p_employee_id INT DEFAULT NULL,
                p_page_no INT DEFAULT 1,
                p_page_size INT DEFAULT 10,
                p_search TEXT DEFAULT NULL,
                p_prefetch_mode TEXT DEFAULT 'both',  -- options: 'none', 'after', 'both'
                p_sort_by TEXT DEFAULT NULL
            )
            RETURNS JSON
            LANGUAGE plpgsql
            AS $$
            DECLARE
                employee_count INT;
                v_total_pages INT;
                v_data_prev JSON := '[]'::json;
                v_data_curr JSON := '[]'::json;
                v_data_next JSON := '[]'::json;
                v_offset_prev INT;
                v_offset_curr INT;
                v_offset_next INT;
                v_order_clause TEXT := 'ORDER BY u.id DESC'; -- default sorting

                -- Temporary variables for dynamic SQL
                inner_sql TEXT;
            BEGIN
                -- Determine order by clause
                CASE LOWER(TRIM(p_sort_by))
                    WHEN 'newest' THEN v_order_clause := 'ORDER BY u.id DESC';
                    WHEN 'oldest' THEN v_order_clause := 'ORDER BY u.id ASC';
                    WHEN 'az' THEN v_order_clause := 'ORDER BY u.name ASC NULLS LAST';
                    WHEN 'za' THEN v_order_clause := 'ORDER BY u.name DESC NULLS LAST';
                    ELSE v_order_clause := 'ORDER BY u.id DESC';
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
                SELECT COUNT(*) INTO employee_count
                FROM users u
                LEFT JOIN designations d ON d.id = u.designation_id AND d.deleted_at IS NULL
                WHERE u.tenant_id = p_tenant_id
                AND u.employee_account_enabled = TRUE
                AND u.user_account_enabled = TRUE
                AND (p_employee_id IS NULL OR u.id = p_employee_id)
                AND (
                    p_search IS NULL OR
                    u.name ILIKE '%' || p_search || '%' OR
                    u.employee_number ILIKE '%' || p_search || '%' OR
                    u.email ILIKE '%' || p_search || '%' OR
                    u.contact_no ILIKE '%' || p_search || '%' OR
                    d.designation ILIKE '%' || p_search || '%'
                );

                IF employee_count = 0 THEN
                    RETURN json_build_object(
                        'status', 'FAILURE',
                        'message', 'No employees found',
                        'meta', json_build_object(
                            'total_records', 0,
                            'total_pages', 0,
                            'current_page', p_page_no,
                            'page_size', p_page_size
                        ),
                        'data', json_build_object('previous', '[]'::json, 'current', '[]'::json, 'next', '[]'::json)
                    );
                END IF;

                v_total_pages := CEIL(employee_count::DECIMAL / p_page_size);

                -- Calculate offsets
                v_offset_curr := (p_page_no - 1) * p_page_size;
                v_offset_prev := GREATEST(v_offset_curr - p_page_size, 0);
                v_offset_next := p_page_no * p_page_size;

                -- Build inner query for current page
                inner_sql := format('
                    SELECT
                        u.id,
                        u.name AS employee_name,
                        u.employee_number,
                        u.email,
                        u.contact_no AS phone_number,
                        u.contact_no_code,
                        u.address,
                        u.created_at,
                        u.updated_at,
                        u.organization,
                        COALESCE((org.data->>''organizationName'')::TEXT, '''') AS department_name,
                        org.data AS department_data,
                        u.designation_id,
                        d.designation AS designation_name,
                        u.id AS user_id,
                        u.name AS user_name,
                        u.email AS user_email,
                        u.profile_image AS user_profile_image,
                        u.user_account_enabled,
                        u.employee_account_enabled
                    FROM users u
                    LEFT JOIN organization org ON org.id = u.organization AND org.deleted_at IS NULL
                    LEFT JOIN designations d ON d.id = u.designation_id AND d.deleted_at IS NULL
                    WHERE u.tenant_id = %L
                    AND u.employee_account_enabled = TRUE
                    AND (%L IS NULL OR u.id = %L)
                    AND (
                        %L IS NULL OR
                        u.name ILIKE ''%%'' || %L || ''%%'' OR
                        u.employee_number ILIKE ''%%'' || %L || ''%%'' OR
                        u.email ILIKE ''%%'' || %L || ''%%'' OR
                        u.contact_no ILIKE ''%%'' || %L || ''%%'' OR
                        d.designation ILIKE ''%%'' || %L || ''%%''
                    )
                    %s
                    LIMIT %s OFFSET %s
                ',
                    p_tenant_id, p_employee_id, p_employee_id,
                    p_search, p_search, p_search, p_search, p_search, p_search,
                    v_order_clause, p_page_size, v_offset_curr
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
                            u.id,
                            u.name AS employee_name,
                            u.employee_number,
                            u.email,
                            u.contact_no AS phone_number,
                            u.contact_no_code,
                            u.address,
                            u.created_at,
                            u.updated_at,
                            u.organization,
                            COALESCE((org.data->>''organizationName'')::TEXT, '''') AS department_name,
                            org.data AS department_data,
                            u.designation_id,
                            d.designation AS designation_name,
                            u.id AS user_id,
                            u.name AS user_name,
                            u.email AS user_email,
                            u.profile_image AS user_profile_image,
                            u.user_account_enabled,
                            u.employee_account_enabled
                        FROM users u
                        LEFT JOIN organization org ON org.id = u.organization AND org.deleted_at IS NULL
                        LEFT JOIN designations d ON d.id = u.designation_id AND d.deleted_at IS NULL
                        WHERE u.tenant_id = %L
                        AND u.employee_account_enabled = TRUE
                        AND (%L IS NULL OR u.id = %L)
                        AND (
                            %L IS NULL OR
                            u.name ILIKE ''%%'' || %L || ''%%'' OR
                            u.employee_number ILIKE ''%%'' || %L || ''%%'' OR
                            u.email ILIKE ''%%'' || %L || ''%%'' OR
                            u.contact_no ILIKE ''%%'' || %L || ''%%'' OR
                            d.designation ILIKE ''%%'' || %L || ''%%''
                        )
                        %s
                        LIMIT %s OFFSET %s
                    ',
                        p_tenant_id, p_employee_id, p_employee_id,
                        p_search, p_search, p_search, p_search, p_search, p_search,
                        v_order_clause, p_page_size, v_offset_prev
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
                            u.id,
                            u.name AS employee_name,
                            u.employee_number,
                            u.email,
                            u.contact_no AS phone_number,
                            u.contact_no_code,
                            u.address,
                            u.created_at,
                            u.updated_at,
                            u.organization,
                            COALESCE((org.data->>''organizationName'')::TEXT, '''') AS department_name,
                            org.data AS department_data,
                            u.designation_id,
                            d.designation AS designation_name,
                            u.id AS user_id,
                            u.name AS user_name,
                            u.email AS user_email,
                            u.profile_image AS user_profile_image,
                            u.user_account_enabled,
                            u.employee_account_enabled
                        FROM users u
                        LEFT JOIN organization org ON org.id = u.organization AND org.deleted_at IS NULL
                        LEFT JOIN designations d ON d.id = u.designation_id AND d.deleted_at IS NULL
                        WHERE u.tenant_id = %L
                        AND u.employee_account_enabled = TRUE
                        AND (%L IS NULL OR u.id = %L)
                        AND (
                            %L IS NULL OR
                            u.name ILIKE ''%%'' || %L || ''%%'' OR
                            u.employee_number ILIKE ''%%'' || %L || ''%%'' OR
                            u.email ILIKE ''%%'' || %L || ''%%'' OR
                            u.contact_no ILIKE ''%%'' || %L || ''%%'' OR
                            d.designation ILIKE ''%%'' || %L || ''%%''
                        )
                        %s
                        LIMIT %s OFFSET %s
                    ',
                        p_tenant_id, p_employee_id, p_employee_id,
                        p_search, p_search, p_search, p_search, p_search, p_search,
                        v_order_clause, p_page_size, v_offset_next
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
                    'message', 'Employees fetched successfully',
                    'meta', json_build_object(
                        'total_records', employee_count,
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
        DB::unprepared(<<<'SQL'
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_employees'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
        SQL);
    }
};
