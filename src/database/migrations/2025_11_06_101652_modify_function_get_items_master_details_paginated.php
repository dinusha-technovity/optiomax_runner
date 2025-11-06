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
                WHERE proname = 'get_items_master_details'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_items_master_details(
            p_tenant_id BIGINT,
            p_item_id BIGINT DEFAULT NULL,
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
            v_order_clause TEXT := 'ORDER BY i.id DESC';
            inner_sql TEXT;
        BEGIN
            -- Sorting logic
            CASE LOWER(TRIM(p_sort_by))
                WHEN 'newest' THEN v_order_clause := 'ORDER BY i.id DESC';
                WHEN 'oldest' THEN v_order_clause := 'ORDER BY i.id ASC';
                WHEN 'az' THEN v_order_clause := 'ORDER BY i.item_name ASC NULLS LAST';
                WHEN 'za' THEN v_order_clause := 'ORDER BY i.item_name DESC NULLS LAST';
                ELSE v_order_clause := 'ORDER BY i.id DESC';
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

            ----------------------------------------------------------------
            -- Count total records
            ----------------------------------------------------------------
            SELECT COUNT(*) INTO v_total_records
            FROM items i
            LEFT JOIN item_category_type ict ON i.item_category_type_id = ict.id
            LEFT JOIN item_categories c ON i.category_id = c.id AND c.deleted_at IS NULL
            LEFT JOIN item_types t ON i.type_id = t.id AND t.deleted_at IS NULL
            WHERE i.tenant_id = p_tenant_id
            AND i.deleted_at IS NULL
            AND i.isactive = TRUE
            AND (p_item_id IS NULL OR i.id = p_item_id)
            AND (
                p_search IS NULL OR
                i.item_name ILIKE '%' || p_search || '%' OR
                i.item_description ILIKE '%' || p_search || '%' OR
                i.item_id::TEXT ILIKE '%' || p_search || '%' OR
                c.name ILIKE '%' || p_search || '%' OR
                t.name ILIKE '%' || p_search || '%'
            );

            IF v_total_records = 0 THEN
                RETURN json_build_object(
                    'status', 'FAILURE',
                    'message', 'No items found',
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

            ----------------------------------------------------------------
            -- Common SELECT Template
            ----------------------------------------------------------------
            inner_sql := format($SQL$
                SELECT 
                    i.id,
                    i.item_category_type_id,
                    ict.name AS item_category_type_name,
                    i.item_id,
                    i.item_name,
                    t.name AS type_name,
                    c.name AS category_name,
                    i.item_description,
                    i.isactive AS is_active,
                    i.purchase_price,
                    i.selling_price,
                    i.max_inventory_level,
                    i.min_inventory_level,
                    i.re_order_level,
                    i.type_id,
                    i.category_id,
                    i.unit_of_measure_id,
                    i.purchase_price_currency_id,
                    i.selling_price_currency_id,
                    i.low_stock_alert,
                    i.over_stock_alert,
                    i.image_links,
                    COALESCE(
                        jsonb_agg(
                            jsonb_build_object(
                                'id', si.supplier_id,
                                'name', s.name,
                                'email', s.email,
                                'lead_time', si.lead_time
                            )
                        ) FILTER (WHERE si.supplier_id IS NOT NULL),
                        '[]'::JSONB
                    ) AS suppliers
                FROM items i
                LEFT JOIN item_category_type ict ON i.item_category_type_id = ict.id
                LEFT JOIN item_categories c ON i.category_id = c.id AND c.deleted_at IS NULL
                LEFT JOIN item_types t ON i.type_id = t.id AND t.deleted_at IS NULL
                LEFT JOIN suppliers_for_item si ON i.id = si.master_item_id AND si.deleted_at IS NULL
                LEFT JOIN suppliers s ON si.supplier_id = s.id AND s.deleted_at IS NULL
                WHERE i.tenant_id = %L
                AND i.isactive = TRUE
                AND i.deleted_at IS NULL
                AND (%L IS NULL OR i.id = %L)
                AND (
                    %L IS NULL OR
                    i.item_name ILIKE '%%' || %L || '%%' OR
                    i.item_description ILIKE '%%' || %L || '%%' OR
                    i.item_id::TEXT ILIKE '%%' || %L || '%%' OR
                    c.name ILIKE '%%' || %L || '%%' OR
                    t.name ILIKE '%%' || %L || '%%'
                )
                GROUP BY i.id, ict.name, t.name, c.name
                %s
                LIMIT %s OFFSET %s
            $SQL$,
                p_tenant_id, p_item_id, p_item_id,
                p_search, p_search, p_search, p_search, p_search, p_search,
                v_order_clause, p_page_size, v_offset_curr
            );

            EXECUTE 'SELECT json_agg(row_to_json(t)) FROM (' || inner_sql || ') t'
            INTO v_data_curr;
            v_data_curr := COALESCE(v_data_curr, '[]'::json);

            ----------------------------------------------------------------
            -- PREVIOUS PAGE
            ----------------------------------------------------------------
            IF p_prefetch_mode = 'both' AND p_page_no > 1 THEN
                inner_sql := replace(inner_sql, format('OFFSET %s', v_offset_curr::TEXT), format('OFFSET %s', v_offset_prev::TEXT));
                EXECUTE 'SELECT json_agg(row_to_json(t)) FROM (' || inner_sql || ') t'
                INTO v_data_prev;
                v_data_prev := COALESCE(v_data_prev, '[]'::json);
            END IF;

            ----------------------------------------------------------------
            -- NEXT PAGE
            ----------------------------------------------------------------
            IF p_prefetch_mode IN ('both', 'after') AND p_page_no < v_total_pages THEN
                inner_sql := replace(inner_sql, format('OFFSET %s', v_offset_curr::TEXT), format('OFFSET %s', v_offset_next::TEXT));
                EXECUTE 'SELECT json_agg(row_to_json(t)) FROM (' || inner_sql || ') t'
                INTO v_data_next;
                v_data_next := COALESCE(v_data_next, '[]'::json);
            END IF;

            ----------------------------------------------------------------
            -- Final JSON output
            ----------------------------------------------------------------
            RETURN json_build_object(
                'status', 'SUCCESS',
                'message', CASE WHEN p_item_id IS NULL THEN 'Items fetched successfully' ELSE 'Item details fetched successfully' END,
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
        $$
        
        SQL);
    }

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
                    WHERE proname = 'get_items_master_details'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
        SQL);
    }
};