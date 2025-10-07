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
            CREATE OR REPLACE FUNCTION get_asset_categories_list(
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_asset_categories_id INT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                name TEXT
            ) 
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF p_tenant_id IS NULL AND p_asset_categories_id IS NULL THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT AS status,
                        'All asset categories list fetched successfully'::TEXT AS message,
                        ac.id,
                        ac.name::TEXT
                    FROM asset_categories ac
                    WHERE ac.deleted_at IS NULL
                    AND ac.isactive = TRUE;
                    RETURN;
                END IF;

                IF p_tenant_id IS NULL OR p_tenant_id < 0 THEN
                    RETURN QUERY 
                    SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name;
                    RETURN;
                END IF;

                IF p_asset_categories_id IS NOT NULL AND p_asset_categories_id < 0 THEN
                    RETURN QUERY 
                    SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid asset category ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name;
                    RETURN;
                END IF;

                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Asset categories fetched successfully'::TEXT AS message,
                    ac.id,
                    ac.name::TEXT
                FROM asset_categories ac
                WHERE (p_asset_categories_id IS NULL OR ac.id = p_asset_categories_id)
                AND ac.tenant_id = p_tenant_id
                AND ac.deleted_at IS NULL
                AND ac.isactive = TRUE;
            END; 
            $$;
            SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_categories_list');
    }
};