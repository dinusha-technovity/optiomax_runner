<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
                WHERE proname = 'get_asset_sub_categories'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_asset_sub_categories(
            p_tenant_id BIGINT,
            p_asset_sub_categories_id BIGINT DEFAULT NULL,
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
            v_total_records INT;
            v_total_pages INT;

            v_data_prev JSON := '[]'::JSON;
            v_data_curr JSON := '[]'::JSON;
            v_data_next JSON := '[]'::JSON;

            v_offset_prev INT;
            v_offset_curr INT;
            v_offset_next INT;

            v_order_clause TEXT := 'ORDER BY asct.id DESC';
            inner_sql TEXT;
        BEGIN
            ----------------------------------------------------------------
            -- Sorting logic
            ----------------------------------------------------------------
            CASE LOWER(TRIM(p_sort_by))
                WHEN 'newest' THEN v_order_clause := 'ORDER BY asct.id DESC';
                WHEN 'oldest' THEN v_order_clause := 'ORDER BY asct.id ASC';
                WHEN 'az' THEN v_order_clause := 'ORDER BY asct.name ASC NULLS LAST';
                WHEN 'za' THEN v_order_clause := 'ORDER BY asct.name DESC NULLS LAST';
                ELSE v_order_clause := 'ORDER BY asct.id DESC';
            END CASE;

            ----------------------------------------------------------------
            -- Tenant validation
            ----------------------------------------------------------------
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN json_build_object(
                    'status', 'FAILURE',
                    'message', 'Invalid tenant ID',
                    'data', json_build_object('previous','[]','current','[]','next','[]'),
                    'success', FALSE
                );
            END IF;

            p_page_no := GREATEST(p_page_no, 1);
            p_page_size := GREATEST(p_page_size, 1);

            ----------------------------------------------------------------
            -- Count total records
            ----------------------------------------------------------------
            SELECT COUNT(*) INTO v_total_records
            FROM asset_sub_categories asct
            LEFT JOIN asset_categories ac ON ac.id = asct.asset_category_id
            WHERE asct.tenant_id = p_tenant_id
            AND asct.deleted_at IS NULL
            AND asct.isactive = TRUE
            AND (p_asset_sub_categories_id IS NULL OR asct.id = p_asset_sub_categories_id)
            AND (
                p_search IS NULL OR
                asct.name ILIKE '%' || p_search || '%' OR
                asct.description ILIKE '%' || p_search || '%' OR
                ac.name ILIKE '%' || p_search || '%'
            );

            IF v_total_records = 0 THEN
                RETURN json_build_object(
                    'status', 'FAILURE',
                    'message', 'No matching subcategories found',
                    'meta', json_build_object(
                        'total_records', 0,
                        'total_pages', 0,
                        'current_page', p_page_no,
                        'page_size', p_page_size
                    ),
                    'data', json_build_object('previous','[]','current','[]','next','[]'),
                    'success', FALSE
                );
            END IF;

            v_total_pages := CEIL(v_total_records::DECIMAL / p_page_size);

            v_offset_curr := (p_page_no - 1) * p_page_size;
            v_offset_prev := GREATEST(v_offset_curr - p_page_size, 0);
            v_offset_next := p_page_no * p_page_size;

            ----------------------------------------------------------------
            -- SQL Template for fetching rows
            ----------------------------------------------------------------
            inner_sql := format($SQL$
                SELECT 
                    asct.id,
                    asct.name AS subcategories_name,
                    asct.description AS subcategories_description,
                    asct.reading_parameters AS subcategoriesreading_parameters,
                    asct.asset_category_id,
                    ac.name AS category_name,
                    ac.reading_parameters AS categoriesreading_parameters
                FROM asset_sub_categories asct
                LEFT JOIN asset_categories ac ON ac.id = asct.asset_category_id
                WHERE asct.tenant_id = %L
                AND asct.deleted_at IS NULL
                AND asct.isactive = TRUE
                AND (%L IS NULL OR asct.id = %L)
                AND (
                    %L IS NULL OR
                    asct.name ILIKE '%%' || %L || '%%' OR
                    asct.description ILIKE '%%' || %L || '%%' OR
                    ac.name ILIKE '%%' || %L || '%%'
                )
                %s
                LIMIT %s OFFSET %s
            $SQL$,
                p_tenant_id,
                p_asset_sub_categories_id,
                p_asset_sub_categories_id,
                p_search,
                p_search,
                p_search,
                p_search,
                v_order_clause,
                p_page_size,
                v_offset_curr
            );

            ----------------------------------------------------------------
            -- Current page
            ----------------------------------------------------------------
            EXECUTE 'SELECT json_agg(row_to_json(t)) FROM (' || inner_sql || ') t'
            INTO v_data_curr;
            v_data_curr := COALESCE(v_data_curr, '[]'::json);

            ----------------------------------------------------------------
            -- Previous page
            ----------------------------------------------------------------
            IF p_prefetch_mode = 'both' AND p_page_no > 1 THEN
                inner_sql := replace(inner_sql, format('OFFSET %s', v_offset_curr), format('OFFSET %s', v_offset_prev));
                EXECUTE 'SELECT json_agg(row_to_json(t)) FROM (' || inner_sql || ') t'
                INTO v_data_prev;
                v_data_prev := COALESCE(v_data_prev, '[]'::json);
            END IF;

            ----------------------------------------------------------------
            -- Next page
            ----------------------------------------------------------------
            IF p_prefetch_mode IN ('both','after') AND p_page_no < v_total_pages THEN
                inner_sql := replace(inner_sql, format('OFFSET %s', v_offset_curr), format('OFFSET %s', v_offset_next));
                EXECUTE 'SELECT json_agg(row_to_json(t)) FROM (' || inner_sql || ') t'
                INTO v_data_next;
                v_data_next := COALESCE(v_data_next, '[]'::json);
            END IF;

            ----------------------------------------------------------------
            -- Final JSON output
            ----------------------------------------------------------------
            RETURN json_build_object(
                'status', 'SUCCESS',
                'message', CASE WHEN p_asset_sub_categories_id IS NULL 
                                THEN 'Subcategories fetched successfully' 
                                ELSE 'Subcategory details fetched successfully' 
                        END,
                'meta', json_build_object(
                    'total_records', v_total_records,
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
                ),
                'success', TRUE
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
        DB::unprepared(
            <<<SQL
            DO $$
        DECLARE
            r RECORD;
        BEGIN
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'get_asset_sub_categories'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;
        SQL
        );
    }
};
