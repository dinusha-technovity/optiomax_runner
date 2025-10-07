<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{ 
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_document_category_fields(
                p_tenant_id BIGINT DEFAULT NULL,
                p_category_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                field_id BIGINT,
                field_name TEXT,
                field_description TEXT,
                document_formats TEXT,
                max_upload_count INTEGER
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF p_tenant_id IS NOT NULL AND p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT, 
                        'Invalid tenant ID provided'::TEXT,
                        NULL::BIGINT, 
                        NULL::TEXT, 
                        NULL::TEXT, 
                        NULL::TEXT, 
                        NULL::INTEGER;
                    RETURN;
                END IF;

                IF NOT EXISTS (
                    SELECT 1 
                    FROM document_category_field f
                    WHERE (p_category_id IS NULL OR f.document_category_id = p_category_id)
                    AND (p_tenant_id IS NULL OR f.tenant_id = p_tenant_id)
                ) THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT, 
                        'No matching fields found'::TEXT,
                        NULL::BIGINT, 
                        NULL::TEXT, 
                        NULL::TEXT, 
                        NULL::TEXT, 
                        NULL::INTEGER;
                    RETURN;
                END IF;

                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Fields fetched successfully'::TEXT AS message,
                    f.id AS field_id,
                    f.document_field_name::text AS field_name,          -- Cast as text
                    f.description,                                      -- Already text
                    f.document_formats::text AS document_formats,       -- Cast as text
                    f.max_upload_count
                FROM document_category_field f
                WHERE (p_category_id IS NULL OR f.document_category_id = p_category_id)
                AND (p_tenant_id IS NULL OR f.tenant_id = p_tenant_id);
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_document_category_fields(BIGINT, BIGINT);");
    }
};