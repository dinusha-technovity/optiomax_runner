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
                WHERE proname = 'get_approved_procurements_for_drawer'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_approved_procurements_for_drawer(
            p_tenant_id BIGINT,
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
            v_order_clause TEXT := 'ORDER BY p.id DESC';
            v_message TEXT := '';
            v_search_clause TEXT := '';
            v_base_query TEXT := '';
        BEGIN
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN json_build_object(
                    'status', 'FAILURE',
                    'message', 'Invalid tenant ID provided',
                    'success', FALSE,
                    'data', json_build_object('previous', '[]', 'current', '[]', 'next', '[]'),
                    'pagination', json_build_object('current_page', 0, 'total_pages', 0, 'total_records', 0, 'page_size', p_page_size)
                );
            END IF;

            -- Sorting
            CASE LOWER(TRIM(p_sort_by))
                WHEN 'newest' THEN v_order_clause := 'ORDER BY p.id DESC';
                WHEN 'oldest' THEN v_order_clause := 'ORDER BY p.id ASC';
                WHEN 'az' THEN v_order_clause := 'ORDER BY p.request_id ASC NULLS LAST';
                WHEN 'za' THEN v_order_clause := 'ORDER BY p.request_id DESC NULLS LAST';
                ELSE v_order_clause := 'ORDER BY p.id DESC';
            END CASE;

            p_page_no := GREATEST(p_page_no, 1);
            p_page_size := GREATEST(p_page_size, 1);

            IF p_search IS NOT NULL AND LENGTH(TRIM(p_search)) > 0 THEN
                v_search_clause := format(
                    'AND (p.request_id ILIKE %L OR u.name ILIKE %L)',
                    '%' || p_search || '%',
                    '%' || p_search || '%'
                );
            END IF;

            v_base_query := format($BQ$
                FROM procurements p
                LEFT JOIN users u ON u.id = p.created_by
                WHERE p.tenant_id = %s
                    AND p.procurement_status = 'APPROVED'
                    %s
            $BQ$, p_tenant_id, v_search_clause);

            EXECUTE format($C$ SELECT COUNT(*) %s $C$, v_base_query) INTO v_total_records;

            IF v_total_records = 0 THEN
                RETURN json_build_object(
                    'status', 'SUCCESS',
                    'message', 'No approved procurements found',
                    'success', TRUE,
                    'data', json_build_object('previous', '[]', 'current', '[]', 'next', '[]'),
                    'pagination', json_build_object('current_page', p_page_no, 'total_pages', 0, 'total_records', 0, 'page_size', p_page_size)
                );
            END IF;

            v_total_pages := CEIL(v_total_records::DECIMAL / p_page_size);
            v_offset_curr := (p_page_no - 1) * p_page_size;
            v_offset_prev := GREATEST(v_offset_curr - p_page_size, 0);
            v_offset_next := p_page_no * p_page_size;

            -- Current
            EXECUTE format($Q$
                SELECT COALESCE(json_agg(t), '[]'::JSON)
                FROM (
                    SELECT p.id, p.request_id, p.procurement_status, p.created_by, u.name as created_by_name, p.created_at, p.updated_at
                    %s
                    %s
                    LIMIT %s OFFSET %s
                ) t
            $Q$, v_base_query, v_order_clause, p_page_size, v_offset_curr)
            INTO v_data_curr;
            v_data_curr := COALESCE(v_data_curr, '[]'::JSON);

            -- Previous
            IF p_prefetch_mode = 'both' AND p_page_no > 1 THEN
                EXECUTE format($Q$
                    SELECT COALESCE(json_agg(t), '[]'::JSON)
                    FROM (
                        SELECT p.id, p.request_id, p.procurement_status, p.created_by, u.name as created_by_name, p.created_at, p.updated_at
                        %s
                        %s
                        LIMIT %s OFFSET %s
                    ) t
                $Q$, v_base_query, v_order_clause, p_page_size, v_offset_prev)
                INTO v_data_prev;
                v_data_prev := COALESCE(v_data_prev, '[]'::JSON);
            END IF;

            -- Next
            IF (p_prefetch_mode = 'both' OR p_prefetch_mode = 'after') AND p_page_no < v_total_pages THEN
                EXECUTE format($Q$
                    SELECT COALESCE(json_agg(t), '[]'::JSON)
                    FROM (
                        SELECT p.id, p.request_id, p.procurement_status, p.created_by, u.name as created_by_name, p.created_at, p.updated_at
                        %s
                        %s
                        LIMIT %s OFFSET %s
                    ) t
                $Q$, v_base_query, v_order_clause, p_page_size, v_offset_next)
                INTO v_data_next;
                v_data_next := COALESCE(v_data_next, '[]'::JSON);
            END IF;

            RETURN json_build_object(
                'status', 'SUCCESS',
                'message', 'Approved procurements fetched successfully',
                'success', TRUE,
                'data', json_build_object('previous', v_data_prev, 'current', v_data_curr, 'next', v_data_next),
                'pagination', json_build_object('current_page', p_page_no, 'total_pages', v_total_pages, 'total_records', v_total_records, 'page_size', p_page_size)
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
                WHERE proname = 'get_approved_procurements_for_drawer'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;
        SQL
        );
    }
};