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
                WHERE proname = 'get_asset_audit_variables_for_drawer'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_asset_audit_variables_for_drawer(
            p_tenant_id BIGINT,
            p_variable_id BIGINT DEFAULT NULL,
            p_variable_type_id BIGINT DEFAULT NULL,
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
            v_order_clause TEXT := 'ORDER BY aav.id DESC';
            v_message TEXT := '';
            v_base_query TEXT := '';
            v_search_clause TEXT := '';
            v_type_clause TEXT := '';
            v_variable_types JSON := '[]'::JSON;
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
                        'total_records', 0,
                        'total_pages', 0,
                        'current_page', p_page_no,
                        'page_size', p_page_size
                    )
                );
            END IF;

            ----------------------------------------------------------------
            -- Sorting Logic
            ----------------------------------------------------------------
            CASE LOWER(TRIM(p_sort_by))
                WHEN 'newest' THEN v_order_clause := 'ORDER BY aav.id DESC';
                WHEN 'oldest' THEN v_order_clause := 'ORDER BY aav.id ASC';
                WHEN 'az' THEN v_order_clause := 'ORDER BY aav.name ASC NULLS LAST';
                WHEN 'za' THEN v_order_clause := 'ORDER BY aav.name DESC NULLS LAST';
                ELSE v_order_clause := 'ORDER BY aav.id DESC';
            END CASE;

            p_page_no := GREATEST(p_page_no, 1);
            p_page_size := GREATEST(p_page_size, 1);

            ----------------------------------------------------------------
            -- Search clause
            ----------------------------------------------------------------
            IF p_search IS NOT NULL AND LENGTH(TRIM(p_search)) > 0 THEN
                v_search_clause := format(
                    'AND (aav.name ILIKE %L OR aav.description ILIKE %L OR aavt.name ILIKE %L)',
                    '%' || p_search || '%',
                    '%' || p_search || '%',
                    '%' || p_search || '%'
                );
            END IF;

            ----------------------------------------------------------------
            -- Variable type clause
            ----------------------------------------------------------------
            IF p_variable_type_id IS NOT NULL AND p_variable_type_id > 0 THEN
                v_type_clause := format('AND aav.asset_audit_variable_type_id = %s', p_variable_type_id);
            END IF;

            ----------------------------------------------------------------
            -- Build base query
            ----------------------------------------------------------------
            v_base_query := format($b$
                FROM asset_audit_variable aav
                LEFT JOIN asset_audit_variable_type aavt ON aavt.id = aav.asset_audit_variable_type_id
                WHERE aav.tenant_id = %s
                AND aav.deleted_at IS NULL
                AND aav.is_active = TRUE
                AND (aavt.deleted_at IS NULL OR aavt.deleted_at IS NOT NULL)
                AND (aavt.is_active = TRUE OR aavt.is_active IS NOT NULL)
                %s
                %s
                AND (%L IS NULL OR aav.id = %L)
            $b$, p_tenant_id, v_search_clause, v_type_clause, p_variable_id, p_variable_id);

            v_message := 'Asset audit variables retrieved successfully';

            ----------------------------------------------------------------
            -- Count total records
            ----------------------------------------------------------------
            EXECUTE format($s$
                SELECT COUNT(DISTINCT aav.id)
                %s
            $s$, v_base_query)
            INTO v_total_records;

            ----------------------------------------------------------------
            -- Variable types list (A → Z)
            ----------------------------------------------------------------
            SELECT COALESCE(
                json_agg(
                    json_build_object(
                        'id', aavt.id,
                        'name', aavt.name
                    )
                    ORDER BY aavt.name ASC
                ),
                '[]'::JSON
            ) INTO v_variable_types
            FROM asset_audit_variable_type aavt
            WHERE (aavt.tenant_id = p_tenant_id OR aavt.tenant_id IS NULL)
            AND aavt.deleted_at IS NULL
            AND aavt.is_active = TRUE;
            
            v_variable_types := COALESCE(v_variable_types, '[]'::JSON);

            IF v_total_records = 0 THEN
                RETURN json_build_object(
                    'status', 'SUCCESS',
                    'message', 'No asset audit variables found',
                    'success', TRUE,
                    'data', json_build_object(
                        'previous', '[]',
                        'current', '[]',
                        'next', '[]'
                    ),
                    'pagination', json_build_object(
                        'total_records', 0,
                        'total_pages', 0,
                        'current_page', p_page_no,
                        'page_size', p_page_size
                    ),
                    'variable_types', v_variable_types
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
            EXECUTE format($s$
                SELECT COALESCE(json_agg(t), '[]'::JSON)
                FROM (
                    SELECT 
                        json_build_object(
                            'status', 'SUCCESS',
                            'message', %L,
                            'id', aav.id,
                            'name', aav.name,
                            'description', aav.description,
                            'variable_type_id', aav.asset_audit_variable_type_id,
                            'variable_type_name', aavt.name,
                            'is_active', aav.is_active,
                            'created_at', aav.created_at,
                            'updated_at', aav.updated_at
                        ) AS data
                    %s
                    %s
                    LIMIT %s OFFSET %s
                ) t
            $s$, v_message, v_base_query, v_order_clause, p_page_size, v_offset_curr)
            INTO v_data_curr;
            v_data_curr := COALESCE(v_data_curr, '[]'::JSON);

            ----------------------------------------------------------------
            -- Previous page (if prefetch)
            ----------------------------------------------------------------
            IF p_prefetch_mode = 'both' AND p_page_no > 1 THEN
                EXECUTE format($s$
                    SELECT COALESCE(json_agg(t), '[]'::JSON)
                    FROM (
                        SELECT 
                            json_build_object(
                                'status', 'SUCCESS',
                                'message', %L,
                                'id', aav.id,
                                'name', aav.name,
                                'description', aav.description,
                                'variable_type_id', aav.asset_audit_variable_type_id,
                                'variable_type_name', aavt.name,
                                'is_active', aav.is_active,
                                'created_at', aav.created_at,
                                'updated_at', aav.updated_at
                            ) AS data
                        %s
                        %s
                        LIMIT %s OFFSET %s
                    ) t
                $s$, v_message, v_base_query, v_order_clause, p_page_size, v_offset_prev)
                INTO v_data_prev;
                v_data_prev := COALESCE(v_data_prev, '[]'::JSON);
            END IF;

            ----------------------------------------------------------------
            -- Next page (if prefetch)
            ----------------------------------------------------------------
            IF (p_prefetch_mode = 'both' OR p_prefetch_mode = 'after') AND p_page_no < v_total_pages THEN
                EXECUTE format($s$
                    SELECT COALESCE(json_agg(t), '[]'::JSON)
                    FROM (
                        SELECT 
                            json_build_object(
                                'status', 'SUCCESS',
                                'message', %L,
                                'id', aav.id,
                                'name', aav.name,
                                'description', aav.description,
                                'variable_type_id', aav.asset_audit_variable_type_id,
                                'variable_type_name', aavt.name,
                                'is_active', aav.is_active,
                                'created_at', aav.created_at,
                                'updated_at', aav.updated_at
                            ) AS data
                        %s
                        %s
                        LIMIT %s OFFSET %s
                    ) t
                $s$, v_message, v_base_query, v_order_clause, p_page_size, v_offset_next)
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
                    'total_records', v_total_records,
                    'total_pages', v_total_pages,
                    'current_page', p_page_no,
                    'page_size', p_page_size
                ),
                'variable_types', v_variable_types
            );

        END;
        $$;

        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_audit_variables_for_drawer(BIGINT, BIGINT, BIGINT, INT, INT, TEXT, TEXT, TEXT);');
    }
};