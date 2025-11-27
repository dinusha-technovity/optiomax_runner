<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
                WHERE proname = 'get_item_master_details_list'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_item_master_details_list(
            p_tenant_id BIGINT,
            p_item_id BIGINT DEFAULT NULL,
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
            v_order_clause TEXT := 'ORDER BY i.id DESC';
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

            IF p_item_id IS NOT NULL AND p_item_id < 0 THEN
                RETURN json_build_object(
                    'status', 'FAILURE',
                    'message', 'Invalid item ID provided',
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
                WHEN 'newest' THEN v_order_clause := 'ORDER BY i.id DESC';
                WHEN 'oldest' THEN v_order_clause := 'ORDER BY i.id ASC';
                WHEN 'az' THEN v_order_clause := 'ORDER BY i.item_name ASC NULLS LAST';
                WHEN 'za' THEN v_order_clause := 'ORDER BY i.item_name DESC NULLS LAST';
                ELSE v_order_clause := 'ORDER BY i.id DESC';
            END CASE;

            p_page_no := GREATEST(p_page_no, 1);
            p_page_size := GREATEST(p_page_size, 1);

            ----------------------------------------------------------------
            -- Search clause
            ----------------------------------------------------------------
            IF p_search IS NOT NULL AND LENGTH(TRIM(p_search)) > 0 THEN
                v_search_clause := format(
                    'AND (i.item_name ILIKE %L OR i.item_description ILIKE %L OR i.item_id ILIKE %L)',
                    '%' || p_search || '%',
                    '%' || p_search || '%',
                    '%' || p_search || '%'
                );
            END IF;

            ----------------------------------------------------------------
            -- Build base query
            ----------------------------------------------------------------
            v_base_query := format(
                'FROM items i JOIN measurements m ON m.id = i.unit_of_measure_id JOIN asset_categories ac ON ac.id = i.category_id WHERE i.tenant_id = %L AND ( %L IS NULL OR i.id = %L) AND i.deleted_at IS NULL AND i.isactive = TRUE %s',
                p_tenant_id,
                p_item_id,
                p_item_id,
                v_search_clause
            );
            v_message := 'Items retrieved successfully';

            ----------------------------------------------------------------
            -- Count total records
            ----------------------------------------------------------------
            EXECUTE format('SELECT COUNT(*) %s', v_base_query)
            INTO v_total_records;

            IF v_total_records = 0 THEN
                RETURN json_build_object(
                    'status', 'SUCCESS',
                    'message', 'No items found',
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
                'SELECT COALESCE(json_agg(t), ''[]''::JSON) FROM (SELECT i.id, i.item_id::TEXT, i.item_name::TEXT, i.item_description::TEXT, i.purchase_price::NUMERIC, i.selling_price::NUMERIC, i.image_links::JSONB, m.name::TEXT AS unit_of_measure, ac.name::TEXT AS category_name %s %s LIMIT %s OFFSET %s) t',
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
                EXECUTE format(
                    'SELECT COALESCE(json_agg(t), ''[]''::JSON) FROM (SELECT i.id, i.item_id::TEXT, i.item_name::TEXT, i.item_description::TEXT, i.purchase_price::NUMERIC, i.selling_price::NUMERIC, i.image_links::JSONB, m.name::TEXT AS unit_of_measure, ac.name::TEXT AS category_name %s %s LIMIT %s OFFSET %s) t',
                    v_base_query,
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
            IF p_prefetch_mode IN ('both', 'after') AND p_page_no < v_total_pages THEN
                EXECUTE format(
                    'SELECT COALESCE(json_agg(t), ''[]''::JSON) FROM (SELECT i.id, i.item_id::TEXT, i.item_name::TEXT, i.item_description::TEXT, i.purchase_price::NUMERIC, i.selling_price::NUMERIC, i.image_links::JSONB, m.name::TEXT AS unit_of_measure, ac.name::TEXT AS category_name %s %s LIMIT %s OFFSET %s) t',
                    v_base_query,
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
       DB::unprepared('DROP FUNCTION IF EXISTS get_item_master_details_list(BIGINT, BIGINT, INT, INT, TEXT, TEXT, TEXT);');
    }
};