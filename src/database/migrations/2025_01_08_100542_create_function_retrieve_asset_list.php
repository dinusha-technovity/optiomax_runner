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
            CREATE OR REPLACE FUNCTION get_asset_list(
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_asset_id INT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                name TEXT,
                thumbnail_image JSON,
                asset_classification JSON
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Case 1: Return all records when both parameters are NULL
                IF p_tenant_id IS NULL AND p_asset_id IS NULL THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT AS status,
                        'All asset list fetched successfully'::TEXT AS message,
                        a.id,
                        a.name::TEXT,
                        a.thumbnail_image::JSON,
                        a.asset_classification::JSON
                    FROM assets a
                    WHERE a.deleted_at IS NULL
                    AND a.isactive = TRUE;
                    RETURN;
                END IF;

                -- Case 2: Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id < 0 THEN
                    RETURN QUERY 
                    SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::JSON AS thumbnail_image,
                        NULL::JSON AS asset_classification;
                    RETURN;
                END IF;

                -- Case 3: Validate asset ID if provided
                IF p_asset_id IS NOT NULL AND p_asset_id < 0 THEN
                    RETURN QUERY 
                    SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid asset ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::JSON AS thumbnail_image,
                        NULL::JSON AS asset_classification;
                    RETURN;
                END IF;
                
                -- Case 4: Return matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Asset asset fetched successfully'::TEXT AS message,
                    a.id,
                    a.name::TEXT,
                    a.thumbnail_image::JSON,
                    a.asset_classification::JSON
                FROM assets a
                WHERE (p_asset_id IS NULL OR a.id = p_asset_id)
                AND a.tenant_id = p_tenant_id
                AND a.deleted_at IS NULL
                AND a.isactive = TRUE;

            END; 
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_list');
    }
};