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
        DB::unprepared(
        <<<'SQL'
        DO $$
        DECLARE
            r RECORD;
        BEGIN
            -- Drop all existing versions of the function
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'get_asset_sub_categories_list_paginated'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_asset_sub_categories_list_paginated(
            p_tenant_id BIGINT,
            p_asset_sub_category_id INT DEFAULT NULL,
            p_asset_category_id INT DEFAULT NULL,
            p_action TEXT DEFAULT 'all',  -- 'all' or 'by_category'
            p_page_no INT DEFAULT 1,
            p_page_size INT DEFAULT 10,
            p_search TEXT DEFAULT NULL,
            p_prefetch_mode TEXT DEFAULT 'both',  -- 'none', 'after', 'both'
            p_sort_by TEXT DEFAULT 'newest'
        )
        RETURNS JSON
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_total_records INT;
            v_total_pages INT;
            v_data_prev JSON := '[]'::JSON;
            v_data_curr JSON := '[]'::JSON;
            v_data_next JSON := '[]'::JSON;
            v_offset_prev INT;
            v_offset_curr INT;
            v_offset_next INT;
            v_order_clause TEXT := 'ORDER BY asub.id DESC';
            inner_sql TEXT;
            v_where_clause TEXT := '';
            v_message TEXT := '';
        BEGIN
            -- Sorting logic
            CASE LOWER(TRIM(p_sort_by))
                WHEN 'newest' THEN v_order_clause := 'ORDER BY asub.id DESC';
                WHEN 'oldest' THEN v_order_clause := 'ORDER BY asub.id ASC';
                WHEN 'az' THEN v_order_clause := 'ORDER BY asub.name ASC NULLS LAST';
                WHEN 'za' THEN v_order_clause := 'ORDER BY asub.name DESC NULLS LAST';
                ELSE v_order_clause := 'ORDER BY asub.id DESC';
            END CASE;

            -- Tenant validation
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN json_build_object(
                    'status', 'FAILURE',
                    'message', 'Invalid tenant ID',
                    'data', json_build_object('previous', '[]'::json, 'current', '[]'::json, 'next', '[]'::json),
                    'success', FALSE
                );
            END IF;

            p_page_no := GREATEST(p_page_no, 1);
            p_page_size := GREATEST(p_page_size, 1);

            -- Build WHERE clause based on action
            IF p_action = 'by_category' THEN
                IF p_asset_category_id IS NULL OR p_asset_category_id <= 0 THEN
                    RETURN json_build_object(
                        'status', 'FAILURE',
                        'message', 'Invalid asset_category_id for by_category action',
                        'data', json_build_object('previous', '[]'::json, 'current', '[]'::json, 'next', '[]'::json),
                        'success', FALSE
                    );
                END IF;
                v_where_clause := format('AND asub.asset_category_id = %L', p_asset_category_id);
                v_message := 'Asset sub-categories for category fetched successfully';
            ELSE
                -- Action = 'all'
                v_where_clause := '';
                v_message := 'All asset sub-categories fetched successfully';
            END IF;

            -- If specific sub-category ID is requested
            IF p_asset_sub_category_id IS NOT NULL AND p_asset_sub_category_id > 0 THEN
                v_where_clause := v_where_clause || format(' AND asub.id = %L', p_asset_sub_category_id);
                v_message := 'Asset sub-category details fetched successfully';
            END IF;

            -- Count total records
            EXECUTE format($SQL$
                SELECT COUNT(*) 
                FROM asset_sub_categories asub
                INNER JOIN asset_categories ac ON asub.asset_category_id = ac.id
                LEFT JOIN assets_types at ON ac.assets_type = at.id
                WHERE asub.tenant_id = %L
                AND asub.deleted_at IS NULL
                AND asub.isactive = TRUE
                AND ac.deleted_at IS NULL
                AND ac.isactive = TRUE
                %s
                AND (%L IS NULL OR asub.name ILIKE '%%' || %L || '%%' OR asub.description ILIKE '%%' || %L || '%%')
            $SQL$,
                p_tenant_id,
                v_where_clause,
                p_search, p_search, p_search
            ) INTO v_total_records;

            IF v_total_records = 0 THEN
                RETURN json_build_object(
                    'status', 'FAILURE',
                    'message', 'No asset sub-categories found',
                    'meta', json_build_object(
                        'total_records', 0,
                        'total_pages', 0,
                        'current_page', p_page_no,
                        'page_size', p_page_size
                    ),
                    'data', json_build_object('previous', '[]'::json, 'current', '[]'::json, 'next', '[]'::json),
                    'success', FALSE
                );
            END IF;

            v_total_pages := CEIL(v_total_records::DECIMAL / p_page_size);
            v_offset_curr := (p_page_no - 1) * p_page_size;
            v_offset_prev := GREATEST(v_offset_curr - p_page_size, 0);
            v_offset_next := p_page_no * p_page_size;

            -- Common SELECT Template
            inner_sql := format($SQL$
                SELECT 
                    asub.id,
                    asub.asset_category_id,
                    ac.name AS asset_category_name,
                    ac.assets_type AS asset_type_id,
                    at.name AS asset_type_name,
                    asub.name,
                    asub.description,
                    asub.reading_parameters,
                    asub.isactive AS is_active,
                    asub.tenant_id,
                    asub.created_at,
                    asub.updated_at
                FROM asset_sub_categories asub
                INNER JOIN asset_categories ac ON asub.asset_category_id = ac.id
                LEFT JOIN assets_types at ON ac.assets_type = at.id
                WHERE asub.tenant_id = %L
                AND asub.deleted_at IS NULL
                AND asub.isactive = TRUE
                AND ac.deleted_at IS NULL
                AND ac.isactive = TRUE
                %s
                AND (%L IS NULL OR asub.name ILIKE '%%' || %L || '%%' OR asub.description ILIKE '%%' || %L || '%%')
                %s
                LIMIT %s OFFSET %s
            $SQL$,
                p_tenant_id,
                v_where_clause,
                p_search, p_search, p_search,
                v_order_clause, 
                p_page_size, 
                v_offset_curr
            );

            EXECUTE 'SELECT json_agg(row_to_json(t)) FROM (' || inner_sql || ') t'
            INTO v_data_curr;
            v_data_curr := COALESCE(v_data_curr, '[]'::json);

            -- Previous page prefetch
            IF p_prefetch_mode = 'both' AND p_page_no > 1 THEN
                inner_sql := regexp_replace(inner_sql, 'OFFSET \d+', format('OFFSET %s', v_offset_prev));
                EXECUTE 'SELECT json_agg(row_to_json(t)) FROM (' || inner_sql || ') t'
                INTO v_data_prev;
                v_data_prev := COALESCE(v_data_prev, '[]'::json);
            END IF;

            -- Next page prefetch
            IF p_prefetch_mode IN ('both', 'after') AND p_page_no < v_total_pages THEN
                inner_sql := regexp_replace(inner_sql, 'OFFSET \d+', format('OFFSET %s', v_offset_next));
                EXECUTE 'SELECT json_agg(row_to_json(t)) FROM (' || inner_sql || ') t'
                INTO v_data_next;
                v_data_next := COALESCE(v_data_next, '[]'::json);
            END IF;

            -- Final JSON output
            RETURN json_build_object(
                'status', 'SUCCESS',
                'message', v_message,
                'meta', json_build_object(
                    'total_records', v_total_records,
                    'total_pages', v_total_pages,
                    'current_page', p_page_no,
                    'page_size', p_page_size,
                    'prefetch_mode', p_prefetch_mode,
                    'sort_by', p_sort_by,
                    'action', p_action,
                    'asset_category_id', p_asset_category_id
                ),
                'data', json_build_object(
                    'previous', v_data_prev,
                    'current', v_data_curr,
                    'next', v_data_next
                ),
                'success', TRUE
            );
        END;
        $$;
        SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       DB::unprepared(<<<SQL
        DO $$
        DECLARE
            r RECORD;
        BEGIN
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'get_asset_sub_categories_list_paginated'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;
        SQL);
    }
};