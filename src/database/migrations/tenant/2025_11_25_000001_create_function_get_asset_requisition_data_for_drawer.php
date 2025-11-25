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
                WHERE proname = 'get_asset_requisition_data_for_drawer'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_asset_requisition_data_for_drawer(
            p_user_id BIGINT,
            p_tenant_id BIGINT,
            p_get_type BIGINT DEFAULT NULL,
            p_page_no INT DEFAULT 1,
            p_page_size INT DEFAULT 10,
            p_search TEXT DEFAULT NULL,
            p_prefetch_mode TEXT DEFAULT 'both',  -- 'none', 'after', 'both'
            p_sort_by TEXT DEFAULT 'newest'       -- 'newest', 'oldest', 'az', 'za'
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
            v_order_clause TEXT := 'ORDER BY ar.id DESC';
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

            IF p_user_id IS NULL OR p_user_id <= 0 THEN
                RETURN json_build_object(
                    'status', 'FAILURE',
                    'message', 'Invalid user ID provided',
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
                WHEN 'newest' THEN v_order_clause := 'ORDER BY ar.id DESC';
                WHEN 'oldest' THEN v_order_clause := 'ORDER BY ar.id ASC';
                WHEN 'az' THEN v_order_clause := 'ORDER BY ar.requisition_id ASC NULLS LAST';
                WHEN 'za' THEN v_order_clause := 'ORDER BY ar.requisition_id DESC NULLS LAST';
                ELSE v_order_clause := 'ORDER BY ar.id DESC';
            END CASE;

            p_page_no := GREATEST(p_page_no, 1);
            p_page_size := GREATEST(p_page_size, 1);

            ----------------------------------------------------------------
            -- Search clause
            ----------------------------------------------------------------
            IF p_search IS NOT NULL AND LENGTH(TRIM(p_search)) > 0 THEN
                v_search_clause := format(
                    'AND (ar.requisition_id ILIKE %L OR u.name ILIKE %L OR ac.name ILIKE %L)',
                    '%' || p_search || '%',
                    '%' || p_search || '%',
                    '%' || p_search || '%'
                );
            END IF;

            ----------------------------------------------------------------
            -- Build base query based on get_type
            ----------------------------------------------------------------
            IF p_get_type = 0 THEN
                -- Procurement staff user view
                v_base_query := format($BASE$
                    FROM procurement_staff ps
                    INNER JOIN asset_requisitions_items ari ON ps.asset_category = ari.asset_category
                    INNER JOIN asset_requisitions ar ON ari.asset_requisition_id = ar.id
                    INNER JOIN asset_categories ac ON ari.asset_category = ac.id
                    INNER JOIN asset_requisition_priority_types arpt ON ari.priority = arpt.id
                    LEFT JOIN asset_requisition_acquisition_types arat ON ari.acquisition_type = arat.id
                    LEFT JOIN public.users u ON u.id = CAST(ar.requisition_by AS BIGINT)
                    LEFT JOIN public.organization org ON org.id = ari.organization
                    WHERE ps.user_id = %s
                        AND ps.tenant_id = %s
                        AND ar.requisition_status = 'APPROVED'
                        %s
                $BASE$, p_user_id, p_tenant_id, v_search_clause);
                v_message := 'Procurement staff approved requisitions fetched successfully';

            ELSIF p_get_type IS NULL THEN
                -- Get all approved list (no user_id filter)
                v_base_query := format($BASE$
                    FROM asset_requisitions_items ari
                    INNER JOIN asset_requisitions ar ON ari.asset_requisition_id = ar.id
                    INNER JOIN asset_categories ac ON ari.asset_category = ac.id
                    INNER JOIN asset_requisition_priority_types arpt ON ari.priority = arpt.id
                    LEFT JOIN asset_requisition_acquisition_types arat ON ari.acquisition_type = arat.id
                    LEFT JOIN public.users u ON u.id = CAST(ar.requisition_by AS BIGINT)
                    LEFT JOIN public.organization org ON org.id = ari.organization
                    WHERE ar.tenant_id = %s
                        AND ar.requisition_status = 'APPROVED'
                        %s
                $BASE$, p_tenant_id, v_search_clause);
                v_message := 'All approved requisitions fetched successfully';

            ELSE
                -- Get specific requisition by ID
                v_base_query := format($BASE$
                    FROM asset_requisitions_items ari
                    INNER JOIN asset_requisitions ar ON ari.asset_requisition_id = ar.id
                    INNER JOIN asset_categories ac ON ari.asset_category = ac.id
                    INNER JOIN asset_requisition_priority_types arpt ON ari.priority = arpt.id
                    LEFT JOIN asset_requisition_acquisition_types arat ON ari.acquisition_type = arat.id
                    LEFT JOIN public.users u ON u.id = CAST(ar.requisition_by AS BIGINT)
                    LEFT JOIN public.organization org ON org.id = ari.organization
                    WHERE ar.id = %s
                        AND ar.requisition_status = 'APPROVED'
                        %s
                $BASE$, p_get_type, v_search_clause);
                v_message := 'Specific requisition fetched successfully';
            END IF;

            ----------------------------------------------------------------
            -- Count total records
            ----------------------------------------------------------------
            EXECUTE format($SQL$
                SELECT COUNT(DISTINCT ar.id)
                %s
            $SQL$, v_base_query)
            INTO v_total_records;

            IF v_total_records = 0 THEN
                RETURN json_build_object(
                    'status', 'SUCCESS',
                    'message', 'No approved requisitions found',
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
            EXECUTE format($SQL$
                SELECT COALESCE(json_agg(t), '[]'::JSON)
                FROM (
                    SELECT 
                        'SUCCESS'::TEXT as status,
                        '%s'::TEXT as message,
                        ar.id,
                        json_build_object(
                            'id', u.id,
                            'name', u.name,
                            'email', u.email
                        ) as user_details,
                        json_agg(
                            json_build_object(
                                'id', ari.id,
                                'item_name', ari.item_name,
                                'files', ari.files,
                                'budget', ari.budget,
                                'budget_currency', ari.budget_currency,
                                'period', ari.period,
                                'reason', ari.reason,
                                'priority', ari.priority,
                                'priority_name', arpt.name,
                                'quantity', ari.quantity,
                                'period_to', ari.period_to,
                                'suppliers', ari.suppliers,
                                'created_at', ari.created_at,
                                'updated_at', ari.updated_at,
                                'period_from', ari.period_from,
                                'item_details', ari.item_details,
                                'organization', json_build_object(
                                    'organizationName', org.data ->> 'organizationName',
                                    'email', org.data ->> 'email',
                                    'address', org.data ->> 'address',
                                    'website', org.data ->> 'website',
                                    'telephoneNumber', org.data ->> 'telephoneNumber',
                                    'organizationDescription', org.data ->> 'organizationDescription'
                                ),
                                'period_status', ari.period_status,
                                'required_date', ari.required_date,
                                'acquisition_type', json_build_object(
                                    'id', ari.acquisition_type,
                                    'name', arat.name
                                ),
                                'business_impact', ari.business_impact,
                                'consumables_kpi', ari.consumables_kpi,
                                'maintenance_kpi', ari.maintenance_kpi,
                                'availability_type', ari.availability_type,
                                'business_purpose', ari.business_purpose,
                                'expected_conditions', ari.expected_conditions,
                                'service_support_kpi', ari.service_support_kpi,
                                'asset_requisition_id', ari.asset_requisition_id
                            )
                        ) as items,
                        ar.created_at,
                        ar.updated_at,
                        ar.requisition_by,
                        ar.requisition_id,
                        ar.requisition_date,
                        ar.requisition_status
                    %s
                    GROUP BY ar.id, u.id, u.name, u.email, ar.created_at, ar.updated_at, 
                             ar.requisition_by, ar.requisition_id, ar.requisition_date, ar.requisition_status
                    %s
                    LIMIT %s OFFSET %s
                ) t
            $SQL$, v_message, v_base_query, v_order_clause, p_page_size, v_offset_curr)
            INTO v_data_curr;
            v_data_curr := COALESCE(v_data_curr, '[]'::JSON);

            ----------------------------------------------------------------
            -- Previous page (if prefetch)
            ----------------------------------------------------------------
            IF p_prefetch_mode = 'both' AND p_page_no > 1 THEN
                EXECUTE format($SQL$
                    SELECT COALESCE(json_agg(t), '[]'::JSON)
                    FROM (
                        SELECT 
                            'SUCCESS'::TEXT as status,
                            '%s'::TEXT as message,
                            ar.id,
                            json_build_object(
                                'id', u.id,
                                'name', u.name,
                                'email', u.email
                            ) as user_details,
                            json_agg(
                                json_build_object(
                                    'id', ari.id,
                                    'item_name', ari.item_name,
                                    'files', ari.files,
                                    'budget', ari.budget,
                                    'budget_currency', ari.budget_currency,
                                    'period', ari.period,
                                    'reason', ari.reason,
                                    'priority', ari.priority,
                                    'priority_name', arpt.name,
                                    'quantity', ari.quantity,
                                    'period_to', ari.period_to,
                                    'suppliers', ari.suppliers,
                                    'created_at', ari.created_at,
                                    'updated_at', ari.updated_at,
                                    'period_from', ari.period_from,
                                    'item_details', ari.item_details,
                                    'organization', json_build_object(
                                        'organizationName', org.data ->> 'organizationName',
                                        'email', org.data ->> 'email',
                                        'address', org.data ->> 'address',
                                        'website', org.data ->> 'website',
                                        'telephoneNumber', org.data ->> 'telephoneNumber',
                                        'organizationDescription', org.data ->> 'organizationDescription'
                                    ),
                                    'period_status', ari.period_status,
                                    'required_date', ari.required_date,
                                    'acquisition_type', json_build_object(
                                        'id', ari.acquisition_type,
                                        'name', arat.name
                                    ),
                                    'business_impact', ari.business_impact,
                                    'consumables_kpi', ari.consumables_kpi,
                                    'maintenance_kpi', ari.maintenance_kpi,
                                    'availability_type', ari.availability_type,
                                    'business_purpose', ari.business_purpose,
                                    'expected_conditions', ari.expected_conditions,
                                    'service_support_kpi', ari.service_support_kpi,
                                    'asset_requisition_id', ari.asset_requisition_id
                                )
                            ) as items,
                            ar.created_at,
                            ar.updated_at,
                            ar.requisition_by,
                            ar.requisition_id,
                            ar.requisition_date,
                            ar.requisition_status
                        %s
                        GROUP BY ar.id, u.id, u.name, u.email, ar.created_at, ar.updated_at, 
                                 ar.requisition_by, ar.requisition_id, ar.requisition_date, ar.requisition_status
                        %s
                        LIMIT %s OFFSET %s
                    ) t
                $SQL$, v_message, v_base_query, v_order_clause, p_page_size, v_offset_prev)
                INTO v_data_prev;
                v_data_prev := COALESCE(v_data_prev, '[]'::JSON);
            END IF;

            ----------------------------------------------------------------
            -- Next page (if prefetch)
            ----------------------------------------------------------------
            IF (p_prefetch_mode = 'both' OR p_prefetch_mode = 'after') AND p_page_no < v_total_pages THEN
                EXECUTE format($SQL$
                    SELECT COALESCE(json_agg(t), '[]'::JSON)
                    FROM (
                        SELECT 
                            'SUCCESS'::TEXT as status,
                            '%s'::TEXT as message,
                            ar.id,
                            json_build_object(
                                'id', u.id,
                                'name', u.name,
                                'email', u.email
                            ) as user_details,
                            json_agg(
                                json_build_object(
                                    'id', ari.id,
                                    'item_name', ari.item_name,
                                    'files', ari.files,
                                    'budget', ari.budget,
                                    'budget_currency', ari.budget_currency,
                                    'period', ari.period,
                                    'reason', ari.reason,
                                    'priority', ari.priority,
                                    'priority_name', arpt.name,
                                    'quantity', ari.quantity,
                                    'period_to', ari.period_to,
                                    'suppliers', ari.suppliers,
                                    'created_at', ari.created_at,
                                    'updated_at', ari.updated_at,
                                    'period_from', ari.period_from,
                                    'item_details', ari.item_details,
                                    'organization', json_build_object(
                                        'organizationName', org.data ->> 'organizationName',
                                        'email', org.data ->> 'email',
                                        'address', org.data ->> 'address',
                                        'website', org.data ->> 'website',
                                        'telephoneNumber', org.data ->> 'telephoneNumber',
                                        'organizationDescription', org.data ->> 'organizationDescription'
                                    ),
                                    'period_status', ari.period_status,
                                    'required_date', ari.required_date,
                                    'acquisition_type', json_build_object(
                                        'id', ari.acquisition_type,
                                        'name', arat.name
                                    ),
                                    'business_impact', ari.business_impact,
                                    'consumables_kpi', ari.consumables_kpi,
                                    'maintenance_kpi', ari.maintenance_kpi,
                                    'availability_type', ari.availability_type,
                                    'business_purpose', ari.business_purpose,
                                    'expected_conditions', ari.expected_conditions,
                                    'service_support_kpi', ari.service_support_kpi,
                                    'asset_requisition_id', ari.asset_requisition_id
                                )
                            ) as items,
                            ar.created_at,
                            ar.updated_at,
                            ar.requisition_by,
                            ar.requisition_id,
                            ar.requisition_date,
                            ar.requisition_status
                        %s
                        GROUP BY ar.id, u.id, u.name, u.email, ar.created_at, ar.updated_at, 
                                 ar.requisition_by, ar.requisition_id, ar.requisition_date, ar.requisition_status
                        %s
                        LIMIT %s OFFSET %s
                    ) t
                $SQL$, v_message, v_base_query, v_order_clause, p_page_size, v_offset_next)
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
        DB::unprepared(<<<SQL
        DO $$
        DECLARE
            r RECORD;
        BEGIN
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'get_asset_requisition_data_for_drawer'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;
        SQL);
    }
};