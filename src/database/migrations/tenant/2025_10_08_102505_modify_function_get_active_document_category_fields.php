<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<SQL

         DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_active_document_category_fields'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION get_active_document_category_fields(
                _tenant_id BIGINT DEFAULT NULL,
                p_id BIGINT DEFAULT NULL,
                p_document_category_id BIGINT DEFAULT NULL,
                p_action_type TEXT DEFAULT 'normal'
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                document_category_id BIGINT,
                document_field_name VARCHAR,
                description TEXT,
                file_path VARCHAR,
                isactive BOOLEAN,
                tenant_id BIGINT,
                created_by BIGINT,
                document_formats VARCHAR,
                max_upload_count INTEGER,
                listable BOOLEAN,
                created_at TIMESTAMP,
                updated_at TIMESTAMP,
                category_name VARCHAR,
                category_description TEXT,
                category_tag VARCHAR
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- If p_document_category_id is NULL, return all active fields (default behavior)
                IF p_document_category_id IS NULL THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT AS status,
                        'Active document category fields fetched successfully'::TEXT AS message,
                        dcf.id,
                        dcf.document_category_id,
                        dcf.document_field_name,
                        dcf.description,
                        dcf.file_path,
                        dcf.isactive,
                        dcf.tenant_id,
                        dcf.created_by,
                        dcf.document_formats,
                        dcf.max_upload_count,
                        dcf.listable,
                        dcf.created_at,
                        dcf.updated_at,
                        dc.category_name,
                        dc.description AS category_description,
                        dc.category_tag
                    FROM document_category_field dcf
                    LEFT JOIN document_category dc ON dcf.document_category_id = dc.id
                    WHERE (_tenant_id IS NULL OR dcf.tenant_id = _tenant_id)
                    AND (p_id IS NULL OR dcf.id = p_id)
                    AND dcf.isactive = TRUE;
                    RETURN;
                END IF;

                IF p_action_type = 'booking' THEN
                    -- Booking: Return same rows but keep IDs empty
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT AS status,
                        'Booking type - fields fetched without IDs'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::BIGINT AS document_category_id,
                        dcf.document_field_name,
                        dcf.description,
                        dcf.file_path,
                        dcf.isactive,
                        dcf.tenant_id,
                        dcf.created_by,
                        dcf.document_formats,
                        dcf.max_upload_count,
                        dcf.listable,
                        dcf.created_at,
                        dcf.updated_at,
                        dc.category_name,
                        dc.description AS category_description,
                        dc.category_tag
                    FROM document_category_field dcf
                    LEFT JOIN document_category dc ON dcf.document_category_id = dc.id
                    WHERE (_tenant_id IS NULL OR dcf.tenant_id = _tenant_id)
                    AND dcf.document_category_id = p_document_category_id
                    AND dcf.isactive = TRUE;

                ELSE
                    -- Normal behavior (default)
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT AS status,
                        'Active document category fields fetched successfully'::TEXT AS message,
                        dcf.id,
                        dcf.document_category_id,
                        dcf.document_field_name,
                        dcf.description,
                        dcf.file_path,
                        dcf.isactive,
                        dcf.tenant_id,
                        dcf.created_by,
                        dcf.document_formats,
                        dcf.max_upload_count,
                        dcf.listable,
                        dcf.created_at,
                        dcf.updated_at,
                        dc.category_name,
                        dc.description AS category_description,
                        dc.category_tag
                    FROM document_category_field dcf
                    LEFT JOIN document_category dc ON dcf.document_category_id = dc.id
                    WHERE (_tenant_id IS NULL OR dcf.tenant_id = _tenant_id)
                    AND dcf.document_category_id = p_document_category_id
                    AND (p_id IS NULL OR dcf.id = p_id)
                    AND dcf.isactive = TRUE;
                END IF;
            END;
            $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_active_document_category_fields(BIGINT, BIGINT, BIGINT, TEXT);');
    }
};
