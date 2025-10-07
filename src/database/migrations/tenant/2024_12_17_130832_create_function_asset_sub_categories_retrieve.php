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
        DB::unprepared(<<<SQL
        CREATE OR REPLACE FUNCTION get_asset_sub_categories(
            p_tenant_id BIGINT,
            p_asset_sub_categories_id INT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            id BIGINT,
            subcategories_name TEXT,
            subcategories_description TEXT,
            subcategoriesreading_parameters JSON,
            asset_category_id BIGINT,
            category_name TEXT,
            categoriesreading_parameters JSON
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            sub_category_count INT;
        BEGIN
            -- Validate tenant ID
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT AS status,
                    'Invalid tenant ID provided'::TEXT AS message,
                    NULL::BIGINT AS id,
                    NULL::TEXT AS subcategories_name,
                    NULL::TEXT AS subcategories_description,
                    NULL::JSON AS subcategoriesreading_parameters,
                    NULL::BIGINT AS asset_category_id,
                    NULL::TEXT AS category_name,
                    NULL::JSON AS categoriesreading_parameters;
                RETURN;
            END IF;

            -- Validate subcategory ID (optional)
            IF p_asset_sub_categories_id IS NOT NULL AND p_asset_sub_categories_id < 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT AS status,
                    'Invalid subcategory ID provided'::TEXT AS message,
                    NULL::BIGINT AS id,
                    NULL::TEXT AS subcategories_name,
                    NULL::TEXT AS subcategories_description,
                    NULL::JSON AS subcategoriesreading_parameters,
                    NULL::BIGINT AS asset_category_id,
                    NULL::TEXT AS category_name,
                    NULL::JSON AS categoriesreading_parameters;
                RETURN;
            END IF;

            -- Check if any matching records exist
            SELECT COUNT(*) INTO sub_category_count
            FROM asset_sub_categories asct
            WHERE (p_asset_sub_categories_id IS NULL OR asct.id = p_asset_sub_categories_id)
            AND asct.tenant_id = p_tenant_id
            AND asct.deleted_at IS NULL
            AND asct.isactive = TRUE;

            IF sub_category_count = 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT AS status,
                    'No matching subcategories found'::TEXT AS message,
                    NULL::BIGINT AS id,
                    NULL::TEXT AS subcategories_name,
                    NULL::TEXT AS subcategories_description,
                    NULL::JSON AS subcategoriesreading_parameters,
                    NULL::BIGINT AS asset_category_id,
                    NULL::TEXT AS category_name,
                    NULL::JSON AS categoriesreading_parameters;
                RETURN;
            END IF;

            -- Return the matching records
            RETURN QUERY
            SELECT
                'SUCCESS'::TEXT AS status,
                'Subcategories fetched successfully'::TEXT AS message,
                asct.id,
                asct.name::TEXT AS subcategories_name,
                asct.description::TEXT AS subcategories_description,
                asct.reading_parameters::JSON AS subcategoriesreading_parameters,
                asct.asset_category_id,
                ac.name::TEXT AS category_name,
                ac.reading_parameters::JSON AS categoriesreading_parameters
            FROM
                asset_sub_categories asct
            INNER JOIN
                asset_categories ac ON asct.asset_category_id = ac.id
            WHERE
                (p_asset_sub_categories_id IS NULL OR asct.id = p_asset_sub_categories_id)
                AND asct.tenant_id = p_tenant_id
                AND asct.deleted_at IS NULL
                AND asct.isactive = TRUE;

        END;
        $$;
        SQL);
    }
 
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_sub_categories');
    }
};