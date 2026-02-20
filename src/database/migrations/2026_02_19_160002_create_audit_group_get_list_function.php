<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Create audit group list retrieval function
     * 
     * This function retrieves a paginated, searchable list of audit groups
     * with their associated asset counts using efficient LATERAL joins.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION get_audit_groups(
            p_tenant_id BIGINT,
            p_search_term VARCHAR DEFAULT NULL,
            p_page INT DEFAULT 1,
            p_per_page INT DEFAULT 15,
            p_only_active BOOLEAN DEFAULT TRUE
        ) RETURNS JSONB LANGUAGE plpgsql AS $$
        DECLARE
            v_offset INT;
            v_total_count INT;
            v_groups JSONB;
        BEGIN
            -- Calculate offset
            v_offset := (p_page - 1) * p_per_page;

            -- Get total count
            SELECT COUNT(*)
            INTO v_total_count
            FROM audit_groups
            WHERE tenant_id = p_tenant_id
                AND deleted_at IS NULL
                AND (NOT p_only_active OR isactive = TRUE)
                AND (p_search_term IS NULL OR 
                     name ILIKE '%' || p_search_term || '%' OR 
                     description ILIKE '%' || p_search_term || '%');

            -- Get paginated groups with asset count
            SELECT jsonb_agg(row_data)
            INTO v_groups
            FROM (
                SELECT jsonb_build_object(
                    'id', ag.id,
                    'name', ag.name,
                    'description', ag.description,
                    'isactive', ag.isactive,
                    'asset_count', COALESCE(asset_counts.count, 0),
                    'created_at', ag.created_at,
                    'updated_at', ag.updated_at
                ) as row_data
                FROM audit_groups ag
                LEFT JOIN LATERAL (
                    SELECT COUNT(*) as count
                    FROM audit_groups_releated_assets agra
                    WHERE agra.audit_group_id = ag.id
                        AND agra.deleted_at IS NULL
                        AND agra.isactive = TRUE
                ) asset_counts ON TRUE
                WHERE ag.tenant_id = p_tenant_id
                    AND ag.deleted_at IS NULL
                    AND (NOT p_only_active OR ag.isactive = TRUE)
                    AND (p_search_term IS NULL OR 
                         ag.name ILIKE '%' || p_search_term || '%' OR 
                         ag.description ILIKE '%' || p_search_term || '%')
                ORDER BY ag.created_at DESC
                LIMIT p_per_page
                OFFSET v_offset
            ) subquery;

            RETURN jsonb_build_object(
                'status', 'SUCCESS',
                'data', COALESCE(v_groups, '[]'::jsonb),
                'pagination', jsonb_build_object(
                    'current_page', p_page,
                    'per_page', p_per_page,
                    'total', v_total_count,
                    'last_page', CEIL(v_total_count::DECIMAL / p_per_page)
                )
            );

        EXCEPTION
            WHEN OTHERS THEN
                RETURN jsonb_build_object(
                    'status', 'ERROR',
                    'message', 'An error occurred: ' || SQLERRM
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
        DB::unprepared('DROP FUNCTION IF EXISTS get_audit_groups');
    }
};
