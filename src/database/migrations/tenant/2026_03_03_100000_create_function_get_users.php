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
        // 1. Drop every existing overload of get_users
        DB::unprepared(<<<'SQL'
          
                DO $$
                    DECLARE
                        r RECORD;
                    BEGIN
                        FOR r IN
                            SELECT oid::regprocedure::text AS func_signature
                            FROM pg_proc
                            WHERE proname = 'get_users'
                        LOOP
                            EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                        END LOOP;
                    END$$;
                    
        CREATE OR REPLACE FUNCTION get_users(
            p_tenant_id BIGINT,
            p_user_id BIGINT DEFAULT NULL,
            p_page_no INT DEFAULT 1,
            p_page_size INT DEFAULT 10,
            p_search TEXT DEFAULT NULL,
            p_status TEXT DEFAULT NULL,
            p_is_system_user BOOLEAN DEFAULT NULL,
            p_prefetch_mode TEXT DEFAULT 'both',  -- none | after | both
            p_sort_by TEXT DEFAULT NULL
        )
        RETURNS JSON
        LANGUAGE plpgsql
        AS $$
        DECLARE
            user_count INT;
            v_total_pages INT;

            v_data_prev JSONB := '[]';
            v_data_curr JSONB := '[]';
            v_data_next JSONB := '[]';

            v_offset_curr INT;
            v_offset_prev INT;
            v_offset_next INT;

            v_order_clause TEXT;
            v_sort_key TEXT;
            v_prefetch_mode TEXT;

            v_base_sql TEXT;
        BEGIN
            -------------------------------------------------------------------
            -- Input normalization & validation
            -------------------------------------------------------------------
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN json_build_object(
                    'status', 'FAILURE',
                    'message', 'Invalid tenant ID',
                    'data', json_build_object(
                        'previous', '[]'::json,
                        'current', '[]'::json,
                        'next', '[]'::json
                    )
                );
            END IF;

            p_page_no   := GREATEST(p_page_no, 1);
            p_page_size := GREATEST(p_page_size, 1);

            --  FIX: normalize search (prevents ILIKE '%%')
            p_search := NULLIF(TRIM(p_search), '');

            v_sort_key := LOWER(REPLACE(REPLACE(COALESCE(p_sort_by, 'newest'), '-', ''), '_', ''));
            v_prefetch_mode := LOWER(COALESCE(p_prefetch_mode, 'both'));

            -------------------------------------------------------------------
            -- Sorting (stable + deterministic)
            -------------------------------------------------------------------
            v_order_clause :=
                CASE v_sort_key
                    WHEN 'newest' THEN 'ORDER BY u.created_at DESC, u.id DESC'
                    WHEN 'oldest' THEN 'ORDER BY u.created_at ASC,  u.id ASC'
                    WHEN 'az'     THEN 'ORDER BY u.name ASC  NULLS LAST, u.id DESC'
                    WHEN 'za'     THEN 'ORDER BY u.name DESC NULLS LAST, u.id DESC'
                    ELSE               'ORDER BY u.created_at DESC, u.id DESC'
                END;

            -------------------------------------------------------------------
            -- Base WHERE clause (single source of truth)
            -------------------------------------------------------------------
            v_base_sql := '
                FROM users u
                LEFT JOIN designations d ON u.designation_id = d.id
                WHERE u.tenant_id = $1
                AND u.deleted_at IS NULL
                AND ($2 IS NULL OR u.id = $2)
                AND ($3 IS NULL OR 
                    CASE 
                        WHEN $3 = ''active'' THEN u.user_account_enabled = TRUE
                        WHEN $3 = ''inactive'' THEN u.user_account_enabled = FALSE
                        ELSE TRUE
                    END
                )
                AND ($4 IS NULL OR u.is_system_user = $4)
                AND (
                    $5 IS NULL OR
                    u.name ILIKE ''%'' || $5 || ''%'' OR
                    u.email ILIKE ''%'' || $5 || ''%'' OR
                    u.user_name ILIKE ''%'' || $5 || ''%'' OR
                    u.employee_number ILIKE ''%'' || $5 || ''%'' OR
                    u.user_description ILIKE ''%'' || $5 || ''%'' OR
                    (
                        $5 ~ ''[0-9]'' AND
                        u.contact_no ILIKE ''%'' || $5 || ''%''
                    )
                )
            ';

            -------------------------------------------------------------------
            -- Total count
            -------------------------------------------------------------------
            EXECUTE 'SELECT COUNT(*) ' || v_base_sql
            INTO user_count
            USING p_tenant_id, p_user_id, p_status, p_is_system_user, p_search;

            IF user_count = 0 THEN
                RETURN json_build_object(
                    'status', 'FAILURE',
                    'message', 'No users found',
                    'meta', json_build_object(
                        'total_records', 0,
                        'total_pages', 0,
                        'current_page', p_page_no,
                        'page_size', p_page_size
                    ),
                    'data', json_build_object(
                        'previous', '[]'::json,
                        'current', '[]'::json,
                        'next', '[]'::json
                    )
                );
            END IF;

            v_total_pages := CEIL(user_count::NUMERIC / p_page_size);

            IF p_page_no > v_total_pages THEN
                p_page_no := v_total_pages;
            END IF;

            v_offset_curr := (p_page_no - 1) * p_page_size;
            v_offset_prev := GREATEST(v_offset_curr - p_page_size, 0);
            v_offset_next := p_page_no * p_page_size;

            -------------------------------------------------------------------
            -- Current page (with roles aggregation)
            -------------------------------------------------------------------
            EXECUTE format(
                'SELECT jsonb_agg(row_to_json(t)) FROM (
                    SELECT
                        u.id,
                        u.user_name,
                        u.email,
                        u.name,
                        u.contact_no,
                        u.contact_no_code,
                        u.profile_image,
                        u.user_description,
                        u.designation_id,
                        u.organization,
                        u.is_system_user,
                        u.user_account_enabled,
                        u.employee_account_enabled,
                        u.employee_number,
                        u.is_user_active,
                        u.created_at,
                        u.updated_at,
                        d.designation as designation_name,
                        (
                            SELECT jsonb_agg(
                                jsonb_build_object(
                                    ''id'', r.id,
                                    ''name'', r.name
                                )
                            )
                            FROM role_user ru
                            JOIN roles r ON ru.role_id = r.id
                            WHERE ru.user_id = u.id
                        ) as roles
                    %s
                    %s
                    LIMIT %s OFFSET %s
                ) t',
                v_base_sql,
                v_order_clause,
                p_page_size,
                v_offset_curr
            )
            INTO v_data_curr
            USING p_tenant_id, p_user_id, p_status, p_is_system_user, p_search;

            -------------------------------------------------------------------
            -- Previous page
            -------------------------------------------------------------------
            IF v_prefetch_mode = 'both' AND p_page_no > 1 THEN
                EXECUTE format(
                    'SELECT jsonb_agg(row_to_json(t)) FROM (
                        SELECT u.* %s %s LIMIT %s OFFSET %s
                    ) t',
                    v_base_sql,
                    v_order_clause,
                    p_page_size,
                    v_offset_prev
                )
                INTO v_data_prev
                USING p_tenant_id, p_user_id, p_status, p_is_system_user, p_search;
            END IF;

            -------------------------------------------------------------------
            -- Next page
            -------------------------------------------------------------------
            IF v_prefetch_mode IN ('both', 'after') AND p_page_no < v_total_pages THEN
                EXECUTE format(
                    'SELECT jsonb_agg(row_to_json(t)) FROM (
                        SELECT u.* %s %s LIMIT %s OFFSET %s
                    ) t',
                    v_base_sql,
                    v_order_clause,
                    p_page_size,
                    v_offset_next
                )
                INTO v_data_next
                USING p_tenant_id, p_user_id, p_status, p_is_system_user, p_search;
            END IF;

            -------------------------------------------------------------------
            -- Final response
            -------------------------------------------------------------------
            RETURN json_build_object(
                'status', 'SUCCESS',
                'message', 'Users fetched successfully',
                'meta', json_build_object(
                    'total_records', user_count,
                    'total_pages', v_total_pages,
                    'current_page', p_page_no,
                    'page_size', p_page_size,
                    'prefetch_mode', v_prefetch_mode,
                    'sort_by', v_sort_key
                ),
                'data', json_build_object(
                    'previous', COALESCE(v_data_prev, '[]'::jsonb),
                    'current',  COALESCE(v_data_curr, '[]'::jsonb),
                    'next',     COALESCE(v_data_next, '[]'::jsonb)
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
                    WHERE proname = 'get_users'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
        SQL);
    }
};
