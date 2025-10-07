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
        CREATE OR REPLACE FUNCTION get_document_media_file_by_name(p_document_media_stored_file_name TEXT)
        RETURNS TEXT
        LANGUAGE plpgsql AS $$
        DECLARE
            v_full_path TEXT;
        BEGIN
            SELECT 
                CONCAT(dcf.file_path, '/', dm.stored_file_name)
            INTO v_full_path
            FROM document_media dm
            LEFT JOIN document_category_field dcf 
                ON dm.document_field_id = dcf.id AND dcf.isactive = true
            WHERE 
                LOWER(dm.stored_file_name) = LOWER(p_document_media_stored_file_name)
                AND dm.isactive = true
                AND dm.stored_file_name IS NOT NULL
                AND dcf.file_path IS NOT NULL;

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
        DB::unprepared('DROP FUNCTION IF EXISTS get_document_media_file_by_name(TEXT);');

    }
};
