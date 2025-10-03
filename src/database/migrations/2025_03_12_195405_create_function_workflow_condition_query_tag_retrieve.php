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
            CREATE OR REPLACE FUNCTION get_all_workflow_condition_system_variable(
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_system_variable_id INT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                name TEXT,
                value TEXT
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- If both parameters are NULL, return all records
                IF p_tenant_id IS NULL AND p_system_variable_id IS NULL THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT AS status,
                        'All system variable fetched successfully'::TEXT AS message,
                        wcsv.id,
                        wcsv.name::TEXT,
                        wcsv.value::TEXT
                    FROM workflow_condition_system_variable wcsv
                    WHERE wcsv.deleted_at IS NULL
                    AND wcsv.isactive = TRUE;
                    RETURN;
                END IF;
        
                -- Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::TEXT AS value;
                    RETURN;
                END IF;
        
                -- Validate system variable ID (optional)
                IF p_system_variable_id IS NOT NULL AND p_system_variable_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid workflow condition system variable ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::TEXT AS value;
                    RETURN;
                END IF;
        
                -- Return matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'System variable fetched successfully'::TEXT AS message,
                    wcsv.id,
                    wcsv.name::TEXT,
                    wcsv.value::TEXT
                FROM
                    workflow_condition_system_variable wcsv
                WHERE (p_system_variable_id IS NULL OR wcsv.id = p_system_variable_id)
                AND wcsv.tenant_id = p_tenant_id
                AND wcsv.deleted_at IS NULL
                AND wcsv.isactive = TRUE;
                RETURN;
            END;
            $$;
        SQL);    
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_all_workflow_condition_system_variable');
    }
};