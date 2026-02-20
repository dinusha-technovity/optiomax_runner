<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Create audit group details retrieval function
     * 
     * This function retrieves detailed information about a specific audit group
     * including all associated assets with their full details.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION get_audit_group_details(
            p_group_id BIGINT,
            p_tenant_id BIGINT
        ) RETURNS JSONB LANGUAGE plpgsql AS $$
        DECLARE
            v_group JSONB;
            v_assets JSONB;
        BEGIN
            -- Get group details
            SELECT jsonb_build_object(
                'id', ag.id,
                'name', ag.name,
                'description', ag.description,
                'isactive', ag.isactive,
                'created_at', ag.created_at,
                'updated_at', ag.updated_at
            )
            INTO v_group
            FROM audit_groups ag
            WHERE ag.id = p_group_id
                AND ag.tenant_id = p_tenant_id
                AND ag.deleted_at IS NULL;

            IF v_group IS NULL THEN
                RETURN jsonb_build_object(
                    'status', 'ERROR',
                    'message', 'Audit group not found'
                );
            END IF;

            -- Get associated assets
            SELECT jsonb_agg(
                jsonb_build_object(
                    'id', ai.id,
                    'asset_tag', ai.asset_tag,
                    'model_number', ai.model_number,
                    'serial_number', ai.serial_number,
                    'thumbnail_image', ai.thumbnail_image,
                    'qr_code', ai.qr_code,
                    'item_value', ai.item_value,
                    'asset_name', a.name,
                    'asset_category', ac.name,
                    'added_at', agra.created_at,
                    'relation_id', agra.id
                ) ORDER BY agra.created_at DESC
            )
            INTO v_assets
            FROM audit_groups_releated_assets agra
            INNER JOIN asset_items ai ON ai.id = agra.asset_id
            INNER JOIN assets a ON a.id = ai.asset_id
            LEFT JOIN asset_categories ac ON ac.id = a.category
            WHERE agra.audit_group_id = p_group_id
                AND agra.tenant_id = p_tenant_id
                AND agra.deleted_at IS NULL
                AND agra.isactive = TRUE;

            RETURN jsonb_build_object(
                'status', 'SUCCESS',
                'group', v_group,
                'assets', COALESCE(v_assets, '[]'::jsonb),
                'asset_count', jsonb_array_length(COALESCE(v_assets, '[]'::jsonb))
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
        DB::unprepared('DROP FUNCTION IF EXISTS get_audit_group_details');
    }
};
