<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<SQL
        DO $$
        DECLARE
            r RECORD;
        BEGIN
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'get_supplier_related_items'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_supplier_related_items(
            p_tenant_id BIGINT,
            p_supplier_id BIGINT,
            p_page_no INT DEFAULT 1,
            p_page_size INT DEFAULT 10,
            p_prefetch_mode TEXT DEFAULT 'both', -- none | after | both
            p_sort_by TEXT DEFAULT 'newest'
        )
        RETURNS JSON
        LANGUAGE plpgsql
        AS $$
        DECLARE
            item_count INT;
            v_total_pages INT;

            v_data_prev JSON := '[]'::json;
            v_data_curr JSON := '[]'::json;
            v_data_next JSON := '[]'::json;

            v_offset_prev INT;
            v_offset_curr INT;
            v_offset_next INT;

            v_sort_key TEXT;
            v_order_clause TEXT := 'ORDER BY i.id DESC';
            v_prefetch TEXT;

            inner_sql TEXT;
        BEGIN
            ------------------------------------------------------------------
            -- Normalize inputs
            ------------------------------------------------------------------
            v_sort_key := LOWER(REPLACE(REPLACE(TRIM(COALESCE(p_sort_by,'newest')),'-',''),'_',''));
            v_prefetch := LOWER(TRIM(COALESCE(p_prefetch_mode,'both')));

            CASE v_sort_key
                WHEN 'newest' THEN v_order_clause := 'ORDER BY i.id DESC';
                WHEN 'oldest' THEN v_order_clause := 'ORDER BY i.id ASC';
                WHEN 'az' THEN v_order_clause := 'ORDER BY i.item_name ASC NULLS LAST, i.id DESC';
                WHEN 'za' THEN v_order_clause := 'ORDER BY i.item_name DESC NULLS LAST, i.id DESC';
                ELSE v_order_clause := 'ORDER BY i.id DESC';
            END CASE;

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
            -- Count items
            ------------------------------------------------------------------
            SELECT COUNT(*) INTO item_count
            FROM items i
            INNER JOIN suppliers_for_item si
                ON i.id = si.master_item_id
            AND si.supplier_id = p_supplier_id
            AND si.deleted_at IS NULL
            WHERE i.tenant_id = p_tenant_id
            AND i.deleted_at IS NULL
            AND i.isactive = TRUE;

            IF item_count = 0 THEN
                RETURN json_build_object(
                    'status','FAILURE',
                    'message','No items found',
                    'meta', json_build_object(
                        'total_records',0,
                        'total_pages',0,
                        'current_page',p_page_no,
                        'page_size',p_page_size
                    ),
                    'data', json_build_object('previous','[]','current','[]','next','[]')
                );
            END IF;

            v_total_pages := CEIL(item_count::DECIMAL / p_page_size);

            ------------------------------------------------------------------
            -- Offsets
            ------------------------------------------------------------------
            v_offset_curr := (p_page_no - 1) * p_page_size;
            v_offset_prev := GREATEST(v_offset_curr - p_page_size, 0);
            v_offset_next := p_page_no * p_page_size;

            ------------------------------------------------------------------
            -- CURRENT PAGE
            ------------------------------------------------------------------
            inner_sql := format('
                SELECT
                    i.id,
                    i.item_id,
                    i.item_name,
                    i.type_id,
                    t.name AS type_name,
                    i.category_id,
                    c.name AS category_name,
                    i.item_description,
                    i.purchase_price,
                    i.selling_price,
                    i.unit_of_measure_id,
                    i.low_stock_alert,
                    i.over_stock_alert,
                    i.image_links
                FROM items i
                INNER JOIN suppliers_for_item si
                    ON i.id = si.master_item_id
                AND si.supplier_id = %L
                AND si.deleted_at IS NULL
                LEFT JOIN item_categories c
                    ON i.category_id = c.id
                AND c.deleted_at IS NULL
                LEFT JOIN item_types t
                    ON i.type_id = t.id
                AND t.deleted_at IS NULL
                WHERE i.tenant_id = %L
                AND i.deleted_at IS NULL
                AND i.isactive = TRUE
                %s
                LIMIT %s OFFSET %s
            ',
                p_supplier_id,
                p_tenant_id,
                v_order_clause,
                p_page_size,
                v_offset_curr
            );

            EXECUTE 'SELECT json_agg(row_to_json(t)) FROM ('||inner_sql||') t'
            INTO v_data_curr;

            v_data_curr := COALESCE(v_data_curr,'[]'::json);

            ------------------------------------------------------------------
            -- PREVIOUS PAGE
            ------------------------------------------------------------------
            IF v_prefetch = 'both' AND p_page_no > 1 THEN
                EXECUTE replace(inner_sql,
                    format('OFFSET %s', v_offset_curr),
                    format('OFFSET %s', v_offset_prev)
                )
                INTO v_data_prev;

                v_data_prev := COALESCE(v_data_prev,'[]'::json);
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

                v_data_next := COALESCE(v_data_next,'[]'::json);
            END IF;

            ------------------------------------------------------------------
            -- FINAL RESPONSE
            ------------------------------------------------------------------
            RETURN json_build_object(
                'status','SUCCESS',
                'message','Supplier related items fetched successfully',
                'meta', json_build_object(
                    'total_records', item_count,
                    'total_pages', v_total_pages,
                    'current_page', p_page_no,
                    'page_size', p_page_size,
                    'prefetch_mode', v_prefetch,
                    'sort_by', v_sort_key
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
                WHERE proname = 'get_supplier_related_items'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;
        SQL);
    }
};
