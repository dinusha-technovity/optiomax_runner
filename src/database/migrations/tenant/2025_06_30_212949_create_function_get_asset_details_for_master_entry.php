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
            CREATE OR REPLACE FUNCTION get_asset_details_for_master_entry(
                p_tenant_id BIGINT,
                p_asset_item_id BIGINT DEFAULT NULL
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
                asset_tag VARCHAR
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Invalid tenant ID provided'::TEXT,
                        NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL;
                    RETURN;
                END IF;

                -- Validate asset item ID
                IF p_asset_item_id IS NOT NULL AND p_asset_item_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Invalid asset item ID provided'::TEXT,
                        NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL;
                    RETURN;
                END IF;

                -- Return matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT,
                    'Asset items retrieved successfully'::TEXT,
                    ai.id, 
                    a.id,
                    a.name,
                    ai.model_number,
                    ai.serial_number,
                    ai.thumbnail_image,
                    ai.qr_code,
                    ai.asset_tag
                FROM asset_items ai
                INNER JOIN assets a ON ai.asset_id = a.id
                WHERE (ai.id = p_asset_item_id OR p_asset_item_id IS NULL)
                AND ai.tenant_id = p_tenant_id
                AND ai.deleted_at IS NULL
                AND ai.isactive = TRUE;
            END;
            $$
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       DB::unprepared('DROP FUNCTION IF EXISTS get_asset_details_for_master_entry( BIGINT, BIGINT);');
    }
};
