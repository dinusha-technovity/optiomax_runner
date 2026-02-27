<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Modify get_audit_groups function to include first 10 assets for each audit group
     * ISO 19011:2018 Compliant - Enhanced audit group asset visibility
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        DROP FUNCTION IF EXISTS get_audit_groups CASCADE;

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

            -- Get paginated groups with asset count and first 10 assets
            SELECT jsonb_agg(row_data)
            INTO v_groups
            FROM (
                SELECT jsonb_build_object(
                    'id', ag.id,
                    'name', ag.name,
                    'description', ag.description,
                    'isactive', ag.isactive,
                    'asset_count', COALESCE(asset_counts.count, 0),
                    'assets', COALESCE(asset_list.assets, '[]'::jsonb),
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
                LEFT JOIN LATERAL (
                    SELECT jsonb_agg(
                        jsonb_build_object(
                            'id', sub.id,
                            'asset_tag', sub.asset_tag,
                            'model_number', sub.model_number,
                            'serial_number', sub.serial_number,
                            'asset_name', sub.asset_name,
                            'category_id', sub.category_id,
                            'category_name', sub.category_name,
                            'sub_category_id', sub.sub_category_id,
                            'sub_category_name', sub.sub_category_name,
                            'item_value', sub.item_value,
                            'thumbnail_image', sub.thumbnail_image,
                            'qr_code', sub.qr_code,
                            'assigned_at', sub.assigned_at
                        )
                    ) as assets
                    FROM (
                        SELECT 
                            ai.id,
                            ai.asset_tag,
                            ai.model_number,
                            ai.serial_number,
                            a.name as asset_name,
                            a.category as category_id,
                            ac.name as category_name,
                            a.sub_category as sub_category_id,
                            asub.name as sub_category_name,
                            ai.item_value,
                            CASE 
                                WHEN jsonb_typeof(ai.thumbnail_image) = 'array' THEN ai.thumbnail_image
                                ELSE '[]'::jsonb
                            END as thumbnail_image,
                            CASE 
                                WHEN jsonb_typeof(ai.qr_code) = 'array' THEN ai.qr_code
                                ELSE '[]'::jsonb
                            END as qr_code,
                            agra.created_at as assigned_at
                        FROM audit_groups_releated_assets agra
                        INNER JOIN asset_items ai ON ai.id = agra.asset_id
                        INNER JOIN assets a ON a.id = ai.asset_id
                        LEFT JOIN asset_categories ac ON ac.id = a.category
                        LEFT JOIN asset_sub_categories asub ON asub.id = a.sub_category
                        WHERE agra.audit_group_id = ag.id
                            AND agra.deleted_at IS NULL
                            AND agra.isactive = TRUE
                            AND ai.deleted_at IS NULL
                            AND ai.isactive = TRUE
                            AND a.deleted_at IS NULL
                            AND a.isactive = TRUE
                        ORDER BY agra.created_at DESC
                        LIMIT 10
                    ) sub
                ) asset_list ON TRUE
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
        DB::unprepared(<<<'SQL'
        DROP FUNCTION IF EXISTS get_audit_groups CASCADE;

        -- Restore original function without assets
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
            v_offset := (p_page - 1) * p_per_page;

            SELECT COUNT(*)
            INTO v_total_count
            FROM audit_groups
            WHERE tenant_id = p_tenant_id
                AND deleted_at IS NULL
                AND (NOT p_only_active OR isactive = TRUE)
                AND (p_search_term IS NULL OR 
                     name ILIKE '%' || p_search_term || '%' OR 
                     description ILIKE '%' || p_search_term || '%');

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
};
