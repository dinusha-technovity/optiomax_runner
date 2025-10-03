<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {  
         DB::unprepared(<<<SQL
            -- Drop the function if it already exists
            DROP FUNCTION IF EXISTS insert_asset_group(
                VARCHAR,
                JSONB,
                BIGINT,
                BIGINT,
                TEXT,
                JSONB,
                JSONB,
                JSONB,
                BIGINT,
                BIGINT,
                TIMESTAMPTZ
            );
            CREATE OR REPLACE FUNCTION insert_asset_group(
                IN p_name VARCHAR(255),
                IN p_thumbnail_image JSONB,
                IN p_category BIGINT,
                IN p_sub_category BIGINT,
                IN p_asset_description TEXT,
                IN p_asset_details JSONB,
                IN p_asset_classification JSONB,
                IN p_reading_parameters JSONB,
                IN p_tenant_id BIGINT,
                IN p_registered_by BIGINT,
                IN p_current_time TIMESTAMP WITH TIME ZONE
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                new_asset JSONB
            )
            LANGUAGE plpgsql
            AS \$\$
            DECLARE
                asset_id BIGINT;
                new_asset JSONB;
                error_message TEXT;
                v_log_success BOOLEAN := FALSE;
                existing_asset_count INTEGER;
            BEGIN
                IF p_name IS NULL OR LENGTH(TRIM(p_name)) = 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Asset name cannot be empty', NULL::JSONB;
                END IF;

                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Invalid tenant ID provided', NULL::JSONB;
                END IF;

                -- Check for duplicate asset group name within the same tenant
                SELECT COUNT(*) INTO existing_asset_count
                FROM assets
                WHERE LOWER(TRIM(name)) = LOWER(TRIM(p_name))
                AND tenant_id = p_tenant_id;

                IF existing_asset_count > 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Asset group name already exists', NULL::JSONB;
                END IF;

                BEGIN
                    INSERT INTO assets (
                        name,
                        thumbnail_image,
                        category,
                        sub_category,
                        asset_description,
                        asset_details,
                        asset_classification,
                        reading_parameters,
                        registered_by,
                        tenant_id,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        p_name,
                        p_thumbnail_image,
                        p_category,
                        p_sub_category,
                        p_asset_description,
                        p_asset_details,
                        p_asset_classification,
                        p_reading_parameters,
                        p_registered_by,
                        p_tenant_id,
                        p_current_time,
                        p_current_time
                    )
                    RETURNING id, to_jsonb(assets.*) INTO asset_id, new_asset;

                    BEGIN
                        PERFORM log_activity(
                            'create_asset_group',
                            'Asset group created',
                            'asset_group',
                            asset_id,
                            'user',
                            p_registered_by,
                            new_asset,
                            p_tenant_id
                        );
                        v_log_success := TRUE;
                    EXCEPTION WHEN OTHERS THEN
                        v_log_success := FALSE;
                    END;

                    RETURN QUERY SELECT 'SUCCESS', 'Asset group created successfully', new_asset;
                EXCEPTION WHEN OTHERS THEN
                    error_message := SQLERRM;
                    RETURN QUERY SELECT 'ERROR', 'Error during insert: ' || error_message, NULL::JSONB;
                END;
            END;
            \$\$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS insert_asset_group');
    }
};
