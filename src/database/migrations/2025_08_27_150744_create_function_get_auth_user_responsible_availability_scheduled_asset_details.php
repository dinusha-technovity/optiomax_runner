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
            CREATE OR REPLACE FUNCTION get_auth_user_responsible_availability_scheduled_asset_details(
                p_tenant_id BIGINT,
                p_asset_item_id BIGINT DEFAULT NULL,
                p_user_id BIGINT DEFAULT NULL
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
                -- tenant check
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE','Invalid tenant ID provided',
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,NULL;
                    RETURN;
                END IF;

                -- asset item check
                IF p_asset_item_id IS NOT NULL AND p_asset_item_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE','Invalid asset item ID provided',
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,NULL;
                    RETURN;
                END IF;

                -- user check
                IF p_user_id IS NOT NULL AND p_user_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE','Invalid user ID provided',
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,
                        NULL,NULL,NULL,NULL,NULL,NULL;
                    RETURN;
                END IF;

                RETURN QUERY
                    SELECT DISTINCT
                        'SUCCESS',
                        'Assets with schedules assigned to responsible_person retrieved successfully',
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
                    WHERE ai.responsible_person = p_user_id
                    AND (ai.id = p_asset_item_id OR p_asset_item_id IS NULL OR p_asset_item_id <= 0)
                    AND ai.tenant_id = p_tenant_id
                    AND ai.deleted_at IS NULL
                    AND ai.isactive = TRUE
                    AND a.deleted_at IS NULL
                    AND a.isactive = TRUE
                    -- ✅ Must have schedules in either table
                    AND (
                        EXISTS (
                            SELECT 1 FROM asset_availability_schedules aas
                            WHERE aas.asset_id = ai.id
                            AND aas.tenant_id = p_tenant_id
                            AND aas.deleted_at IS NULL
                            AND aas.is_active = TRUE
                        )
                        OR EXISTS (
                            SELECT 1 FROM asset_availability_blockout_schedules abs
                            WHERE abs.asset_id = ai.id
                            AND abs.tenant_id = p_tenant_id
                            AND abs.deleted_at IS NULL
                            AND abs.is_active = TRUE
                        )
                    );
            END;
            $$
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_availability_blockout_schedule_full(BIGINT, BIGINT)');
    }
};