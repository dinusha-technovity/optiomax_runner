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
            CREATE OR REPLACE FUNCTION get_active_document_categories(
                    _tenant_id BIGINT DEFAULT NULL,
                    p_id BIGINT DEFAULT NULL
                )
                RETURNS TABLE (
                    status TEXT,
                    message TEXT,
                    id BIGINT,
                    category_name VARCHAR,
                    description TEXT,
                    category_tag VARCHAR,
                    isactive BOOLEAN,
                    tenant_id BIGINT,
                    created_by BIGINT,
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP
                )
                LANGUAGE plpgsql
                AS $$
                BEGIN
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT AS status,
                        'Active document categories fetched successfully'::TEXT AS message,
                        dc.id,
                        dc.category_name,
                        dc.description,
                        dc.category_tag,
                        dc.isactive,
                        dc.tenant_id,
                        dc.created_by,
                        dc.created_at,
                        dc.updated_at
                    FROM document_category dc
                    WHERE (p_id IS NULL OR dc.id = p_id)
                    AND (_tenant_id IS NULL OR dc.tenant_id = _tenant_id)
                    AND (dc.isactive = true OR dc.isactive IS NULL);
                END;
            $$;
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_active_document_categories(BIGINT, BIGINT);');
    }
};
