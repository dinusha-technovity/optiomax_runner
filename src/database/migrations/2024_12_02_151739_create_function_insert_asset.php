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
        // DB::unprepared("
        //     CREATE OR REPLACE PROCEDURE create_insert_asset_procedures(
        //         IN p_name VARCHAR(255),
        //         IN p_thumbnail_image JSONB,
        //         IN p_assets_type BIGINT,
        //         IN p_category BIGINT,
        //         IN p_sub_category BIGINT,
        //         IN p_assets_value DECIMAL(10, 2), 
        //         IN p_asset_description text,
        //         IN p_asset_details JSONB,
        //         IN p_asset_classification JSONB,
        //         IN p_tenant_id BIGINT,
        //         IN p_registered_by BIGINT,
        //         IN p_current_time TIMESTAMP WITH TIME ZONE
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     DECLARE
        //         asset_id BIGINT;  -- Declare the organization_id variable
        //         error_message TEXT;
        //     BEGIN
        //         -- Create a temporary response table to store the result
        //         DROP TABLE IF EXISTS response;
        //         CREATE TEMP TABLE response (
        //             status TEXT,
        //             message TEXT,
        //             asset_id BIGINT DEFAULT 0
        //         );

        //         -- Try inserting the asset_classification_tags_library data
        //         BEGIN
        //             INSERT INTO assets (
        //                 name,
        //                 thumbnail_image,
        //                 assets_type,
        //                 category,
        //                 sub_category,
        //                 asset_value,
        //                 asset_description,
        //                 asset_details,
        //                 asset_classification,
        //                 registered_by,
        //                 tenant_id,
        //                 created_at,
        //                 updated_at
        //             )
        //             VALUES (
        //                 p_name,
        //                 p_thumbnail_image,
        //                 p_assets_type,
        //                 p_category,
        //                 p_sub_category,
        //                 p_assets_value,
        //                 p_asset_description,
        //                 p_asset_details,
        //                 p_asset_classification,
        //                 p_registered_by,
        //                 p_tenant_id,
        //                 p_current_time,
        //                 p_current_time
        //             )
        //             RETURNING id INTO asset_id;  -- Assigning the returned ID to the variable

        //             -- Insert success message into the response table
        //             INSERT INTO response (status, message, asset_id)
        //             VALUES ('SUCCESS', 'asset inserted successfully', asset_id);
        //         EXCEPTION
        //             WHEN OTHERS THEN
        //                 error_message := SQLERRM;
        //                 INSERT INTO response (status, message)
        //                 VALUES ('ERROR', 'Error during insert: ' || error_message);
        //         END;
        //     END;
        //     $$;
        // ");
 
        // DB::unprepared(<<<SQL
        //     CREATE OR REPLACE FUNCTION insert_asset(
        //         IN p_name VARCHAR(255),
        //         IN p_thumbnail_image JSONB,
        //         IN p_assets_type BIGINT,
        //         IN p_category BIGINT,
        //         IN p_sub_category BIGINT,
        //         IN p_assets_value DECIMAL(12, 2),
        //         IN p_asset_description TEXT,
        //         IN p_asset_details JSONB,
        //         IN p_asset_classification JSONB,
        //         IN p_reading_parameters JSONB,
        //         IN p_tenant_id BIGINT,
        //         IN p_registered_by BIGINT,
        //         IN p_current_time TIMESTAMP WITH TIME ZONE
        //     )
        //     RETURNS TABLE (
        //         status TEXT,
        //         message TEXT,
        //         asset_id BIGINT
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     DECLARE
        //         asset_id BIGINT; -- Captures the ID of the inserted item
        //         error_message TEXT; -- Captures any error message during the insert
        //     BEGIN
        //         -- Validate critical inputs
        //         IF p_name IS NULL OR LENGTH(TRIM(p_name)) = 0 THEN
        //             RETURN QUERY SELECT 
        //                 'FAILURE'::TEXT AS status, 
        //                 'Asset name cannot be empty'::TEXT AS message, 
        //                 NULL::BIGINT AS asset_id;
        //             RETURN;
        //         END IF;

        //         IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
        //             RETURN QUERY SELECT 
        //                 'FAILURE'::TEXT AS status, 
        //                 'Invalid tenant ID provided'::TEXT AS message, 
        //                 NULL::BIGINT AS asset_id;
        //             RETURN;
        //         END IF;

        //         -- Try to insert the asset data
        //         BEGIN
        //             INSERT INTO assets (
        //                 name,
        //                 thumbnail_image,
        //                 assets_type,
        //                 category,
        //                 sub_category,
        //                 asset_value,
        //                 asset_description,
        //                 asset_details,
        //                 asset_classification,
        //                 reading_parameters,
        //                 registered_by,
        //                 tenant_id,
        //                 created_at,
        //                 updated_at
        //             )
        //             VALUES (
        //                 p_name,
        //                 p_thumbnail_image,
        //                 p_assets_type,
        //                 p_category,
        //                 p_sub_category,
        //                 p_assets_value,
        //                 p_asset_description,
        //                 p_asset_details,
        //                 p_asset_classification,
        //                 p_reading_parameters,
        //                 p_registered_by,
        //                 p_tenant_id,
        //                 p_current_time,
        //                 p_current_time
        //             )
        //             RETURNING id INTO asset_id;

        //             -- Return success message and generated asset ID
        //             RETURN QUERY SELECT 
        //                 'SUCCESS'::TEXT AS status, 
        //                 'Asset Register successfully'::TEXT AS message, 
        //                 asset_id;
        //         EXCEPTION
        //             WHEN OTHERS THEN
        //                 error_message := SQLERRM;
        //                 -- Return failure message with error details
        //                 RETURN QUERY SELECT 
        //                     'ERROR'::TEXT AS status, 
        //                     'Error during insert: ' || error_message::TEXT AS message, 
        //                     NULL::BIGINT AS asset_id;
        //         END;
        //     END;
        // $$;
        // SQL);

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION insert_asset(
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
                        assets_type,
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
                        updated_at
                    )
                    VALUES (
                        p_name,
                        p_thumbnail_image,
                        p_assets_type,
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
                        p_current_time
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
        DB::unprepared('DROP PROCEDURE IF EXISTS create_insert_asset_procedures;');
    } 
};