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
            CREATE OR REPLACE FUNCTION get_asset_categories_with_sub_categories(
                p_tenant_id BIGINT,
                p_asset_categories_id INT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                ac_id BIGINT,
                categories_name TEXT,
                categories_reading_parameters JSON,
                sub_categories JSON
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT AS ac_id,
                        NULL::TEXT AS categories_name,
                        NULL::JSON AS categories_reading_parameters,
                        NULL::JSON AS sub_categories;
                    RETURN;
                END IF;
            
                -- Validate asset category ID
                IF p_asset_categories_id IS NOT NULL AND p_asset_categories_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid asset category ID provided'::TEXT AS message,
                        NULL::BIGINT AS ac_id,
                        NULL::TEXT AS categories_name,
                        NULL::JSON AS categories_reading_parameters,
                        NULL::JSON AS sub_categories;
                    RETURN;
                END IF;
            
                -- Check if any matching records exist
                IF NOT EXISTS (
                    SELECT 1
                    FROM asset_categories ac
                    WHERE ac.tenant_id = p_tenant_id
                    AND (p_asset_categories_id IS NULL OR ac.id = p_asset_categories_id)
                    AND ac.deleted_at IS NULL
                    AND ac.isactive = TRUE
                ) THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'No matching asset categories found'::TEXT AS message,
                        NULL::BIGINT AS ac_id,
                        NULL::TEXT AS categories_name,
                        NULL::JSON AS categories_reading_parameters,
                        NULL::JSON AS sub_categories;
                    RETURN;
                END IF;
            
                -- Return matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Asset categories fetched successfully'::TEXT AS message,
                    ac.id AS ac_id,
                    ac.name::TEXT AS categories_name,
                    ac.reading_parameters::JSON AS categories_reading_parameters,
                    COALESCE(
                        json_agg(
                            json_build_object(
                                'assc_id', assc.id,
                                'asset_category_id', assc.asset_category_id,
                                'assc_name', assc.name,
                                'assc_reading_parameters', assc.reading_parameters
                            )
                        ) FILTER (WHERE assc.id IS NOT NULL), '[]'
                    ) AS sub_categories
                FROM
                    asset_categories ac
                LEFT JOIN
                    asset_sub_categories assc ON ac.id = assc.asset_category_id
                WHERE
                    ac.tenant_id = p_tenant_id
                    AND (p_asset_categories_id IS NULL OR ac.id = p_asset_categories_id)
                    AND ac.deleted_at IS NULL
                    AND ac.isactive = TRUE
                    AND (assc.deleted_at IS NULL AND assc.isactive = TRUE)
                GROUP BY
                    ac.id
                ORDER BY
                    ac.id;
            
            END;
            $$;
        SQL);                  
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_categories_with_sub_categories');
    }
};