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
        DROP FUNCTION IF EXISTS insert_asset(
            IN p_name VARCHAR(255),
            IN p_thumbnail_image JSONB,
            IN p_assets_type BIGINT,
            IN p_category BIGINT,
            IN p_sub_category BIGINT,
            IN p_assets_value DECIMAL(12, 2),
            IN p_asset_description TEXT,
            IN p_asset_details JSONB,
            IN p_asset_classification JSONB,
            IN p_reading_parameters JSONB,
            IN p_tenant_id BIGINT,
            IN p_registered_by BIGINT,
            IN p_current_time TIMESTAMP WITH TIME ZONE
        );
        CREATE OR REPLACE FUNCTION insert_asset(
            IN p_name VARCHAR(255),
            IN p_thumbnail_image JSONB,
            IN p_category BIGINT,
            IN p_sub_category BIGINT,
            IN p_assets_value DECIMAL(12, 2),
            IN p_asset_description TEXT,
            IN p_asset_details JSONB,
            IN p_asset_classification JSONB,
            IN p_reading_parameters JSONB,
            IN p_tenant_id BIGINT,
            IN p_registered_by BIGINT,
            IN p_current_time TIMESTAMP WITH TIME ZONE,
            IN p_expected_life_time VARCHAR(255)
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            new_asset JSONB
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            asset_id BIGINT;
            new_asset JSONB;
            error_message TEXT;
        BEGIN
            -- Validate critical inputs
            IF p_name IS NULL OR LENGTH(TRIM(p_name)) = 0 THEN
                RETURN QUERY SELECT 'FAILURE', 'Asset name cannot be empty', NULL::JSONB;
                RETURN;
            END IF;

            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT 'FAILURE', 'Invalid tenant ID provided', NULL::JSONB;
                RETURN;
            END IF;
            
            -- Try to insert the asset data
            BEGIN
                INSERT INTO assets (
                    name,
                    thumbnail_image,
                    category,
                    sub_category,
                    asset_value,
                    asset_description,
                    asset_details,
                    asset_classification,
                    reading_parameters,
                    registered_by,
                    tenant_id,
                    created_at,
                    updated_at,
                    average_life_time
                )
                VALUES (
                    p_name,
                    p_thumbnail_image,
                    p_category,
                    p_sub_category,
                    p_assets_value,
                    p_asset_description,
                    p_asset_details,
                    p_asset_classification,
                    p_reading_parameters,
                    p_registered_by,
                    p_tenant_id,
                    p_current_time,
                    p_current_time,
                    p_expected_life_time
                )
                RETURNING id, to_jsonb(assets.*) INTO asset_id, new_asset;

                -- Return success message with old and new data
                RETURN QUERY SELECT 'SUCCESS', 'Asset registered successfully', new_asset;
            EXCEPTION
                WHEN OTHERS THEN
                    error_message := SQLERRM;
                    RETURN QUERY SELECT 'ERROR', 'Error during insert: ' || error_message, NULL::JSONB;
            END;
        END;
        $$;
    SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS insert_asset');
    }
};
