<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // DB::unprepared( 
        //     "CREATE OR REPLACE PROCEDURE store_procedure_retrieve_asset_details( 
        //         IN _tenant_id BIGINT,
        //         IN p_asset_id INT DEFAULT NULL 
        //     ) 
        //     AS $$
        //     BEGIN
        //         DROP TABLE IF EXISTS asset_details_from_store_procedure;
            
        //         IF p_asset_id IS NOT NULL AND p_asset_id <= 0 THEN
        //             RAISE EXCEPTION 'Invalid p_asset_id: %', p_asset_id;
        //         END IF;
            
        //         CREATE TEMP TABLE asset_details_from_store_procedure AS
        //         SELECT
        //             a.id,
        //             a.name,
        //             a.thumbnail_image,
        //             a.assets_type as assets_type_id,
        //             ast.name as assets_type_name,
        //             a.category as category_id,
        //             ac.name as category_name,
        //             a.sub_category as sub_category_id,
        //             assc.name as sub_category_name,
        //             a.asset_description,
        //             a.asset_details,
        //             a.asset_classification,
        //             a.registered_by as registered_by_id,
        //             ur.name as registered_by_name
        //         FROM
        //             assets a
        //         INNER JOIN
        //             assets_types ast ON a.assets_type = ast.id
        //         INNER JOIN
        //             asset_categories ac ON a.category = ac.id
        //         INNER JOIN
        //             asset_sub_categories assc ON a.sub_category = assc.id
        //         INNER JOIN
        //             users ur ON a.registered_by = ur.id
        //         WHERE
        //             (a.id = p_asset_id OR p_asset_id IS NULL OR p_asset_id = 0)
        //             AND a.tenant_id = _tenant_id
        //             AND a.deleted_at IS NULL
        //             AND a.isactive = TRUE
        //         GROUP BY
        //         a.id, ast.id, ac.id, assc.id, ur.id;
        //     END; 
        //     $$ LANGUAGE plpgsql;"
        // );
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_asset_details(
                _tenant_id BIGINT,
                p_asset_id INT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                name TEXT,
                thumbnail_image JSON, -- Corrected type to JSON
                assets_type_id BIGINT,
                assets_type_name TEXT,
                category_id BIGINT,
                category_name TEXT,
                sub_category_id BIGINT,
                sub_category_name TEXT,
                asset_description TEXT,
                asset_details JSON,
                asset_classification JSON,
                reading_parameters JSON,
                registered_by_id BIGINT,
                registered_by_name TEXT
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
                        NULL::BIGINT AS assets_type_id,
                        NULL::TEXT AS assets_type_name,
                        NULL::BIGINT AS category_id,
                        NULL::TEXT AS category_name,
                        NULL::BIGINT AS sub_category_id,
                        NULL::TEXT AS sub_category_name,
                        NULL::TEXT AS asset_description,
                        NULL::JSON AS asset_details,
                        NULL::JSON AS asset_classification,
                        NULL::JSON AS reading_parameters,
                        NULL::BIGINT AS registered_by_id,
                        NULL::TEXT AS registered_by_name;
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
                        NULL::BIGINT AS assets_type_id,
                        NULL::TEXT AS assets_type_name,
                        NULL::BIGINT AS category_id,
                        NULL::TEXT AS category_name,
                        NULL::BIGINT AS sub_category_id,
                        NULL::TEXT AS sub_category_name,
                        NULL::TEXT AS asset_description,
                        NULL::JSON AS asset_details,
                        NULL::JSON AS asset_classification,
                        NULL::JSON AS reading_parameters,
                        NULL::BIGINT AS registered_by_id,
                        NULL::TEXT AS registered_by_name;
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
                    a.assets_type AS assets_type_id,
                    ast.name::TEXT AS assets_type_name,
                    a.category AS category_id,
                    ac.name::TEXT AS category_name,
                    a.sub_category AS sub_category_id,
                    assc.name::TEXT AS sub_category_name,
                    a.asset_description::TEXT,
                    a.asset_details::JSON,
                    a.asset_classification::JSON,
                    a.reading_parameters::JSON,
                    a.registered_by AS registered_by_id,
                    ur.name::TEXT AS registered_by_name
                FROM
                    assets a
                INNER JOIN
                    assets_types ast ON a.assets_type = ast.id
                INNER JOIN
                    asset_categories ac ON a.category = ac.id
                INNER JOIN
                    asset_sub_categories assc ON a.sub_category = assc.id
                INNER JOIN
                    users ur ON a.registered_by = ur.id
                WHERE
                    (a.id = p_asset_id OR p_asset_id IS NULL OR p_asset_id = 0)
                    AND a.tenant_id = _tenant_id
                    AND a.deleted_at IS NULL
                    AND a.isactive = TRUE
                GROUP BY
                    a.id, ast.id, ac.id, assc.id, ur.id;
            
            END;
            $$;
        SQL);
        

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_details');
    }
};