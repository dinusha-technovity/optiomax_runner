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
            DROP FUNCTION IF EXISTS get_active_document_category_fields(
                _tenant_id BIGINT,
                p_id BIGINT,
                p_document_category_id BIGINT
            );

            CREATE OR REPLACE FUNCTION get_active_document_category_fields(
                _tenant_id BIGINT DEFAULT NULL,
                p_id BIGINT DEFAULT NULL,
                p_document_category_id BIGINT DEFAULT NULL
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
                WHERE (p_id IS NULL OR dcf.id = p_id)
                AND (_tenant_id IS NULL OR dcf.tenant_id = _tenant_id)
                AND (p_document_category_id IS NULL OR dcf.document_category_id = p_document_category_id)
                AND dcf.isactive = true;
             
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_active_document_category_fields(BIGINT, BIGINT, BIGINT);');
    }
};
