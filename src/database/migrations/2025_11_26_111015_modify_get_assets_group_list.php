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
                WHERE proname = 'get_assets_group_list'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_assets_group_list(
            p_tenant_id BIGINT,
            p_asset_id BIGINT DEFAULT NULL,
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
            v_order_clause TEXT := 'ORDER BY a.id DESC';
            v_message TEXT := '';
            v_base_query TEXT := '';
            v_search_clause TEXT := '';
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

            IF p_asset_id IS NOT NULL AND p_asset_id < 0 THEN
                RETURN json_build_object(
                    'status', 'FAILURE',
                    'message', 'Invalid asset ID provided',
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
                WHEN 'newest' THEN v_order_clause := 'ORDER BY a.id DESC';
                WHEN 'oldest' THEN v_order_clause := 'ORDER BY a.id ASC';
                WHEN 'az' THEN v_order_clause := 'ORDER BY a.name ASC NULLS LAST';
                WHEN 'za' THEN v_order_clause := 'ORDER BY a.name DESC NULLS LAST';
                ELSE v_order_clause := 'ORDER BY a.id DESC';
            END CASE;

            p_page_no := GREATEST(p_page_no, 1);
            p_page_size := GREATEST(p_page_size, 1);

            ----------------------------------------------------------------
            -- Search clause
            ----------------------------------------------------------------
            IF p_search IS NOT NULL AND LENGTH(TRIM(p_search)) > 0 THEN
                v_search_clause := format(
                    'AND (a.name ILIKE %L OR a.asset_description ILIKE %L)',
                    '%' || p_search || '%',
                    '%' || p_search || '%'
                );
            END IF;

            ----------------------------------------------------------------
            -- Build base query
            ----------------------------------------------------------------
            v_base_query := format(
                'FROM assets a WHERE a.deleted_at IS NULL AND a.isactive = TRUE AND a.tenant_id = %L AND (%L IS NULL OR a.id = %L) %s',
                p_tenant_id,
                p_asset_id,
                p_asset_id,
                v_search_clause
            );
            v_message := 'Assets fetched successfully';

            ----------------------------------------------------------------
            -- Count total records
            ----------------------------------------------------------------
            EXECUTE format('SELECT COUNT(*) %s', v_base_query)
            INTO v_total_records;

            IF v_total_records = 0 THEN
                RETURN json_build_object(
                    'status', 'SUCCESS',
                    'message', 'No assets found',
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
                'SELECT COALESCE(json_agg(t), ''[]''::JSON) FROM (SELECT a.id, a.name::TEXT, a.asset_description::TEXT, a.created_at, a.updated_at %s %s LIMIT %s OFFSET %s) t',
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
                        'SELECT a.id, a.name::TEXT, a.asset_description::TEXT, a.created_at, a.updated_at %s %s LIMIT %s OFFSET %s',
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
                        'SELECT a.id, a.name::TEXT, a.asset_description::TEXT, a.created_at, a.updated_at %s %s LIMIT %s OFFSET %s',
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
        DB::unprepared('DROP FUNCTION IF EXISTS get_assets_group_list(BIGINT, BIGINT, INT, INT, TEXT, TEXT, TEXT)');
    }
};
