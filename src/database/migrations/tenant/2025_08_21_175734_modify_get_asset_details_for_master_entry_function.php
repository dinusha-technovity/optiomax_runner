<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            DROP FUNCTION IF EXISTS get_asset_details_for_master_entry(BIGINT, BIGINT, BIGINT, TEXT);

            CREATE OR REPLACE FUNCTION get_asset_details_for_master_entry(
                p_tenant_id BIGINT,
                p_asset_item_id BIGINT DEFAULT NULL,
                p_user_id BIGINT DEFAULT NULL,
                p_action TEXT DEFAULT NULL -- new parameter
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                asset_id BIGINT,
                asset_name VARCHAR,
                model_number VARCHAR,
                serial_number VARCHAR,
                thumbnail_image JSONB,
                qr_code VARCHAR,
                asset_tag VARCHAR
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Tenant check
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Invalid tenant ID provided'::TEXT,
                        NULL::BIGINT, NULL::BIGINT, NULL::VARCHAR, 
                        NULL::VARCHAR, NULL::VARCHAR, NULL::JSONB, 
                        NULL::VARCHAR, NULL::VARCHAR;
                    RETURN;
                END IF;

                -- Asset item check
                IF p_asset_item_id IS NOT NULL AND p_asset_item_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Invalid asset item ID provided'::TEXT,
                        NULL::BIGINT, NULL::BIGINT, NULL::VARCHAR, 
                        NULL::VARCHAR, NULL::VARCHAR, NULL::JSONB, 
                        NULL::VARCHAR, NULL::VARCHAR;
                    RETURN;
                END IF;

                -- User check
                IF p_user_id IS NOT NULL AND p_user_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Invalid user ID provided'::TEXT,
                        NULL::BIGINT, NULL::BIGINT, NULL::VARCHAR, 
                        NULL::VARCHAR, NULL::VARCHAR, NULL::JSONB, 
                        NULL::VARCHAR, NULL::VARCHAR;
                    RETURN;
                END IF;

                -- ✅ Action-based conditions
                IF p_action = 'responsible' THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT,
                        'Assets assigned to responsible_person retrieved successfully'::TEXT,
                        ai.id, a.id, a.name,
                        ai.model_number, ai.serial_number,
                        ai.thumbnail_image, ai.qr_code, ai.asset_tag
                    FROM asset_items ai
                    INNER JOIN assets a ON ai.asset_id = a.id
                    WHERE ai.responsible_person = p_user_id
                    AND (ai.id = p_asset_item_id OR p_asset_item_id IS NULL OR p_asset_item_id <= 0)
                    AND ai.tenant_id = p_tenant_id
                    AND ai.deleted_at IS NULL
                    AND ai.isactive = TRUE
                    AND a.deleted_at IS NULL
                    AND a.isactive = TRUE;

                ELSIF p_action = 'user_related' THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT,
                        'User-related asset items retrieved successfully'::TEXT,
                        ai.id, a.id, a.name,
                        ai.model_number, ai.serial_number,
                        ai.thumbnail_image, ai.qr_code, ai.asset_tag
                    FROM maintenance_team_members mtm
                    JOIN maintenance_teams mt ON mt.id = mtm.team_id
                    JOIN maintenance_team_related_asset_groups mtag ON mtag.team_id = mt.id
                    JOIN assets a ON a.id = mtag.asset_group_id
                    JOIN asset_items ai ON ai.asset_id = a.id
                    WHERE mtm.user_id = p_user_id
                    AND mtm.tenant_id = p_tenant_id
                    AND mtm.deleted_at IS NULL AND mtm.isactive = TRUE
                    AND mt.deleted_at IS NULL AND mt.isactive = TRUE
                    AND mtag.deleted_at IS NULL AND mtag.isactive = TRUE
                    AND a.deleted_at IS NULL AND a.isactive = TRUE
                    AND ai.deleted_at IS NULL AND ai.isactive = TRUE;

                ELSE
                    -- Default case: fetch normally
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT,
                        'Asset items retrieved successfully'::TEXT,
                        ai.id, a.id, a.name,
                        ai.model_number, ai.serial_number,
                        ai.thumbnail_image, ai.qr_code, ai.asset_tag
                    FROM asset_items ai
                    INNER JOIN assets a ON ai.asset_id = a.id
                    WHERE (ai.id = p_asset_item_id OR p_asset_item_id IS NULL)
                    AND ai.tenant_id = p_tenant_id
                    AND ai.deleted_at IS NULL
                    AND ai.isactive = TRUE
                    AND a.deleted_at IS NULL
                    AND a.isactive = TRUE;
                END IF;
            END;
            $$
        SQL);
    }
 
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       DB::unprepared('DROP FUNCTION IF EXISTS get_asset_details_for_master_entry(BIGINT, BIGINT, BIGINT, TEXT);');
    }
};