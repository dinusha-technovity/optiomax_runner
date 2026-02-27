<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates PostgreSQL function for audit group assets drawer with pagination
     * Returns paginated assets assigned to a specific audit group
     * ISO 19011:2018 Compliant
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- Drop existing function if exists
        DROP FUNCTION IF EXISTS get_audit_group_assets_for_drawer CASCADE;

        CREATE OR REPLACE FUNCTION get_audit_group_assets_for_drawer(
            p_tenant_id BIGINT,
            p_audit_group_id BIGINT,
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
            v_order_clause TEXT := 'ORDER BY ai.id DESC';
            v_message TEXT := '';
            v_base_query TEXT := '';
            v_search_clause TEXT := '';
            v_group_name TEXT := '';
        BEGIN
            ----------------------------------------------------------------
            -- Validations
            ----------------------------------------------------------------
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN json_build_object(
                    'success', false,
                    'message', 'Invalid tenant_id provided',
                    'data', json_build_object(
                        'previous', '[]'::JSON,
                        'current', '[]'::JSON,
                        'next', '[]'::JSON
                    ),
                    'pagination', json_build_object(
                        'current_page', 0,
                        'total_pages', 0,
                        'total_records', 0,
                        'page_size', p_page_size,
                        'has_previous', false,
                        'has_next', false
                    )
                );
            END IF;

            IF p_audit_group_id IS NULL OR p_audit_group_id <= 0 THEN
                RETURN json_build_object(
                    'success', false,
                    'message', 'Invalid audit_group_id provided',
                    'data', json_build_object(
                        'previous', '[]'::JSON,
                        'current', '[]'::JSON,
                        'next', '[]'::JSON
                    ),
                    'pagination', json_build_object(
                        'current_page', 0,
                        'total_pages', 0,
                        'total_records', 0,
                        'page_size', p_page_size,
                        'has_previous', false,
                        'has_next', false
                    )
                );
            END IF;

            -- Verify audit group exists and is active
            SELECT ag.name INTO v_group_name
            FROM audit_groups ag
            WHERE ag.id = p_audit_group_id
                AND ag.tenant_id = p_tenant_id
                AND ag.deleted_at IS NULL
                AND ag.isactive = TRUE;

            IF v_group_name IS NULL THEN
                RETURN json_build_object(
                    'success', false,
                    'message', 'Audit group not found or inactive',
                    'data', json_build_object(
                        'previous', '[]'::JSON,
                        'current', '[]'::JSON,
                        'next', '[]'::JSON
                    ),
                    'pagination', json_build_object(
                        'current_page', 0,
                        'total_pages', 0,
                        'total_records', 0,
                        'page_size', p_page_size,
                        'has_previous', false,
                        'has_next', false
                    )
                );
            END IF;

            ----------------------------------------------------------------
            -- Sorting Logic
            ----------------------------------------------------------------
            CASE LOWER(TRIM(p_sort_by))
                WHEN 'newest' THEN v_order_clause := 'ORDER BY ai.created_at DESC NULLS LAST';
                WHEN 'oldest' THEN v_order_clause := 'ORDER BY ai.created_at ASC NULLS LAST';
                WHEN 'name_asc' THEN v_order_clause := 'ORDER BY LOWER(a.name) ASC NULLS LAST';
                WHEN 'name_desc' THEN v_order_clause := 'ORDER BY LOWER(a.name) DESC NULLS LAST';
                WHEN 'code_asc' THEN v_order_clause := 'ORDER BY LOWER(ai.asset_tag) ASC NULLS LAST';
                WHEN 'code_desc' THEN v_order_clause := 'ORDER BY LOWER(ai.asset_tag) DESC NULLS LAST';
                ELSE v_order_clause := 'ORDER BY ai.created_at DESC NULLS LAST';
            END CASE;

            p_page_no := GREATEST(p_page_no, 1);
            p_page_size := GREATEST(p_page_size, 1);

            ----------------------------------------------------------------
            -- Search clause
            ----------------------------------------------------------------
            IF p_search IS NOT NULL AND LENGTH(TRIM(p_search)) > 0 THEN
                v_search_clause := format(
                    'AND (
                        LOWER(a.name) LIKE LOWER(''%%%s%%'') OR
                        LOWER(ai.model_number) LIKE LOWER(''%%%s%%'') OR
                        LOWER(ai.serial_number) LIKE LOWER(''%%%s%%'') OR
                        LOWER(ai.asset_tag) LIKE LOWER(''%%%s%%'')
                    )',
                    p_search, p_search, p_search, p_search
                );
            END IF;

            -- Build base query for assets in this audit group
            v_base_query := format($b$
                FROM asset_items ai
                INNER JOIN audit_groups_releated_assets agaa 
                    ON agaa.asset_id = ai.id 
                    AND agaa.audit_group_id = %s
                    AND agaa.tenant_id = %s
                    AND agaa.deleted_at IS NULL
                    AND agaa.isactive = TRUE
                INNER JOIN assets a 
                    ON ai.asset_id = a.id
                    AND a.deleted_at IS NULL
                    AND a.isactive = TRUE
                INNER JOIN asset_categories ac 
                    ON a.category = ac.id 
                    AND ac.deleted_at IS NULL 
                    AND ac.isactive = TRUE
                INNER JOIN asset_sub_categories asub 
                    ON a.sub_category = asub.id 
                    AND asub.deleted_at IS NULL 
                    AND asub.isactive = TRUE
                WHERE ai.tenant_id = %s
                    AND ai.deleted_at IS NULL
                    AND ai.isactive = TRUE
                    %s
            $b$,
                p_audit_group_id,
                p_tenant_id,
                p_tenant_id,
                v_search_clause
            );

            ----------------------------------------------------------------
            -- Count total records
            ----------------------------------------------------------------
            EXECUTE format($s$
                SELECT COUNT(DISTINCT ai.id)
                %s
            $s$, v_base_query)
            INTO v_total_records;

            IF v_total_records = 0 THEN
                RETURN json_build_object(
                    'success', true,
                    'message', format('No assets found for audit group: %s', v_group_name),
                    'data', json_build_object(
                        'previous', '[]'::JSON,
                        'current', '[]'::JSON,
                        'next', '[]'::JSON
                    ),
                    'pagination', json_build_object(
                        'current_page', 0,
                        'total_pages', 0,
                        'total_records', 0,
                        'page_size', p_page_size,
                        'has_previous', false,
                        'has_next', false,
                        'group_id', p_audit_group_id,
                        'group_name', v_group_name
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

            v_message := format('Assets for audit group "%s" retrieved successfully', v_group_name);

            ----------------------------------------------------------------
            -- Current page data
            ----------------------------------------------------------------
            EXECUTE format($s$
                SELECT COALESCE(json_agg(t.*), '[]'::JSON)
                FROM (
                    SELECT
                        ai.id as asset_id,
                        ai.asset_tag,
                        ai.model_number,
                        ai.serial_number,
                        a.name as asset_name,
                        a.category as category_id,
                        ac.name as category_name,
                        a.sub_category as sub_category_id,
                        asub.name as sub_category_name,
                        ai.purchase_cost,
                        ai.item_value,
                        CASE 
                            WHEN jsonb_typeof(ai.thumbnail_image) = 'array' THEN ai.thumbnail_image
                            ELSE '[]'::jsonb
                        END as thumbnail_image,
                        ai.qr_code,
                        agaa.created_at as assigned_to_group_at,
                        ai.created_at,
                        ai.updated_at
                    %s
                    %s
                    LIMIT %s OFFSET %s
                ) t
            $s$, v_base_query, v_order_clause, p_page_size, v_offset_curr)
            INTO v_data_curr;

            ----------------------------------------------------------------
            -- Previous page (if prefetch)
            ----------------------------------------------------------------
            IF p_prefetch_mode = 'both' AND p_page_no > 1 THEN
                EXECUTE format($s$
                    SELECT COALESCE(json_agg(t.*), '[]'::JSON)
                    FROM (
                        SELECT
                            ai.id as asset_id,
                            ai.asset_tag,
                            ai.model_number,
                            ai.serial_number,
                            a.name as asset_name,
                            a.category as category_id,
                            ac.name as category_name,
                            a.sub_category as sub_category_id,
                            asub.name as sub_category_name,
                            ai.purchase_cost,
                            ai.item_value,
                            CASE 
                                WHEN jsonb_typeof(ai.thumbnail_image) = 'array' THEN ai.thumbnail_image
                                ELSE '[]'::jsonb
                            END as thumbnail_image,
                            ai.qr_code,
                            agaa.created_at as assigned_to_group_at,
                            ai.created_at,
                            ai.updated_at
                        %s
                        %s
                        LIMIT %s OFFSET %s
                    ) t
                $s$, v_base_query, v_order_clause, p_page_size, v_offset_prev)
                INTO v_data_prev;
            END IF;

            ----------------------------------------------------------------
            -- Next page (if prefetch)
            ----------------------------------------------------------------
            IF (p_prefetch_mode = 'both' OR p_prefetch_mode = 'after') AND p_page_no < v_total_pages THEN
                EXECUTE format($s$
                    SELECT COALESCE(json_agg(t.*), '[]'::JSON)
                    FROM (
                        SELECT
                            ai.id as asset_id,
                            ai.asset_tag,
                            ai.model_number,
                            ai.serial_number,
                            a.name as asset_name,
                            a.category as category_id,
                            ac.name as category_name,
                            a.sub_category as sub_category_id,
                            asub.name as sub_category_name,
                            ai.purchase_cost,
                            ai.item_value,
                            CASE 
                                WHEN jsonb_typeof(ai.thumbnail_image) = 'array' THEN ai.thumbnail_image
                                ELSE '[]'::jsonb
                            END as thumbnail_image,
                            ai.qr_code,
                            agaa.created_at as assigned_to_group_at,
                            ai.created_at,
                            ai.updated_at
                        %s
                        %s
                        LIMIT %s OFFSET %s
                    ) t
                $s$, v_base_query, v_order_clause, p_page_size, v_offset_next)
                INTO v_data_next;
            END IF;

            ----------------------------------------------------------------
            -- Return final result
            ----------------------------------------------------------------
            RETURN json_build_object(
                'success', true,
                'message', v_message,
                'data', json_build_object(
                    'previous', v_data_prev,
                    'current', v_data_curr,
                    'next', v_data_next
                ),
                'pagination', json_build_object(
                    'current_page', p_page_no,
                    'total_pages', v_total_pages,
                    'total_records', v_total_records,
                    'page_size', p_page_size,
                    'has_previous', p_page_no > 1,
                    'has_next', p_page_no < v_total_pages,
                    'group_id', p_audit_group_id,
                    'group_name', v_group_name
                )
            );

        END;
        $$;

        -- Add helpful comment
        COMMENT ON FUNCTION get_audit_group_assets_for_drawer IS 
        'Returns paginated list of assets assigned to a specific audit group with prefetch support. ISO 19011:2018 compliant.';
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_audit_group_assets_for_drawer CASCADE;');
    }
};