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
        CREATE OR REPLACE FUNCTION get_document_media_file(p_document_media_id BIGINT)
        RETURNS TEXT
        LANGUAGE plpgsql AS $$
        DECLARE
            v_file_path TEXT;
            v_stored_file_name TEXT;
            v_isactive BOOLEAN;
            v_full_path TEXT;
        BEGIN
            -- Check if the document media exists and is active
            SELECT dm.isactive, dm.stored_file_name, dcf.file_path
            INTO v_isactive, v_stored_file_name, v_file_path
            FROM document_media dm
            LEFT JOIN document_category_field dcf ON dm.document_field_id = dcf.id AND dcf.isactive = true
            WHERE dm.id = p_document_media_id;
            
            -- If record not found
            IF NOT FOUND THEN
                RETURN NULL;
            END IF;
            
            -- If record is not active
            IF v_isactive = false THEN
                RETURN NULL;
            END IF;
            
            -- If file path is not available
            IF v_file_path IS NULL OR v_stored_file_name IS NULL THEN
                RETURN NULL;
            END IF;
            
            -- Construct full path (you might need to adjust path concatenation based on your OS)
            v_full_path := CONCAT(v_file_path, '/', v_stored_file_name);
            
            RETURN v_full_path;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_document_media_file(BIGINT;');

    }
};
