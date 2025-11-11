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
                WHERE proname = 'get_supplier_list_for_drawer'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_supplier_list_for_drawer(
            p_tenant_id BIGINT,
            p_supplier_id BIGINT DEFAULT NULL,
            p_page_no INT DEFAULT 1,
            p_page_size INT DEFAULT 10,
            p_search TEXT DEFAULT NULL,
            p_prefetch_mode TEXT DEFAULT 'both',  -- 'none', 'after', 'both'
            p_sort_by TEXT DEFAULT 'newest'       -- 'newest', 'oldest', 'az', 'za', 'rating_high', 'rating_low'
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
            v_order_clause TEXT := 'ORDER BY s.id DESC';
            v_message TEXT := '';
            v_where_clause TEXT := '';
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
                    'data', json_build_object('previous', '[]', 'current', '[]', 'next', '[]')
                );
            END IF;

            ----------------------------------------------------------------
            -- Sorting Logic
            ----------------------------------------------------------------
            CASE LOWER(TRIM(p_sort_by))
                WHEN 'newest' THEN v_order_clause := 'ORDER BY s.id DESC';
                WHEN 'oldest' THEN v_order_clause := 'ORDER BY s.id ASC';
                WHEN 'az' THEN v_order_clause := 'ORDER BY s.name ASC NULLS LAST';
                WHEN 'za' THEN v_order_clause := 'ORDER BY s.name DESC NULLS LAST';
                WHEN 'rating_high' THEN v_order_clause := 'ORDER BY s.supplier_rating DESC NULLS LAST';
                WHEN 'rating_low' THEN v_order_clause := 'ORDER BY s.supplier_rating ASC NULLS LAST';
                ELSE v_order_clause := 'ORDER BY s.id DESC';
            END CASE;

            p_page_no := GREATEST(p_page_no, 1);
            p_page_size := GREATEST(p_page_size, 1);

            ----------------------------------------------------------------
            -- Supplier filter logic
            ----------------------------------------------------------------
            IF p_supplier_id IS NULL THEN
                v_where_clause := '';
                v_message := 'All suppliers fetched successfully';
            ELSIF p_supplier_id = -1 THEN
                v_where_clause := 'AND s.supplier_reg_status = ''INVITED''';
                v_message := 'Invited suppliers fetched successfully';
            ELSIF p_supplier_id = 0 THEN
                v_where_clause := ''; -- ignore reg_status
                v_message := 'All suppliers (reg status ignored) fetched successfully';
            ELSE
                v_where_clause := format('AND s.id = %s AND s.supplier_reg_status = ''APPROVED''', p_supplier_id);
                v_message := 'Specific supplier fetched successfully';
            END IF;

            ----------------------------------------------------------------
            -- Search clause
            ----------------------------------------------------------------
            IF p_search IS NOT NULL AND LENGTH(TRIM(p_search)) > 0 THEN
                v_search_clause := format(
                    'AND (s.name ILIKE %L OR s.email ILIKE %L OR s.description ILIKE %L)',
                    '%%' || p_search || '%%', '%%' || p_search || '%%', '%%' || p_search || '%%'
                );
            END IF;

            ----------------------------------------------------------------
            -- Count total records
            ----------------------------------------------------------------
            EXECUTE format($SQL$
                SELECT COUNT(*) 
                FROM suppliers s
                WHERE s.tenant_id = %L
                AND s.deleted_at IS NULL
                AND s.isactive = TRUE
                %s
                %s
            $SQL$, p_tenant_id, v_where_clause, v_search_clause)
            INTO v_total_records;

            IF v_total_records = 0 THEN
                RETURN json_build_object(
                    'status', 'FAILURE',
                    'message', 'No suppliers found',
                    'meta', json_build_object(
                        'total_records', 0,
                        'total_pages', 0,
                        'current_page', p_page_no,
                        'page_size', p_page_size
                    ),
                    'data', json_build_object('previous', '[]', 'current', '[]', 'next', '[]'),
                    'success', FALSE
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
            EXECUTE format($SQL$
                SELECT json_agg(row_to_json(t)) FROM (
                    SELECT 
                        s.id,
                        s.name,
                        s.description,
                        s.email,
                        s.supplier_reg_status,
                        s.supplier_rating,
                        s.isactive,
                        s.created_at
                    FROM suppliers s
                    WHERE s.tenant_id = %L
                    AND s.deleted_at IS NULL
                    AND s.isactive = TRUE
                    %s
                    %s
                    %s
                    LIMIT %s OFFSET %s
                ) t
            $SQL$, p_tenant_id, v_where_clause, v_search_clause, v_order_clause, p_page_size, v_offset_curr)
            INTO v_data_curr;
            v_data_curr := COALESCE(v_data_curr, '[]'::JSON);

            ----------------------------------------------------------------
            -- Previous page (if prefetch)
            ----------------------------------------------------------------
            IF p_prefetch_mode = 'both' AND p_page_no > 1 THEN
                EXECUTE format($SQL$
                    SELECT json_agg(row_to_json(t)) FROM (
                        SELECT 
                            s.id,
                            s.name,
                            s.description,
                            s.email,
                            s.supplier_reg_status,
                            s.supplier_rating,
                            s.isactive,
                            s.created_at
                        FROM suppliers s
                        WHERE s.tenant_id = %L
                        AND s.deleted_at IS NULL
                        AND s.isactive = TRUE
                        %s
                        %s
                        %s
                        LIMIT %s OFFSET %s
                    ) t
                $SQL$, p_tenant_id, v_where_clause, v_search_clause, v_order_clause, p_page_size, v_offset_prev)
                INTO v_data_prev;
                v_data_prev := COALESCE(v_data_prev, '[]'::JSON);
            END IF;

            ----------------------------------------------------------------
            -- Next page (if prefetch)
            ----------------------------------------------------------------
            IF p_prefetch_mode IN ('both', 'after') AND p_page_no < v_total_pages THEN
                EXECUTE format($SQL$
                    SELECT json_agg(row_to_json(t)) FROM (
                        SELECT 
                            s.id,
                            s.name,
                            s.description,
                            s.email,
                            s.supplier_reg_status,
                            s.supplier_rating,
                            s.isactive,
                            s.created_at
                        FROM suppliers s
                        WHERE s.tenant_id = %L
                        AND s.deleted_at IS NULL
                        AND s.isactive = TRUE
                        %s
                        %s
                        %s
                        LIMIT %s OFFSET %s
                    ) t
                $SQL$, p_tenant_id, v_where_clause, v_search_clause, v_order_clause, p_page_size, v_offset_next)
                INTO v_data_next;
                v_data_next := COALESCE(v_data_next, '[]'::JSON);
            END IF;

            ----------------------------------------------------------------
            -- Return final structured JSON
            ----------------------------------------------------------------
            RETURN json_build_object(
                'status', 'SUCCESS',
                'message', v_message,
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
        DB::unprepared(<<<SQL
        DO $$
        DECLARE
            r RECORD;
        BEGIN
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'get_supplier_list_for_drawer'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;
        SQL);
    }
};
