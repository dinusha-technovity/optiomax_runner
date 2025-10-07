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
            CREATE OR REPLACE FUNCTION get_depreciation_method_types_list(
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_depreciation_method_id INT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                name TEXT
            ) 
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF p_tenant_id IS NULL AND p_depreciation_method_id IS NULL THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT AS status,
                        'All depreciation method types list fetched successfully'::TEXT AS message,
                        dmt.id,
                        dmt.name::TEXT
                    FROM depreciation_method_table dmt
                    WHERE dmt.deleted_at IS NULL
                    AND dmt.isactive = TRUE;
                    RETURN;
                END IF;

                IF p_tenant_id IS NULL OR p_tenant_id < 0 THEN
                    RETURN QUERY 
                    SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name;
                    RETURN;
                END IF;

                IF p_depreciation_method_id IS NOT NULL AND p_depreciation_method_id < 0 THEN
                    RETURN QUERY 
                    SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid depreciation method ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name;
                    RETURN;
                END IF;

                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Depreciation method types fetched successfully'::TEXT AS message,
                    dmt.id,
                    dmt.name::TEXT
                FROM depreciation_method_table dmt
                WHERE (p_depreciation_method_id IS NULL OR dmt.id = p_depreciation_method_id)
                AND dmt.tenant_id = p_tenant_id
                AND dmt.deleted_at IS NULL
                AND dmt.isactive = TRUE;
            END; 
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_depreciation_method_types_list( BIGINT, BIGINT);');
    }
};
