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
        // DB::unprepared(
        //     "CREATE OR REPLACE PROCEDURE store_procedure_retrieve_asset_classification_tags_library(
        //         IN _tenant_id BIGINT,
        //         IN p_tags_id INT DEFAULT NULL
        //     ) LANGUAGE plpgsql AS
        //     $$
        //     BEGIN
        //         DROP TABLE IF EXISTS asset_classification_tags_library_from_store_procedure;
            
        //         IF p_tags_id IS NOT NULL AND p_tags_id <= 0 THEN
        //             RAISE EXCEPTION 'Invalid p_tags_id: %', p_tags_id;
        //         END IF;
            
        //         CREATE TEMP TABLE asset_classification_tags_library_from_store_procedure AS
        //         SELECT * FROM asset_classification_tags_library 
        //         WHERE (asset_classification_tags_library.id = p_tags_id 
        //             OR p_tags_id IS NULL 
        //             OR p_tags_id = 0)
        //             AND asset_classification_tags_library.tenant_id = _tenant_id
        //             AND asset_classification_tags_library.deleted_at IS NULL
        //             AND asset_classification_tags_library.isactive = TRUE;
        //     END;
        //     $$;"
        // );
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_asset_classification_tags_library(
                _tenant_id BIGINT,
                p_tags_id INT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                label TEXT,
                isactive BOOLEAN
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                tags_count INT;
            BEGIN
                -- Validate tenant ID
                IF _tenant_id IS NULL OR _tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS label,
                        NULL::BOOLEAN AS isactive;
                    RETURN;
                END IF;
            
                -- Validate tag ID (optional)
                IF p_tags_id IS NOT NULL AND p_tags_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tag ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS label,
                        NULL::BOOLEAN AS isactive;
                    RETURN;
                END IF;
            
                -- Check if any matching records exist
                SELECT COUNT(*) INTO tags_count
                FROM asset_classification_tags_library acl
                WHERE (p_tags_id IS NULL OR acl.id = p_tags_id)
                AND acl.tenant_id = _tenant_id
                AND acl.deleted_at IS NULL
                AND acl.isactive = TRUE;
            
                IF tags_count = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'No matching tags found'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS label,
                        NULL::BOOLEAN AS isactive;
                    RETURN;
                END IF;
            
                -- Return the matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Tags fetched successfully'::TEXT AS message,
                    acl.id,
                    acl.label::TEXT,
                    acl.isactive
                FROM
                    asset_classification_tags_library acl
                WHERE
                    (p_tags_id IS NULL OR acl.id = p_tags_id)
                    AND acl.tenant_id = _tenant_id
                    AND acl.deleted_at IS NULL
                    AND acl.isactive = TRUE;
            
            END;
            $$;
        SQL);
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_classification_tags_library');
    }
};