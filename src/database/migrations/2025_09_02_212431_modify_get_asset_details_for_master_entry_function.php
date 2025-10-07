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
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_asset_details_for_master_entry'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION get_asset_details_for_master_entry(
                p_tenant_id BIGINT,
                p_asset_item_id BIGINT DEFAULT NULL,
                p_user_id BIGINT DEFAULT NULL,
                p_action TEXT DEFAULT NULL
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
                asset_tag VARCHAR,
                assets_type_id BIGINT,
                assets_type_name VARCHAR,
                category_id BIGINT,
                category_name VARCHAR,
                sub_category_id BIGINT,
                sub_category_name VARCHAR
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Tenant check
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE','Invalid tenant ID provided',
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,NULL;
                    RETURN;
                END IF;

                -- Asset item check
                IF p_asset_item_id IS NOT NULL AND p_asset_item_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE','Invalid asset item ID provided',
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,NULL;
                    RETURN;
                END IF;

                -- User check
                IF p_user_id IS NOT NULL AND p_user_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE','Invalid user ID provided',
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,NULL;
                    RETURN;
                END IF;

                -- Action-based conditions
                IF p_action = 'responsible' THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS',
                        'Assets assigned to responsible_person retrieved successfully',
                        ai.id, a.id, a.name,
                        ai.model_number, ai.serial_number,
                        ai.thumbnail_image, ai.qr_code, ai.asset_tag,
                        ac.assets_type AS assets_type_id,
                        ast.name AS assets_type_name,
                        a.category AS category_id,
                        ac.name AS category_name,
                        a.sub_category AS sub_category_id,
                        assc.name AS sub_category_name
                    FROM asset_items ai
                    INNER JOIN assets a ON ai.asset_id = a.id
                    INNER JOIN asset_categories ac ON a.category = ac.id
                    INNER JOIN assets_types ast ON ac.assets_type = ast.id
                    INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
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
                        'SUCCESS',
                        'User-related asset items retrieved successfully',
                        ai.id, a.id, a.name,
                        ai.model_number, ai.serial_number,
                        ai.thumbnail_image, ai.qr_code, ai.asset_tag,
                        ac.assets_type AS assets_type_id,
                        ast.name AS assets_type_name,
                        a.category AS category_id,
                        ac.name AS category_name,
                        a.sub_category AS sub_category_id,
                        assc.name AS sub_category_name
                    FROM maintenance_team_members mtm
                    JOIN maintenance_teams mt ON mt.id = mtm.team_id
                    JOIN maintenance_team_related_asset_groups mtag ON mtag.team_id = mt.id
                    JOIN assets a ON a.id = mtag.asset_group_id
                    JOIN asset_items ai ON ai.asset_id = a.id
                    INNER JOIN asset_categories ac ON a.category = ac.id
                    INNER JOIN assets_types ast ON ac.assets_type = ast.id
                    INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
                    WHERE mtm.user_id = p_user_id
                    AND mtm.tenant_id = p_tenant_id
                    AND mtm.deleted_at IS NULL AND mtm.isactive = TRUE
                    AND mt.deleted_at IS NULL AND mt.isactive = TRUE
                    AND mtag.deleted_at IS NULL AND mtag.isactive = TRUE
                    AND a.deleted_at IS NULL AND a.isactive = TRUE
                    AND ai.deleted_at IS NULL AND ai.isactive = TRUE;

                ELSIF p_action = 'availability' THEN
                    RETURN QUERY
                    SELECT DISTINCT
                        'SUCCESS',
                        'Assets with published schedules and correct visibility assigned to responsible_person retrieved successfully',
                        ai.id, 
                        a.id AS asset_id, 
                        a.name AS asset_name,
                        ai.model_number, 
                        ai.serial_number,
                        ai.thumbnail_image, 
                        ai.qr_code, 
                        ai.asset_tag,
                        ac.assets_type AS assets_type_id,
                        ast.name AS assets_type_name,
                        a.category AS category_id,
                        ac.name AS category_name,
                        a.sub_category AS sub_category_id,
                        assc.name AS sub_category_name
                    FROM asset_items ai
                    INNER JOIN assets a ON ai.asset_id = a.id
                    INNER JOIN asset_categories ac ON a.category = ac.id
                    INNER JOIN assets_types ast ON ac.assets_type = ast.id
                    INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
                    WHERE (ai.id = p_asset_item_id OR p_asset_item_id IS NULL OR p_asset_item_id <= 0)
                    AND ai.tenant_id = p_tenant_id
                    AND ai.deleted_at IS NULL
                    AND ai.isactive = TRUE
                    AND a.deleted_at IS NULL
                    AND a.isactive = TRUE
                    AND EXISTS (
                        SELECT 1 FROM asset_availability_schedules aas
                        WHERE aas.asset_id = ai.id
                        AND aas.tenant_id = p_tenant_id
                        AND aas.deleted_at IS NULL
                        AND aas.is_active = TRUE
                        AND aas.publish_status = 'PUBLISHED'
                        AND aas.visibility_id IN (1, 3)
                    );

                ELSE
                    -- Default case: fetch normally
                    RETURN QUERY
                    SELECT
                        'SUCCESS',
                        'Asset items retrieved successfully',
                        ai.id, a.id, a.name,
                        ai.model_number, ai.serial_number,
                        ai.thumbnail_image, ai.qr_code, ai.asset_tag,
                        ac.assets_type AS assets_type_id,
                        ast.name AS assets_type_name,
                        a.category AS category_id,
                        ac.name AS category_name,
                        a.sub_category AS sub_category_id,
                        assc.name AS sub_category_name
                    FROM asset_items ai
                    INNER JOIN assets a ON ai.asset_id = a.id
                    INNER JOIN asset_categories ac ON a.category = ac.id
                    INNER JOIN assets_types ast ON ac.assets_type = ast.id
                    INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
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