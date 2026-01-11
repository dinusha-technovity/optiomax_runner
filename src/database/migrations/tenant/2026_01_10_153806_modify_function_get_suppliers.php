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
        // 1. Drop every existing overload of get_suppliers
        DB::unprepared(<<<'SQL'
          
                DO $$
                    DECLARE
                        r RECORD;
                    BEGIN
                        FOR r IN
                            SELECT oid::regprocedure::text AS func_signature
                            FROM pg_proc
                            WHERE proname = 'get_suppliers'
                        LOOP
                            EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                        END LOOP;
                    END$$;
                    
        CREATE OR REPLACE FUNCTION get_suppliers(
            p_tenant_id BIGINT,
            p_supplier_id INT DEFAULT NULL,
            p_page_no INT DEFAULT 1,
            p_page_size INT DEFAULT 10,
            p_search TEXT DEFAULT NULL,
            p_status TEXT DEFAULT NULL,
            p_prefetch_mode TEXT DEFAULT 'both',  -- none | after | both
            p_sort_by TEXT DEFAULT NULL
        )
        RETURNS JSON
        LANGUAGE plpgsql
        AS $$
        DECLARE
            supplier_count INT;
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
                    WHEN 'newest' THEN 'ORDER BY s.created_at DESC, s.id DESC'
                    WHEN 'oldest' THEN 'ORDER BY s.created_at ASC,  s.id ASC'
                    WHEN 'az'     THEN 'ORDER BY s.name ASC  NULLS LAST, s.id DESC'
                    WHEN 'za'     THEN 'ORDER BY s.name DESC NULLS LAST, s.id DESC'
                    ELSE               'ORDER BY s.created_at DESC, s.id DESC'
                END;

            -------------------------------------------------------------------
            -- Base WHERE clause (single source of truth)
            -------------------------------------------------------------------
            v_base_sql := '
                FROM suppliers s
                WHERE s.tenant_id = $1
                AND s.deleted_at IS NULL
                AND s.isactive = TRUE
                AND ($2 IS NULL OR s.id = $2)
                AND ($3 IS NULL OR s.supplier_reg_status = $3)
                AND (
                    $4 IS NULL OR
                    s.name ILIKE ''%'' || $4 || ''%'' OR
                    s.email ILIKE ''%'' || $4 || ''%'' OR
                    s.supplier_reg_no ILIKE ''%'' || $4 || ''%'' OR
                    s.supplier_primary_email ILIKE ''%'' || $4 || ''%'' OR
                    s.supplier_secondary_email ILIKE ''%'' || $4 || ''%'' OR
                    s.supplier_website ILIKE ''%'' || $4 || ''%'' OR
                    (
                        $4 ~ ''[0-9]'' AND
                        EXISTS (
                            SELECT 1
                            FROM jsonb_array_elements(
                                COALESCE(s.contact_no::jsonb, ''[]''::jsonb)
                            ) elem
                            WHERE
                                regexp_replace(elem->>''contact_no'', ''\D'', '''', ''g'')
                                ILIKE ''%'' ||
                                regexp_replace(
                                    split_part(
                                        $4,
                                        '' '',
                                        array_length(string_to_array($4, '' ''), 1)
                                    ),
                                    ''\D'',
                                    '''',
                                    ''g''
                                ) || ''%''
                        )
                    )
                )
            ';

            -------------------------------------------------------------------
            -- Total count
            -------------------------------------------------------------------
            EXECUTE 'SELECT COUNT(*) ' || v_base_sql
            INTO supplier_count
            USING p_tenant_id, p_supplier_id, p_status, p_search;

            IF supplier_count = 0 THEN
                RETURN json_build_object(
                    'status', 'FAILURE',
                    'message', 'No suppliers found',
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

            v_total_pages := CEIL(supplier_count::NUMERIC / p_page_size);

            IF p_page_no > v_total_pages THEN
                p_page_no := v_total_pages;
            END IF;

            v_offset_curr := (p_page_no - 1) * p_page_size;
            v_offset_prev := GREATEST(v_offset_curr - p_page_size, 0);
            v_offset_next := p_page_no * p_page_size;

            -------------------------------------------------------------------
            -- Current page
            -------------------------------------------------------------------
            EXECUTE format(
                'SELECT jsonb_agg(row_to_json(t)) FROM (
                    SELECT
                        s.id, s.name, s.contact_no, s.address, s.description,
                        s.supplier_type, s.supplier_reg_no, s.supplier_reg_status,
                        s.supplier_asset_classes, s.supplier_rating,
                        s.supplier_business_name, s.supplier_business_register_no,
                        s.supplier_primary_email, s.supplier_secondary_email,
                        s.supplier_br_attachment, s.supplier_website,
                        s.supplier_tel_no, s.contact_no_code,
                        s.supplier_mobile, s.mobile_no_code,
                        s.supplier_fax, s.supplier_city,
                        s.supplier_location_latitude, s.supplier_location_longitude,
                        s.email, s.asset_categories
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
            USING p_tenant_id, p_supplier_id, p_status, p_search;

            -------------------------------------------------------------------
            -- Previous page
            -------------------------------------------------------------------
            IF v_prefetch_mode = 'both' AND p_page_no > 1 THEN
                EXECUTE format(
                    'SELECT jsonb_agg(row_to_json(t)) FROM (
                        SELECT * %s %s LIMIT %s OFFSET %s
                    ) t',
                    v_base_sql,
                    v_order_clause,
                    p_page_size,
                    v_offset_prev
                )
                INTO v_data_prev
                USING p_tenant_id, p_supplier_id, p_status, p_search;
            END IF;

            -------------------------------------------------------------------
            -- Next page
            -------------------------------------------------------------------
            IF v_prefetch_mode IN ('both', 'after') AND p_page_no < v_total_pages THEN
                EXECUTE format(
                    'SELECT jsonb_agg(row_to_json(t)) FROM (
                        SELECT * %s %s LIMIT %s OFFSET %s
                    ) t',
                    v_base_sql,
                    v_order_clause,
                    p_page_size,
                    v_offset_next
                )
                INTO v_data_next
                USING p_tenant_id, p_supplier_id, p_status, p_search;
            END IF;

            -------------------------------------------------------------------
            -- Final response
            -------------------------------------------------------------------
            RETURN json_build_object(
                'status', 'SUCCESS',
                'message', 'Suppliers fetched successfully',
                'meta', json_build_object(
                    'total_records', supplier_count,
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
                    WHERE proname = 'get_suppliers'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
        SQL);
    }
};
