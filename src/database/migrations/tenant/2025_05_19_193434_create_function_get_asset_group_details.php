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
            CREATE OR REPLACE FUNCTION get_asset_group_details(
                _tenant_id BIGINT,
                p_asset_id INT DEFAULT NULL 
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                name TEXT,
                thumbnail_image JSON,
                category_id BIGINT,
                category_name TEXT,
                sub_category_id BIGINT,
                sub_category_name TEXT,
                asset_description TEXT,
                asset_details JSON,
                asset_classification JSON,
                reading_parameters JSON,
                registered_by_id BIGINT,
                registered_by_name TEXT,
                asset_type_id BIGINT,
                asset_type TEXT
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Validate tenant ID
                IF _tenant_id IS NULL OR _tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::JSON AS thumbnail_image,
                        NULL::BIGINT AS category_id,
                        NULL::TEXT AS category_name,
                        NULL::BIGINT AS sub_category_id,
                        NULL::TEXT AS sub_category_name,
                        NULL::TEXT AS asset_description,
                        NULL::JSON AS asset_details,
                        NULL::JSON AS asset_classification,
                        NULL::JSON AS reading_parameters,
                        NULL::BIGINT AS registered_by_id,
                        NULL::TEXT AS registered_by_name,
                        NULL::BIGINT AS asset_type_id,
                        NULL::TEXT AS asset_type;

                    RETURN;
                END IF;

                -- Validate asset ID (optional)
                IF p_asset_id IS NOT NULL AND p_asset_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid p_asset_id provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::JSON AS thumbnail_image,
                        NULL::BIGINT AS category_id,
                        NULL::TEXT AS category_name,
                        NULL::BIGINT AS sub_category_id,
                        NULL::TEXT AS sub_category_name,
                        NULL::TEXT AS asset_description,
                        NULL::JSON AS asset_details,
                        NULL::JSON AS asset_classification,
                        NULL::JSON AS reading_parameters,
                        NULL::BIGINT AS registered_by_id,
                        NULL::TEXT AS registered_by_name,
                        NULL::BIGINT AS asset_type_id,
                        NULL::TEXT AS asset_type;
                    RETURN;
                END IF;

                -- Return the matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Asset details retrieved successfully'::TEXT AS message,
                    a.id,
                    a.name::TEXT,
                    a.thumbnail_image::JSON,
                    a.category AS category_id,
                    ac.name::TEXT AS category_name,
                    a.sub_category AS sub_category_id,
                    assc.name::TEXT AS sub_category_name,
                    a.asset_description::TEXT,
                    a.asset_details::JSON,
                    a.asset_classification::JSON,
                    a.reading_parameters::JSON,
                    a.registered_by AS registered_by_id,
                    ur.name::TEXT AS registered_by_name,
                    ac.assets_type AS asset_type_id,  -- Getting asset_type_id from asset_categories
                    ast.name::TEXT AS asset_type      -- Getting asset_type name from assets_types
                FROM
                    assets a
                INNER JOIN
                    asset_categories ac ON a.category = ac.id
                INNER JOIN
                    asset_sub_categories assc ON a.sub_category = assc.id
                INNER JOIN
                    users ur ON a.registered_by = ur.id
                INNER JOIN
                    assets_types ast ON ac.assets_type = ast.id  -- Join with assets_types via asset_categories
                WHERE
                    (a.id = p_asset_id OR p_asset_id IS NULL OR p_asset_id = 0)
                    AND a.tenant_id = _tenant_id
                    AND a.deleted_at IS NULL
                    AND a.isactive = TRUE
                GROUP BY
                    a.id, ac.id, assc.id, ur.id, ast.id;

            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_group_details');
    }
};
