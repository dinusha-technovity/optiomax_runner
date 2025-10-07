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
        CREATE OR REPLACE FUNCTION upload_thumbnail_image(
            IN _original_name VARCHAR,
            IN _stored_name VARCHAR,
            IN _size VARCHAR,
            IN _mime_type VARCHAR,
            IN _tenant_id BIGINT,
            IN _user_id BIGINT,
            IN _category_id BIGINT DEFAULT NULL,
            IN _field_id BIGINT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            inserted_id BIGINT,
            stored_file_name VARCHAR
        )
        LANGUAGE plpgsql
        AS $$
        BEGIN
            IF _original_name IS NULL OR _original_name = '' THEN
                RETURN QUERY SELECT 'FAILURE', 'Original file name cannot be null or empty', NULL, NULL;
                RETURN;
            END IF;

            IF _stored_name IS NULL OR _stored_name = '' THEN
                RETURN QUERY SELECT 'FAILURE', 'Stored file name cannot be null or empty', NULL, NULL;
                RETURN;
            END IF;

            IF _size IS NULL OR _size = '' THEN
                RETURN QUERY SELECT 'FAILURE', 'File size cannot be null or empty', NULL, NULL;
                RETURN;
            END IF;

            IF _mime_type IS NULL OR _mime_type = '' THEN
                RETURN QUERY SELECT 'FAILURE', 'MIME type cannot be null or empty', NULL, NULL;
                RETURN;
            END IF;

            IF _tenant_id IS NULL THEN
                RETURN QUERY SELECT 'FAILURE', 'Tenant ID cannot be null', NULL, NULL;
                RETURN;
            END IF;

            IF _user_id IS NULL THEN
                RETURN QUERY SELECT 'FAILURE', 'User ID cannot be null', NULL, NULL;
                RETURN;
            END IF;

            RETURN QUERY 
            WITH inserted AS (
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
                    _category_id,
                    _field_id,
                    _original_name,
                    _stored_name,
                    _size,
                    _mime_type,
                    _tenant_id,
                    TRUE,
                    _user_id,
                    _user_id,
                    NOW(),
                    NOW()
                )
                RETURNING id, document_media.stored_file_name
            )
            SELECT 
                'SUCCESS',
                'Thumbnail image uploaded successfully',
                i.id,
                i.stored_file_name
            FROM inserted i;
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
