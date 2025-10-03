<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
        DROP FUNCTION IF EXISTS get_assets_group_list(BIGINT, BIGINT);

        CREATE OR REPLACE FUNCTION get_assets_group_list(
            IN p_tenant_id BIGINT DEFAULT NULL,
            IN p_asset_id BIGINT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            id BIGINT,
            name TEXT,
            asset_type TEXT,
            asset_description TEXT
        )
        LANGUAGE plpgsql
        AS $$
        BEGIN
            -- Validate tenant ID
            IF p_tenant_id IS NULL OR p_tenant_id < 0 THEN
                RETURN QUERY 
                SELECT 
                    'FAILURE',
                    'Invalid tenant ID provided',
                    NULL::BIGINT,
                    NULL::TEXT,
                    NULL::TEXT,
                    NULL::TEXT;
                RETURN;
            END IF;

            -- Validate asset ID
            IF p_asset_id IS NOT NULL AND p_asset_id < 0 THEN
                RETURN QUERY 
                SELECT 
                    'FAILURE',
                    'Invalid asset ID provided',
                    NULL::BIGINT,
                    NULL::TEXT,
                    NULL::TEXT,
                    NULL::TEXT;
                RETURN;
            END IF;

            -- Fetch assets
            RETURN QUERY
            SELECT
                'SUCCESS'::TEXT,
                'Assets fetched successfully'::TEXT,
                a.id,
                a.name::TEXT,
                at.name::TEXT,
                a.asset_description::TEXT
            FROM assets a
            JOIN assets_types at ON at.id = a.assets_type
            WHERE a.deleted_at IS NULL
              AND a.isactive = TRUE
              AND a.tenant_id = p_tenant_id
              AND (p_asset_id IS NULL OR a.id = p_asset_id);
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_assets_group_list(BIGINT, BIGINT)');
    }
};
