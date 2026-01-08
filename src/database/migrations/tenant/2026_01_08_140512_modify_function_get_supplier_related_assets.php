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
                WHERE proname = 'get_supplier_related_assets'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_supplier_related_assets(
            p_tenant_id BIGINT,
            p_supplier_id BIGINT,
            p_page_no INT DEFAULT 1,
            p_page_size INT DEFAULT 10,
            p_prefetch_mode TEXT DEFAULT 'both', -- none | after | both
            p_sort_by TEXT DEFAULT 'newest',
            p_search_text TEXT DEFAULT NULL
        )
        RETURNS JSON
        LANGUAGE plpgsql
        AS $$
        DECLARE
            asset_count INT;
            v_total_pages INT;

            v_data_prev JSON := '[]'::json;
            v_data_curr JSON := '[]'::json;
            v_data_next JSON := '[]'::json;

            v_offset_prev INT;
            v_offset_curr INT;
            v_offset_next INT;

            v_sort_key TEXT;
            v_order_clause TEXT := 'ORDER BY ai.id DESC';
            v_prefetch TEXT;

            v_search_clause TEXT := '';
            inner_sql TEXT;
        BEGIN
            ------------------------------------------------------------------
            -- Normalize inputs
            ------------------------------------------------------------------
            v_sort_key := LOWER(REPLACE(REPLACE(TRIM(COALESCE(p_sort_by, 'newest')), '-', ''), '_', ''));
            v_prefetch := LOWER(TRIM(COALESCE(p_prefetch_mode, 'both')));

            CASE v_sort_key
                WHEN 'newest' THEN v_order_clause := 'ORDER BY ai.id DESC';
                WHEN 'oldest' THEN v_order_clause := 'ORDER BY ai.id ASC';
                WHEN 'az' THEN v_order_clause := 'ORDER BY ai.model_number ASC NULLS LAST, ai.id DESC';
                WHEN 'za' THEN v_order_clause := 'ORDER BY ai.model_number DESC NULLS LAST, ai.id DESC';
                ELSE v_order_clause := 'ORDER BY ai.id DESC';
            END CASE;

            ------------------------------------------------------------------
            -- Search clause
            ------------------------------------------------------------------
            IF p_search_text IS NOT NULL AND TRIM(p_search_text) <> '' THEN
                v_search_clause := format(
                    ' AND (
                        ai.model_number::TEXT ILIKE %1$L
                        OR ai.serial_number::TEXT ILIKE %1$L
                        OR ai.asset_tag::TEXT ILIKE %1$L
                    )',
                    '%' || TRIM(p_search_text) || '%'
                );
            END IF;

            ------------------------------------------------------------------
            -- Validation
            ------------------------------------------------------------------
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN json_build_object(
                    'status','FAILURE',
                    'message','Invalid tenant ID',
                    'data', json_build_object('previous','[]','current','[]','next','[]')
                );
            END IF;

            IF p_supplier_id IS NULL OR p_supplier_id <= 0 THEN
                RETURN json_build_object(
                    'status','FAILURE',
                    'message','Invalid supplier ID',
                    'data', json_build_object('previous','[]','current','[]','next','[]')
                );
            END IF;

            ------------------------------------------------------------------
            -- Supplier existence
            ------------------------------------------------------------------
            IF NOT EXISTS (
                SELECT 1
                FROM suppliers s
                WHERE s.id = p_supplier_id
                AND s.tenant_id = p_tenant_id
                AND s.deleted_at IS NULL
            ) THEN
                RETURN json_build_object(
                    'status','FAILURE',
                    'message','Supplier not found',
                    'data', json_build_object('previous','[]','current','[]','next','[]')
                );
            END IF;

            p_page_no := GREATEST(p_page_no, 1);
            p_page_size := GREATEST(p_page_size, 1);

            ------------------------------------------------------------------
            -- Count assets (WITH SEARCH)
            ------------------------------------------------------------------
            EXECUTE format('
                SELECT COUNT(*)
                FROM asset_items ai
                WHERE ai.tenant_id = %L
                AND ai.supplier = %L
                AND ai.deleted_at IS NULL
                %s
            ',
                p_tenant_id,
                p_supplier_id,
                v_search_clause
            )
            INTO asset_count;

            IF asset_count = 0 THEN
                RETURN json_build_object(
                    'status','NO_RECORDS',
                    'message','No assets found',
                    'meta', json_build_object(
                        'total_records',0,
                        'total_pages',0,
                        'current_page',p_page_no,
                        'page_size',p_page_size
                    ),
                    'data', json_build_object('previous','[]','current','[]','next','[]')
                );
            END IF;

            v_total_pages := CEIL(asset_count::DECIMAL / p_page_size);

            ------------------------------------------------------------------
            -- Offsets
            ------------------------------------------------------------------
            v_offset_curr := (p_page_no - 1) * p_page_size;
            v_offset_prev := GREATEST(v_offset_curr - p_page_size, 0);
            v_offset_next := p_page_no * p_page_size;

            ------------------------------------------------------------------
            -- Base query (REUSED)
            ------------------------------------------------------------------
            inner_sql := format('
                SELECT
                    ai.id,
                    ai.asset_id,
                    ai.model_number,
                    ai.serial_number,
                    ai.asset_tag,
                    ai.thumbnail_image,
                    ai.item_value,
                    ai.item_value_currency_id,
                    ai.created_at,
                    ai.updated_at,
                    ai.qr_code,
                    u.name AS responsible_person_name,
                    ai.manufacturer,
                    a.category AS category_id,
                    ac.name AS category_name,
                    ac.assets_type AS assets_type_id,
                    assc.name AS sub_category_name,
                    arct.name AS received_condition_name
                FROM asset_items ai
                LEFT JOIN users u ON ai.responsible_person = u.id
                INNER JOIN assets a ON ai.asset_id = a.id
                INNER JOIN asset_categories ac ON a.category = ac.id
                INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
                LEFT JOIN asset_received_condition_types arct ON ai.received_condition::BIGINT = arct.id
                WHERE ai.tenant_id = %L
                AND ai.isactive = TRUE
                AND ai.supplier = %L
                AND ai.deleted_at IS NULL
                %s
                %s
                LIMIT %s OFFSET %s
            ',
                p_tenant_id,
                p_supplier_id,
                v_search_clause,
                v_order_clause,
                p_page_size,
                v_offset_curr
            );

            ------------------------------------------------------------------
            -- CURRENT PAGE
            ------------------------------------------------------------------
            EXECUTE 'SELECT json_agg(row_to_json(t)) FROM (' || inner_sql || ') t'
            INTO v_data_curr;

            v_data_curr := COALESCE(v_data_curr, '[]'::json);

            ------------------------------------------------------------------
            -- PREVIOUS PAGE
            ------------------------------------------------------------------
            IF v_prefetch = 'both' AND p_page_no > 1 THEN
                EXECUTE replace(inner_sql,
                    format('OFFSET %s', v_offset_curr),
                    format('OFFSET %s', v_offset_prev)
                )
                INTO v_data_prev;

                v_data_prev := COALESCE(v_data_prev, '[]'::json);
            END IF;

            ------------------------------------------------------------------
            -- NEXT PAGE
            ------------------------------------------------------------------
            IF v_prefetch IN ('both','after') AND p_page_no < v_total_pages THEN
                EXECUTE replace(inner_sql,
                    format('OFFSET %s', v_offset_curr),
                    format('OFFSET %s', v_offset_next)
                )
                INTO v_data_next;

                v_data_next := COALESCE(v_data_next, '[]'::json);
            END IF;

            ------------------------------------------------------------------
            -- FINAL RESPONSE
            ------------------------------------------------------------------
            RETURN json_build_object(
                'status','SUCCESS',
                'message','Supplier assets fetched successfully',
                'meta', json_build_object(
                    'total_records', asset_count,
                    'total_pages', v_total_pages,
                    'current_page', p_page_no,
                    'page_size', p_page_size,
                    'prefetch_mode', v_prefetch,
                    'sort_by', v_sort_key,
                    'search_text', p_search_text
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
                WHERE proname = 'get_supplier_related_assets'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;
        SQL);
    }
};
