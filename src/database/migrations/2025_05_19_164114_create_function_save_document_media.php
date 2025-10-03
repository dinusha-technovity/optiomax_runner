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
            CREATE OR REPLACE FUNCTION insert_document_media_bulk(
                        IN _items JSON,
                        IN _current_time TIMESTAMP WITH TIME ZONE
                    )
                    RETURNS TABLE (
                        status TEXT,
                        message TEXT,
                        inserted_data JSON
                    )
                    LANGUAGE plpgsql
                    AS $$
                    
            DECLARE
                item JSON;               -- Iterates over each item in the input JSON array
                inserted_id BIGINT;
                inserted_category_id BIGINT;
                inserted_field_id BIGINT;
                inserted_stored_name TEXT;
                result_obj JSON;
                result_array JSON[] := '{}'; -- Array to store all result objects
            BEGIN
                -- Loop through the items JSON array and insert each item
                FOR item IN SELECT * FROM json_array_elements(_items)
                LOOP
                    -- Validate required fields for each item
                    IF (item->>'original_name') IS NULL OR (item->>'original_name') = '' THEN
                        RETURN QUERY SELECT 'FAILURE'::TEXT, 'Original file name cannot be null or empty for one or more items'::TEXT, NULL::JSON;
                        RETURN;
                    END IF;
                    
                    IF (item->>'stored_name') IS NULL OR (item->>'stored_name') = '' THEN
                        RETURN QUERY SELECT 'FAILURE'::TEXT, 'Stored file name cannot be null or empty for one or more items'::TEXT, NULL::JSON;
                        RETURN;
                    END IF;
                    
                    IF (item->>'size') IS NULL OR (item->>'size') = '' THEN
                        RETURN QUERY SELECT 'FAILURE'::TEXT, 'File size cannot be null or empty for one or more items'::TEXT, NULL::JSON;
                        RETURN;
                    END IF;
                    
                    IF (item->>'mime_type') IS NULL OR (item->>'mime_type') = '' THEN
                        RETURN QUERY SELECT 'FAILURE'::TEXT, 'MIME type cannot be null or empty for one or more items'::TEXT, NULL::JSON;
                        RETURN;
                    END IF;
                    
                    IF (item->>'tenant_id') IS NULL THEN
                        RETURN QUERY SELECT 'FAILURE'::TEXT, 'Tenant ID cannot be null for one or more items'::TEXT, NULL::JSON;
                        RETURN;
                    END IF;
                    
                    IF (item->>'user') IS NULL THEN
                        RETURN QUERY SELECT 'FAILURE'::TEXT, 'User ID cannot be null for one or more items'::TEXT, NULL::JSON;
                        RETURN;
                    END IF;

                    -- Insert item into document_media table and get the generated id and references
                    INSERT INTO document_media (
                        document_category_id,
                        document_field_id,
                        original_file_name,
                        stored_file_name,
                        file_size,
                        mime_type,
                        tenant_id,
                        isactive,
                        created_by,
                        modified_by,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        (item->>'document_category_id')::BIGINT,
                        (item->>'document_field_id')::BIGINT,
                        item->>'original_name',
                        item->>'stored_name',
                        item->>'size',
                        item->>'mime_type',
                        (item->>'tenant_id')::BIGINT,
                        TRUE, -- Default isactive to true
                        (item->>'user')::BIGINT,
                        (item->>'user')::BIGINT,
                        _current_time,
                        _current_time
                    )
                    RETURNING 
                        id,
                        document_category_id,
                        document_field_id,
                        stored_file_name
                    INTO 
                        inserted_id,
                        inserted_category_id,
                        inserted_field_id,
                        inserted_stored_name;

                    -- Create a simplified result object
                    result_obj := json_build_object(
                        'id', inserted_id,
                        'category_id', inserted_category_id,
                        'field_id', inserted_field_id,
                        'file_name', inserted_stored_name
                    );

                    -- Append the result object to the array
                    result_array := array_append(result_array, result_obj);
                END LOOP;

                -- Return the concatenated JSON array and success message
                RETURN QUERY SELECT 
                    'SUCCESS'::TEXT AS status,
                    'All document media items inserted successfully'::TEXT AS message,
                    json_agg(unnested_row) AS inserted_data
                FROM unnest(result_array) unnested_row;
            END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS insert_document_media_bulk(BIGINT, BIGINT, BIGINT);');

    }
};
