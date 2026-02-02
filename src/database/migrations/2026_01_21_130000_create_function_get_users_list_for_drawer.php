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
                WHERE proname = 'get_users_list_for_drawer'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_users_list_for_drawer(
            p_tenant_id BIGINT,
            p_user_id BIGINT DEFAULT NULL,
            p_action TEXT DEFAULT 'Both',
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
            v_data_prev JSON := '[]'::JSON;
            v_data_curr JSON := '[]'::JSON;
            v_data_next JSON := '[]'::JSON;
            v_offset_prev INT := 0;
            v_offset_curr INT := 0;
            v_offset_next INT := 0;
            v_order_clause TEXT := 'ORDER BY u.id DESC';
            v_message TEXT := 'Users retrieved successfully';
            v_search_clause TEXT := '';
            v_action_clause TEXT := '';
        BEGIN
            ----------------------------------------------------------------
            -- Validations
            ----------------------------------------------------------------
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN json_build_object(
                    'success', false,
                    'message', 'Invalid tenant ID',
                    'data', json_build_object(
                        'previous', '[]'::JSON,
                        'current', '[]'::JSON,
                        'next', '[]'::JSON
                    ),
                    'meta', json_build_object(
                        'total_records', 0,
                        'total_pages', 0,
                        'current_page', p_page_no,
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
            -- Search clause
            ----------------------------------------------------------------
            IF p_search IS NOT NULL AND LENGTH(TRIM(p_search)) > 0 THEN
                v_search_clause := format(
                    'AND (
                        LOWER(u.name) LIKE LOWER(%L) OR 
                        LOWER(u.email) LIKE LOWER(%L)
                    )',
                    '%' || p_search || '%',
                    '%' || p_search || '%'
                );
            END IF;

            ----------------------------------------------------------------
            -- Action clause
            ----------------------------------------------------------------
            IF p_action = 'Respoible_persons_only' THEN
                v_action_clause := 'AND EXISTS (
                    SELECT 1 FROM asset_items ai 
                    WHERE ai.responsible_person = u.id 
                    AND ai.deleted_at IS NULL 
                    AND ai.isactive = true
                )';
                v_message := 'Responsible persons retrieved successfully';
            ELSE
                v_action_clause := '';
                v_message := 'Users retrieved successfully';
            END IF;

            ----------------------------------------------------------------
            -- Count total records
            ----------------------------------------------------------------
            EXECUTE format($s$
                SELECT COUNT(*)
                FROM users u
                WHERE u.tenant_id = %s
                AND u.deleted_at IS NULL
                AND u.is_user_active = true
                AND u.is_system_user = false
                AND u.is_app_user = true
                %s
                %s
                %s
            $s$, 
                p_tenant_id,
                CASE WHEN p_user_id IS NOT NULL THEN format('AND u.id = %s', p_user_id) ELSE '' END,
                v_search_clause,
                v_action_clause
            )
            INTO v_total_records;

            IF v_total_records = 0 THEN
                RETURN json_build_object(
                    'success', true,
                    'message', 'No users found',
                    'data', json_build_object(
                        'previous', '[]'::JSON,
                        'current', '[]'::JSON,
                        'next', '[]'::JSON
                    ),
                    'meta', json_build_object(
                        'total_records', 0,
                        'total_pages', 0,
                        'current_page', p_page_no,
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
            EXECUTE format($s$
                SELECT COALESCE(json_agg(t), '[]'::JSON)
                FROM (
                    SELECT
                        'SUCCESS'::TEXT AS status,
                        %L AS message,
                        u.id,
                        u.email,
                        u.name,
                        u.profile_image,
                        u.designation_id,
                        d.designation AS designation_name
                    FROM users u
                    LEFT JOIN designations d ON u.designation_id = d.id AND d.deleted_at IS NULL AND d.isactive = true
                    WHERE u.tenant_id = %s
                    AND u.deleted_at IS NULL
                    AND u.is_user_active = true
                    AND u.is_system_user = false
                    AND u.is_app_user = true
                    %s
                    %s
                    %s
                    %s
                    LIMIT %s OFFSET %s
                ) t
            $s$,
                v_message,
                p_tenant_id,
                CASE WHEN p_user_id IS NOT NULL THEN format('AND u.id = %s', p_user_id) ELSE '' END,
                v_search_clause,
                v_action_clause,
                v_order_clause,
                p_page_size,
                v_offset_curr
            )
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
                            'SUCCESS'::TEXT AS status,
                            %L AS message,
                            u.id,
                            u.email,
                            u.name,
                            u.profile_image,
                            u.designation_id,
                            d.designation AS designation_name
                        FROM users u
                        LEFT JOIN designations d ON u.designation_id = d.id AND d.deleted_at IS NULL AND d.isactive = true
                        WHERE u.tenant_id = %s
                        AND u.deleted_at IS NULL
                        AND u.is_user_active = true
                        AND u.is_system_user = false
                        AND u.is_app_user = true
                        %s
                        %s
                        %s
                        %s
                        LIMIT %s OFFSET %s
                    ) t
                $s$,
                    v_message,
                    p_tenant_id,
                    CASE WHEN p_user_id IS NOT NULL THEN format('AND u.id = %s', p_user_id) ELSE '' END,
                    v_search_clause,
                    v_action_clause,
                    v_order_clause,
                    p_page_size,
                    v_offset_prev
                )
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
                            'SUCCESS'::TEXT AS status,
                            %L AS message,
                            u.id,
                            u.email,
                            u.name,
                            u.profile_image,
                            u.designation_id,
                            d.designation AS designation_name
                        FROM users u
                        LEFT JOIN designations d ON u.designation_id = d.id AND d.deleted_at IS NULL AND d.isactive = true
                        WHERE u.tenant_id = %s
                        AND u.deleted_at IS NULL
                        AND u.is_user_active = true
                        AND u.is_system_user = false
                        AND u.is_app_user = true
                        %s
                        %s
                        %s
                        %s
                        LIMIT %s OFFSET %s
                    ) t
                $s$,
                    v_message,
                    p_tenant_id,
                    CASE WHEN p_user_id IS NOT NULL THEN format('AND u.id = %s', p_user_id) ELSE '' END,
                    v_search_clause,
                    v_action_clause,
                    v_order_clause,
                    p_page_size,
                    v_offset_next
                )
                INTO v_data_next;
                v_data_next := COALESCE(v_data_next, '[]'::JSON);
            END IF;

            ----------------------------------------------------------------
            -- Return final result
            ----------------------------------------------------------------
            RETURN json_build_object(
                'success', true,
                'message', v_message,
                'data', json_build_object(
                    'previous', v_data_prev,
                    'current', v_data_curr,
                    'next', v_data_next
                ),
                'meta', json_build_object(
                    'total_records', v_total_records,
                    'total_pages', v_total_pages,
                    'current_page', p_page_no,
                    'page_size', p_page_size
                )
            );

        END;
        $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_users_list_for_drawer(BIGINT, BIGINT, TEXT, INT, INT, TEXT, TEXT, TEXT);');
    }
}; 