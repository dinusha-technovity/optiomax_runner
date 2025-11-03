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
            p_prefetch_mode TEXT DEFAULT 'both',  -- options: 'none', 'after', 'both'
            p_sort_by TEXT DEFAULT NULL
        )
        RETURNS JSON
        LANGUAGE plpgsql
        AS $$
        DECLARE
            supplier_count INT;
            v_total_pages INT;
            v_data_prev JSON;
            v_data_curr JSON;
            v_data_next JSON;
            v_offset_prev INT;
            v_offset_curr INT;
            v_offset_next INT;
            v_order_clause TEXT := 'ORDER BY s.id DESC'; -- default sorting
        BEGIN
            -- Determine order by clause
            CASE LOWER(TRIM(p_sort_by))
                WHEN 'newest' THEN v_order_clause := 'ORDER BY s.id DESC';
                WHEN 'oldest' THEN v_order_clause := 'ORDER BY s.id ASC';
                WHEN 'az' THEN v_order_clause := 'ORDER BY s.name ASC NULLS LAST';
                WHEN 'za' THEN v_order_clause := 'ORDER BY s.name DESC NULLS LAST';
                ELSE v_order_clause := 'ORDER BY s.id DESC';
            END CASE;

            -- Validate tenant
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN json_build_object(
                    'status', 'FAILURE',
                    'message', 'Invalid tenant ID',
                    'data', json_build_object('previous', '[]'::json, 'current', '[]'::json, 'next', '[]'::json)
                );
            END IF;

            p_page_no := GREATEST(p_page_no, 1);
            p_page_size := GREATEST(p_page_size, 1);

            -- Count total records
            SELECT COUNT(*) INTO supplier_count
            FROM suppliers s
            WHERE s.tenant_id = p_tenant_id
            AND s.deleted_at IS NULL
            AND s.isactive = TRUE
            AND (p_supplier_id IS NULL OR s.id = p_supplier_id)
            AND (p_status IS NULL OR s.supplier_reg_status = p_status)
            AND (
                    p_search IS NULL OR
                    s.name ILIKE '%' || p_search || '%' OR
                    s.supplier_primary_email ILIKE '%' || p_search || '%' OR
                    s.supplier_secondary_email ILIKE '%' || p_search || '%' OR
                    s.supplier_website ILIKE '%' || p_search || '%'
                );

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
                    'data', json_build_object('previous', '[]'::json, 'current', '[]'::json, 'next', '[]'::json)
                );
            END IF;

            v_total_pages := CEIL(supplier_count::DECIMAL / p_page_size);

            -- Calculate offsets
            v_offset_curr := (p_page_no - 1) * p_page_size;
            v_offset_prev := GREATEST(v_offset_curr - p_page_size, 0);
            v_offset_next := (p_page_no) * p_page_size;

            -- Current page data
            EXECUTE format($sql$
                SELECT json_agg(row_to_json(t)) FROM (
                    SELECT
                        s.id,
                        s.name,
                        s.contact_no,
                        s.address,
                        s.description,
                        s.supplier_type,
                        s.supplier_reg_no,
                        s.supplier_reg_status,
                        s.supplier_asset_classes,
                        s.supplier_rating,
                        s.supplier_business_name,
                        s.supplier_business_register_no,
                        s.supplier_primary_email,
                        s.supplier_secondary_email,
                        s.supplier_br_attachment,
                        s.supplier_website,
                        s.supplier_tel_no,
                        s.contact_no_code,
                        s.supplier_mobile,
                        s.mobile_no_code,
                        s.supplier_fax,
                        s.supplier_city,
                        s.supplier_location_latitude,
                        s.supplier_location_longitude,
                        s.email,
                        s.asset_categories
                    FROM suppliers s
                    WHERE s.tenant_id = %L
                    AND s.deleted_at IS NULL
                    AND s.isactive = TRUE
                    AND (%L IS NULL OR s.id = %L)
                    AND (%L IS NULL OR s.supplier_reg_status = %L)
                    AND (
                            %L IS NULL OR
                            s.name ILIKE '%%' || %L || '%%' OR
                            s.supplier_primary_email ILIKE '%%' || %L || '%%' OR
                            s.supplier_secondary_email ILIKE '%%' || %L || '%%' OR
                            s.supplier_website ILIKE '%%' || %L || '%%'
                        )
                    %s
                    LIMIT %s OFFSET %s
                ) t
            $sql$, p_tenant_id, p_supplier_id, p_supplier_id, p_status, p_status, p_search, p_search, p_search, p_search, p_search, v_order_clause, p_page_size, v_offset_curr)
            INTO v_data_curr;

            -- Prefetch previous page
            IF p_prefetch_mode IN ('both') AND p_page_no > 1 THEN
                EXECUTE format($sql$
                    SELECT json_agg(row_to_json(t)) FROM (
                        SELECT
                            s.*
                        FROM suppliers s
                        WHERE s.tenant_id = %L
                        AND s.deleted_at IS NULL
                        AND s.isactive = TRUE
                        %s
                        LIMIT %s OFFSET %s
                    ) t
                $sql$, p_tenant_id, v_order_clause, p_page_size, v_offset_prev)
                INTO v_data_prev;
            END IF;

            -- Prefetch next page
            IF p_prefetch_mode IN ('both', 'after') AND p_page_no < v_total_pages THEN
                EXECUTE format($sql$
                    SELECT json_agg(row_to_json(t)) FROM (
                        SELECT
                            s.*
                        FROM suppliers s
                        WHERE s.tenant_id = %L
                        AND s.deleted_at IS NULL
                        AND s.isactive = TRUE
                        %s
                        LIMIT %s OFFSET %s
                    ) t
                $sql$, p_tenant_id, v_order_clause, p_page_size, v_offset_next)
                INTO v_data_next;
            END IF;

            -- Final JSON output
            RETURN json_build_object(
                'status', 'SUCCESS',
                'message', 'Suppliers fetched successfully',
                'meta', json_build_object(
                    'total_records', supplier_count,
                    'total_pages', v_total_pages,
                    'current_page', p_page_no,
                    'page_size', p_page_size,
                    'prefetch_mode', p_prefetch_mode,
                    'sort_by', p_sort_by
                ),
                'data', json_build_object(
                    'previous', COALESCE(v_data_prev, '[]'::json),
                    'current', COALESCE(v_data_curr, '[]'::json),
                    'next', COALESCE(v_data_next, '[]'::json)
                )
            );
        END;
        $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_suppliers');
    }
};
