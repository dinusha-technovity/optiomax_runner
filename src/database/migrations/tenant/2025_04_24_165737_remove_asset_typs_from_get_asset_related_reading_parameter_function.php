<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            DROP FUNCTION IF EXISTS get_asset_related_reading_parameter(
                _tenant_id BIGINT,
                p_asset_id INT
            );
            CREATE OR REPLACE FUNCTION get_asset_related_reading_parameter(
                _tenant_id BIGINT,
                p_asset_id INT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                asset_category_reading_parameters JSON,
                asset_sub_category_reading_parameters JSON,
                asset_reading_parameters JSON
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
                        NULL::JSON AS asset_category_reading_parameters,
                        NULL::JSON AS asset_sub_category_reading_parameters,
                        NULL::JSON AS asset_reading_parameters;
                    RETURN;
                END IF;
            
                -- Validate asset ID (optional)
                IF p_asset_id IS NOT NULL AND p_asset_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid p_asset_id provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::JSON AS asset_category_reading_parameters,
                        NULL::JSON AS asset_sub_category_reading_parameters,
                        NULL::JSON AS asset_reading_parameters;
                    RETURN;
                END IF;
            
                -- Return the matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Asset reading parameters retrieved successfully'::TEXT AS message,
                    a.id,
                    ac.reading_parameters::JSON AS asset_category_reading_parameters,
                    assc.reading_parameters::JSON AS asset_sub_category_reading_parameters,
                    a.reading_parameters::JSON AS asset_reading_parameters
                FROM
                    assets a
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
                    a.id, ac.id, assc.id, ur.id;
            
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_related_reading_parameter');
    }
};
