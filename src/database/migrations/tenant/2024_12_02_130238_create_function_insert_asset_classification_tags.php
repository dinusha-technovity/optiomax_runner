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
        // DB::unprepared("
        //     CREATE OR REPLACE PROCEDURE create_insert_asset_classification_tags_procedures(
        //         IN p_label VARCHAR(255),
        //         IN p_tenant_id BIGINT,
        //         IN p_current_time TIMESTAMP WITH TIME ZONE
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     DECLARE
        //         tag_id BIGINT;  -- Declare the organization_id variable
        //         error_message TEXT;
        //     BEGIN
        //         -- Create a temporary response table to store the result
        //         DROP TABLE IF EXISTS response;
        //         CREATE TEMP TABLE response (
        //             status TEXT,
        //             message TEXT,
        //             tag_id BIGINT DEFAULT 0
        //         );

        //         -- Try inserting the asset_classification_tags_library data
        //         BEGIN
        //             INSERT INTO asset_classification_tags_library (
        //                 label,
        //                 tenant_id,
        //                 created_at,
        //                 updated_at
        //             )
        //             VALUES (
        //                 p_label,
        //                 p_tenant_id,
        //                 p_current_time,
        //                 p_current_time
        //             )
        //             RETURNING id INTO tag_id;  -- Assigning the returned ID to the variable

        //             -- Insert success message into the response table
        //             INSERT INTO response (status, message, tag_id)
        //             VALUES ('SUCCESS', 'Organization inserted successfully', tag_id);
        //         EXCEPTION
        //             WHEN OTHERS THEN
        //                 error_message := SQLERRM;
        //                 INSERT INTO response (status, message)
        //                 VALUES ('ERROR', 'Error during insert: ' || error_message);
        //         END;
        //     END;
        //     $$;
        // ");
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION insert_asset_classification_tags(
                IN p_label VARCHAR(255),
                IN p_tenant_id BIGINT,
                IN p_current_time TIMESTAMP WITH TIME ZONE
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                tag_id BIGINT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                tag_id BIGINT;  -- To capture the ID of the inserted tag
                error_message TEXT;  -- To capture any error messages
            BEGIN
                -- Validate critical inputs
                IF p_label IS NULL OR LENGTH(TRIM(p_label)) = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Label cannot be empty'::TEXT AS message, 
                        NULL::BIGINT AS tag_id;
                    RETURN;
                END IF;

                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Invalid tenant ID provided'::TEXT AS message, 
                        NULL::BIGINT AS tag_id;
                    RETURN;
                END IF;

                -- Try inserting the asset_classification_tags_library data
                BEGIN
                    INSERT INTO asset_classification_tags_library (
                        label,
                        tenant_id,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        p_label,
                        p_tenant_id,
                        p_current_time,
                        p_current_time
                    )
                    RETURNING id INTO tag_id;

                    -- Return success message and generated tag ID
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status, 
                        'Tag inserted successfully'::TEXT AS message, 
                        tag_id;
                EXCEPTION
                    WHEN OTHERS THEN
                        error_message := SQLERRM;
                        -- Return failure message with error details
                        RETURN QUERY SELECT 
                            'ERROR'::TEXT AS status, 
                            'Error during insert: ' || error_message::TEXT AS message, 
                            NULL::BIGINT AS tag_id;
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
        DB::unprepared('DROP FUNCTION IF EXISTS insert_asset_classification_tags');
    }
};
