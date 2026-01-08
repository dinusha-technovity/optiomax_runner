<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** 
     * Run the migrations.
     */ 
    public function up(): void
    {  
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_all_asset_audit_variable_types(
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_variable_type_id INT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                name TEXT,
                description TEXT
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- If both parameters are NULL, return all records
                IF p_tenant_id IS NULL AND p_variable_type_id IS NULL THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT AS status,
                        'All asset audit variable types fetched successfully'::TEXT AS message,
                        a.id,
                        a.name::TEXT,
                        a.description::TEXT
                    FROM asset_audit_variable_type a
                    WHERE a.deleted_at IS NULL
                    AND a.is_active = TRUE;
                    RETURN;
                END IF;

                -- Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::TEXT AS description;
                    RETURN;
                END IF;

                -- Validate variable type ID (optional)
                IF p_variable_type_id IS NOT NULL AND p_variable_type_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid asset audit variable type ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::TEXT AS description;
                    RETURN;
                END IF;

                -- Return matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Asset audit variable types fetched successfully'::TEXT AS message,
                    a.id,
                    a.name::TEXT,
                    a.description::TEXT
                FROM
                    asset_audit_variable_type a
                WHERE (p_variable_type_id IS NULL OR a.id = p_variable_type_id)
                AND a.tenant_id = p_tenant_id
                AND a.deleted_at IS NULL
                AND a.is_active = TRUE;

            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_all_asset_audit_variable_types');
    }
};
