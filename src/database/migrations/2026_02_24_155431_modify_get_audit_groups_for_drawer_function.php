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
        DB::unprepared(<<<SQL
            DO $$
            DECLARE
                r RECORD;
            BEGIN 
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_audit_groups_for_drawer'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION get_audit_groups_for_drawer(
                p_tenant_id INT,
                p_group_id INT DEFAULT NULL,
                p_action TEXT DEFAULT NULL,
                p_page_no INT DEFAULT 1,
                p_page_size INT DEFAULT 10,
                p_search TEXT DEFAULT NULL,
                p_prefetch_mode TEXT DEFAULT 'both',
                p_sort_by TEXT DEFAULT 'newest'
            )
            RETURNS JSONB
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_offset INT;
                v_total_count INT;
                v_total_pages INT;
                v_result JSONB;
                v_data JSONB;
                v_previous_data JSONB DEFAULT NULL;
                v_next_data JSONB DEFAULT NULL;
            BEGIN
                -- Calculate offset
                v_offset := (p_page_no - 1) * p_page_size;

                -- Get total count first
                WITH filtered_groups AS (
                    SELECT
                        ag.id,
                        ag.name as group_name,
                        ag.description,
                        ag.created_at,
                        ag.updated_at,
                        COUNT(DISTINCT agra.asset_id) as assigned_asset_count,
                        COUNT(DISTINCT asga.id) as assigned_staff_count
                    FROM audit_groups ag
                    LEFT JOIN audit_groups_releated_assets agra 
                        ON agra.audit_group_id = ag.id 
                        AND agra.deleted_at IS NULL 
                        AND agra.isactive = true
                    LEFT JOIN audit_staff_group_assignments asga
                        ON asga.audit_group_id = ag.id
                        AND asga.deleted_at IS NULL
                        AND asga.isactive = true
                    WHERE ag.tenant_id = p_tenant_id
                        AND ag.deleted_at IS NULL
                        AND ag.isactive = true
                        AND (p_group_id IS NULL OR ag.id = p_group_id)
                        AND (
                            p_search IS NULL 
                            OR ag.name ILIKE '%' || p_search || '%'
                            OR ag.description ILIKE '%' || p_search || '%'
                        )
                        AND (
                            p_action IS NULL 
                            OR (p_action = 'with_staff' AND EXISTS (
                                SELECT 1 FROM audit_staff_group_assignments asga2
                                WHERE asga2.audit_group_id = ag.id
                                    AND asga2.deleted_at IS NULL
                                    AND asga2.isactive = true
                            ))
                        )
                    GROUP BY ag.id, ag.name, ag.description, ag.created_at, ag.updated_at
                ),
                sorted_groups AS (
                    SELECT 
                        id,
                        group_name,
                        description,
                        assigned_asset_count,
                        assigned_staff_count,
                        created_at,
                        updated_at,
                        ROW_NUMBER() OVER (
                            ORDER BY
                                CASE WHEN p_sort_by = 'newest' THEN created_at END DESC,
                                CASE WHEN p_sort_by = 'oldest' THEN created_at END ASC,
                                CASE WHEN p_sort_by = 'name_asc' THEN group_name END ASC,
                                CASE WHEN p_sort_by = 'name_desc' THEN group_name END DESC,
                                CASE WHEN p_sort_by = 'most_assets' THEN assigned_asset_count END DESC,
                                CASE WHEN p_sort_by = 'least_assets' THEN assigned_asset_count END ASC,
                                CASE WHEN p_sort_by = 'most_staff' THEN assigned_staff_count END DESC,
                                CASE WHEN p_sort_by = 'least_staff' THEN assigned_staff_count END ASC,
                                id DESC
                        ) as row_num
                    FROM filtered_groups
                ),
                total_count AS (
                    SELECT COUNT(*) as total FROM sorted_groups
                )
                SELECT total INTO v_total_count FROM total_count;

                -- Calculate total pages
                v_total_pages := CEIL(v_total_count::NUMERIC / p_page_size);

                -- Get current page data
                WITH base_query AS (
                    SELECT
                        ag.id,
                        ag.name as group_name,
                        ag.description,
                        ag.created_at,
                        ag.updated_at,
                        COUNT(DISTINCT agra.asset_id) as assigned_asset_count,
                        COUNT(DISTINCT asga.id) as assigned_staff_count
                    FROM audit_groups ag
                    LEFT JOIN audit_groups_releated_assets agra 
                        ON agra.audit_group_id = ag.id 
                        AND agra.deleted_at IS NULL 
                        AND agra.isactive = true
                    LEFT JOIN audit_staff_group_assignments asga
                        ON asga.audit_group_id = ag.id
                        AND asga.deleted_at IS NULL
                        AND asga.isactive = true
                    WHERE ag.tenant_id = p_tenant_id
                        AND ag.deleted_at IS NULL
                        AND ag.isactive = true
                        AND (p_group_id IS NULL OR ag.id = p_group_id)
                        AND (
                            p_search IS NULL 
                            OR ag.name ILIKE '%' || p_search || '%'
                            OR ag.description ILIKE '%' || p_search || '%'
                        )
                        AND (
                            p_action IS NULL 
                            OR (p_action = 'with_staff' AND EXISTS (
                                SELECT 1 FROM audit_staff_group_assignments asga2
                                WHERE asga2.audit_group_id = ag.id
                                    AND asga2.deleted_at IS NULL
                                    AND asga2.isactive = true
                            ))
                        )
                    GROUP BY ag.id, ag.name, ag.description, ag.created_at, ag.updated_at
                ),
                sorted_data AS (
                    SELECT 
                        id,
                        group_name,
                        description,
                        assigned_asset_count,
                        assigned_staff_count,
                        created_at,
                        updated_at,
                        ROW_NUMBER() OVER (
                            ORDER BY
                                CASE WHEN p_sort_by = 'newest' THEN created_at END DESC,
                                CASE WHEN p_sort_by = 'oldest' THEN created_at END ASC,
                                CASE WHEN p_sort_by = 'name_asc' THEN group_name END ASC,
                                CASE WHEN p_sort_by = 'name_desc' THEN group_name END DESC,
                                CASE WHEN p_sort_by = 'most_assets' THEN assigned_asset_count END DESC,
                                CASE WHEN p_sort_by = 'least_assets' THEN assigned_asset_count END ASC,
                                CASE WHEN p_sort_by = 'most_staff' THEN assigned_staff_count END DESC,
                                CASE WHEN p_sort_by = 'least_staff' THEN assigned_staff_count END ASC,
                                id DESC
                        ) as row_num
                    FROM base_query
                )
                SELECT COALESCE(
                    jsonb_agg(
                        jsonb_build_object(
                            'id', id,
                            'group_name', group_name,
                            'description', description,
                            'assigned_asset_count', assigned_asset_count,
                            'assigned_staff_count', assigned_staff_count,
                            'created_at', created_at,
                            'updated_at', updated_at
                        )
                    ), '[]'::jsonb
                )
                INTO v_data
                FROM sorted_data
                WHERE row_num > v_offset
                    AND row_num <= v_offset + p_page_size;

                -- Prefetch previous page if requested
                IF p_prefetch_mode IN ('both', 'previous') AND p_page_no > 1 THEN
                    WITH base_query AS (
                        SELECT
                            ag.id,
                            ag.name as group_name,
                            ag.description,
                            ag.created_at,
                            ag.updated_at,
                            COUNT(DISTINCT agra.asset_id) as assigned_asset_count,
                            COUNT(DISTINCT asga.id) as assigned_staff_count
                        FROM audit_groups ag
                        LEFT JOIN audit_groups_releated_assets agra 
                            ON agra.audit_group_id = ag.id 
                            AND agra.deleted_at IS NULL 
                            AND agra.isactive = true
                        LEFT JOIN audit_staff_group_assignments asga
                            ON asga.audit_group_id = ag.id
                            AND asga.deleted_at IS NULL
                            AND asga.isactive = true
                        WHERE ag.tenant_id = p_tenant_id
                            AND ag.deleted_at IS NULL
                            AND ag.isactive = true
                            AND (p_group_id IS NULL OR ag.id = p_group_id)
                            AND (
                                p_search IS NULL 
                                OR ag.name ILIKE '%' || p_search || '%'
                                OR ag.description ILIKE '%' || p_search || '%'
                            )
                            AND (
                                p_action IS NULL 
                                OR (p_action = 'with_staff' AND EXISTS (
                                    SELECT 1 FROM audit_staff_group_assignments asga2
                                    WHERE asga2.audit_group_id = ag.id
                                        AND asga2.deleted_at IS NULL
                                        AND asga2.isactive = true
                                ))
                            )
                        GROUP BY ag.id, ag.name, ag.description, ag.created_at, ag.updated_at
                    ),
                    sorted_data AS (
                        SELECT 
                            id,
                            group_name,
                            description,
                            assigned_asset_count,
                            assigned_staff_count,
                            created_at,
                            updated_at,
                            ROW_NUMBER() OVER (
                                ORDER BY
                                    CASE WHEN p_sort_by = 'newest' THEN created_at END DESC,
                                    CASE WHEN p_sort_by = 'oldest' THEN created_at END ASC,
                                    CASE WHEN p_sort_by = 'name_asc' THEN group_name END ASC,
                                    CASE WHEN p_sort_by = 'name_desc' THEN group_name END DESC,
                                    CASE WHEN p_sort_by = 'most_assets' THEN assigned_asset_count END DESC,
                                    CASE WHEN p_sort_by = 'least_assets' THEN assigned_asset_count END ASC,
                                    CASE WHEN p_sort_by = 'most_staff' THEN assigned_staff_count END DESC,
                                    CASE WHEN p_sort_by = 'least_staff' THEN assigned_staff_count END ASC,
                                    id DESC
                            ) as row_num
                        FROM base_query
                    )
                    SELECT COALESCE(
                        jsonb_agg(
                            jsonb_build_object(
                                'id', id,
                                'group_name', group_name,
                                'description', description,
                                'assigned_asset_count', assigned_asset_count,
                                'assigned_staff_count', assigned_staff_count,
                                'created_at', created_at,
                                'updated_at', updated_at
                            )
                        ), '[]'::jsonb
                    )
                    INTO v_previous_data
                    FROM sorted_data
                    WHERE row_num > (v_offset - p_page_size)
                        AND row_num <= v_offset;
                END IF;

                -- Prefetch next page if requested
                IF p_prefetch_mode IN ('both', 'next') AND p_page_no < v_total_pages THEN
                    WITH base_query AS (
                        SELECT
                            ag.id,
                            ag.name as group_name,
                            ag.description,
                            ag.created_at,
                            ag.updated_at,
                            COUNT(DISTINCT agra.asset_id) as assigned_asset_count,
                            COUNT(DISTINCT asga.id) as assigned_staff_count
                        FROM audit_groups ag
                        LEFT JOIN audit_groups_releated_assets agra 
                            ON agra.audit_group_id = ag.id 
                            AND agra.deleted_at IS NULL 
                            AND agra.isactive = true
                        LEFT JOIN audit_staff_group_assignments asga
                            ON asga.audit_group_id = ag.id
                            AND asga.deleted_at IS NULL
                            AND asga.isactive = true
                        WHERE ag.tenant_id = p_tenant_id
                            AND ag.deleted_at IS NULL
                            AND ag.isactive = true
                            AND (p_group_id IS NULL OR ag.id = p_group_id)
                            AND (
                                p_search IS NULL 
                                OR ag.name ILIKE '%' || p_search || '%'
                                OR ag.description ILIKE '%' || p_search || '%'
                            )
                            AND (
                                p_action IS NULL 
                                OR (p_action = 'with_staff' AND EXISTS (
                                    SELECT 1 FROM audit_staff_group_assignments asga2
                                    WHERE asga2.audit_group_id = ag.id
                                        AND asga2.deleted_at IS NULL
                                        AND asga2.isactive = true
                                ))
                            )
                        GROUP BY ag.id, ag.name, ag.description, ag.created_at, ag.updated_at
                    ),
                    sorted_data AS (
                        SELECT 
                            id,
                            group_name,
                            description,
                            assigned_asset_count,
                            assigned_staff_count,
                            created_at,
                            updated_at,
                            ROW_NUMBER() OVER (
                                ORDER BY
                                    CASE WHEN p_sort_by = 'newest' THEN created_at END DESC,
                                    CASE WHEN p_sort_by = 'oldest' THEN created_at END ASC,
                                    CASE WHEN p_sort_by = 'name_asc' THEN group_name END ASC,
                                    CASE WHEN p_sort_by = 'name_desc' THEN group_name END DESC,
                                    CASE WHEN p_sort_by = 'most_assets' THEN assigned_asset_count END DESC,
                                    CASE WHEN p_sort_by = 'least_assets' THEN assigned_asset_count END ASC,
                                    CASE WHEN p_sort_by = 'most_staff' THEN assigned_staff_count END DESC,
                                    CASE WHEN p_sort_by = 'least_staff' THEN assigned_staff_count END ASC,
                                    id DESC
                            ) as row_num
                        FROM base_query
                    )
                    SELECT COALESCE(
                        jsonb_agg(
                            jsonb_build_object(
                                'id', id,
                                'group_name', group_name,
                                'description', description,
                                'assigned_asset_count', assigned_asset_count,
                                'assigned_staff_count', assigned_staff_count,
                                'created_at', created_at,
                                'updated_at', updated_at
                            )
                        ), '[]'::jsonb
                    )
                    INTO v_next_data
                    FROM sorted_data
                    WHERE row_num > (v_offset + p_page_size)
                        AND row_num <= (v_offset + (2 * p_page_size));
                END IF;

                -- Build final result
                v_result := jsonb_build_object(
                    'success', true,
                    'data', jsonb_build_object(
                        'current', v_data,
                        'previous', COALESCE(v_previous_data, '[]'::jsonb),
                        'next', COALESCE(v_next_data, '[]'::jsonb)
                    ),
                    'pagination', jsonb_build_object(
                        'current_page', p_page_no,
                        'page_size', p_page_size,
                        'total_records', v_total_count,
                        'total_pages', v_total_pages,
                        'has_previous', p_page_no > 1,
                        'has_next', p_page_no < v_total_pages
                    )
                );

                RETURN v_result;

            EXCEPTION
                WHEN OTHERS THEN
                    RETURN jsonb_build_object(
                        'success', false,
                        'message', SQLERRM,
                        'data', jsonb_build_object(
                            'current', '[]'::jsonb,
                            'previous', '[]'::jsonb,
                            'next', '[]'::jsonb
                        ),
                        'pagination', jsonb_build_object(
                            'current_page', 1,
                            'page_size', p_page_size,
                            'total_records', 0,
                            'total_pages', 0,
                            'has_previous', false,
                            'has_next', false
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
        DB::unprepared("DROP FUNCTION IF EXISTS get_audit_groups_for_drawer(INT, INT, TEXT, INT, INT, TEXT, TEXT, TEXT);");
    }
};