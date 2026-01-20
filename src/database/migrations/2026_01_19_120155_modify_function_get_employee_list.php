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
                WHERE proname = 'get_employee_list'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_employee_list(
            p_tenant_id BIGINT,
            p_employee_id BIGINT DEFAULT NULL,
            p_page_no INT DEFAULT 1,
            p_page_size INT DEFAULT 10,
            p_search TEXT DEFAULT NULL,
            p_prefetch_mode TEXT DEFAULT 'both',
            p_sort_by TEXT DEFAULT 'newest'
        )
        RETURNS JSON
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_total_records INT := 0;
            v_total_pages INT := 0;
            v_offset_curr INT := 0;
            v_offset_prev INT := 0;
            v_offset_next INT := 0;
            v_data_curr JSON := '[]'::json;
            v_data_prev JSON := '[]'::json;
            v_data_next JSON := '[]'::json;
            v_base_query TEXT := '';
            v_search_clause TEXT := '';
            v_order_clause TEXT := 'ORDER BY u.id DESC';
            v_message TEXT := '';
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

            IF p_employee_id IS NOT NULL AND p_employee_id < 0 THEN
                RETURN json_build_object(
                    'status', 'FAILURE',
                    'message', 'Invalid employee ID provided',
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
                WHEN 'newest' THEN v_order_clause := 'ORDER BY u.id DESC';
                WHEN 'oldest' THEN v_order_clause := 'ORDER BY u.id ASC';
                WHEN 'az' THEN v_order_clause := 'ORDER BY u.name ASC NULLS LAST';
                WHEN 'za' THEN v_order_clause := 'ORDER BY u.name DESC NULLS LAST';
                ELSE v_order_clause := 'ORDER BY u.id DESC';
            END CASE;

            p_page_no := GREATEST(p_page_no, 1);
            p_page_size := GREATEST(p_page_size, 1);

            ----------------------------------------------------------------
            -- Search clause (name, email, phone with country code, designation)
            ----------------------------------------------------------------
            IF p_search IS NOT NULL AND LENGTH(TRIM(p_search)) > 0 THEN
                v_search_clause := format(
                    'AND (
                        u.name ILIKE %L 
                        OR u.email ILIKE %L 
                        OR u.employee_number ILIKE %L
                        OR u.contact_no ILIKE %L
                        OR d.designation ILIKE %L
                    )',
                    '%' || p_search || '%',
                    '%' || p_search || '%',
                    '%' || p_search || '%',
                    '%' || p_search || '%',
                    '%' || p_search || '%'
                );
            END IF;

            ----------------------------------------------------------------
            -- Build base query with joins
            ----------------------------------------------------------------
            v_base_query := format(
                'FROM users u
                LEFT JOIN designations d ON u.designation_id = d.id
                LEFT JOIN organization o ON u.organization = o.id
                WHERE u.employee_account_enabled = TRUE 
                AND u.tenant_id = %L 
                AND (%L IS NULL OR u.id = %L) 
                %s',
                p_tenant_id,
                p_employee_id,
                p_employee_id,
                v_search_clause
            );
            v_message := 'Employees fetched successfully';

            ----------------------------------------------------------------
            -- Count total records
            ----------------------------------------------------------------
            EXECUTE format('SELECT COUNT(*) %s', v_base_query)
            INTO v_total_records;

            IF v_total_records = 0 THEN
                RETURN json_build_object(
                    'status', 'SUCCESS',
                    'message', 'No employees found',
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
                    )
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
            EXECUTE format(
                'SELECT COALESCE(json_agg(t), ''[]''::JSON) FROM (
                    SELECT 
                        u.id,
                        u.name::TEXT AS employee_name,
                        u.employee_number::TEXT,
                        u.email::TEXT,
                        u.contact_no::TEXT AS phone_number,
                        NULL AS contact_no_code,
                        NULL AS country_code,
                        u.address::TEXT,
                        u.organization AS department_id,
                        COALESCE((o.data->>''organizationName'')::TEXT, '''') AS department_name,
                        u.designation_id,
                        d.designation::TEXT AS designation_name,
                        u.id AS user_id,
                        u.name::TEXT AS user_name,
                        u.email::TEXT AS user_email,
                        u.profile_image::TEXT AS user_profile_image,
                        u.created_at,
                        u.updated_at
                    %s 
                    %s 
                    LIMIT %s OFFSET %s
                ) t',
                v_base_query,
                v_order_clause,
                p_page_size,
                v_offset_curr
            )
            INTO v_data_curr;
            v_data_curr := COALESCE(v_data_curr, '[]'::JSON);

            ----------------------------------------------------------------
            -- Previous page (if prefetch)
            ----------------------------------------------------------------
            IF p_prefetch_mode IN ('both', 'previous') AND p_page_no > 1 THEN
                DECLARE
                    inner_sql TEXT;
                BEGIN
                    inner_sql := format(
                        'SELECT 
                            u.id,
                            u.name::TEXT AS employee_name,
                            u.employee_number::TEXT,
                            u.email::TEXT,
                            u.contact_no::TEXT AS phone_number,
                            NULL AS contact_no_code,
                            NULL AS country_code,
                            u.address::TEXT,
                            u.organization AS department_id,
                            COALESCE((o.data->>''organizationName'')::TEXT, '''') AS department_name,
                            u.designation_id,
                            d.designation::TEXT AS designation_name,
                            u.id AS user_id,
                            u.name::TEXT AS user_name,
                            u.email::TEXT AS user_email,
                            u.profile_image::TEXT AS user_profile_image,
                            u.created_at,
                            u.updated_at
                        %s 
                        %s 
                        LIMIT %s OFFSET %s',
                        v_base_query,
                        v_order_clause,
                        p_page_size,
                        v_offset_prev
                    );
                    EXECUTE format('SELECT json_agg(row_to_json(t)) FROM (%s) t', inner_sql)
                    INTO v_data_prev;
                    v_data_prev := COALESCE(v_data_prev, '[]'::json);
                END;
            END IF;

            ----------------------------------------------------------------
            -- Next page (if prefetch)
            ----------------------------------------------------------------
            IF p_prefetch_mode IN ('both', 'after') AND p_page_no < v_total_pages THEN
                DECLARE
                    inner_sql TEXT;
                BEGIN
                    inner_sql := format(
                        'SELECT 
                            u.id,
                            u.name::TEXT AS employee_name,
                            u.employee_number::TEXT,
                            u.email::TEXT,
                            u.contact_no::TEXT AS phone_number,
                            NULL AS contact_no_code,
                            NULL AS country_code,
                            u.address::TEXT,
                            u.organization AS department_id,
                            COALESCE((o.data->>''organizationName'')::TEXT, '''') AS department_name,
                            u.designation_id,
                            d.designation::TEXT AS designation_name,
                            u.id AS user_id,
                            u.name::TEXT AS user_name,
                            u.email::TEXT AS user_email,
                            u.profile_image::TEXT AS user_profile_image,
                            u.created_at,
                            u.updated_at
                        %s 
                        %s 
                        LIMIT %s OFFSET %s',
                        v_base_query,
                        v_order_clause,
                        p_page_size,
                        v_offset_next
                    );
                    EXECUTE format('SELECT json_agg(row_to_json(t)) FROM (%s) t', inner_sql)
                    INTO v_data_next;
                    v_data_next := COALESCE(v_data_next, '[]'::json);
                END;
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
        DB::unprepared('DROP FUNCTION IF EXISTS get_employee_list(BIGINT, BIGINT, INT, INT, TEXT, TEXT, TEXT)');
    }
};
