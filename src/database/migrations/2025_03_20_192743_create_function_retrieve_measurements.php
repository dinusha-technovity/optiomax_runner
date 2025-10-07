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

        CREATE OR REPLACE FUNCTION get_measurements(
            IN p_tenant_id BIGINT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            id BIGINT,
            name TEXT,
            symbol TEXT,
            measurement_type TEXT,
            is_active BOOLEAN
        )
        LANGUAGE plpgsql
        AS $$
        BEGIN
            -- Case 1: If no tenant_id is provided, return all measurements
            IF p_tenant_id IS NULL THEN
                RETURN QUERY
                SELECT 
                    'SUCCESS'::TEXT AS status,
                    'All measurements retrieved successfully'::TEXT AS message,
                    m.id,
                    m.name::TEXT,  
                    m.symbol::TEXT,  
                    m.measurement_type::TEXT,
                    m.is_active
                FROM measurements m;
                RETURN;
            END IF;

            -- Case 2: Validate tenant ID
            IF p_tenant_id < 0 THEN
                RETURN QUERY 
                SELECT 
                    'FAILURE'::TEXT AS status,
                    'Invalid tenant ID provided'::TEXT AS message,
                    NULL::BIGINT AS id,
                    NULL::TEXT AS name,
                    NULL::TEXT AS symbol,
                    NULL::TEXT AS measurement_type,
                    NULL::BOOLEAN AS is_active;
                RETURN;
            END IF;

            -- Case 3: Retrieve measurements for a specific tenant
            RETURN QUERY
            SELECT 
                'SUCCESS'::TEXT AS status,
                'Measurements retrieved successfully for the given tenant'::TEXT AS message,
                m.id,
                m.name::TEXT,  
                m.symbol::TEXT,  
                m.measurement_type::TEXT,
                m.is_active
            FROM measurements m
            WHERE m.tenant_id = p_tenant_id;

        END;
        $$;


        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_measurements(BIGINT);");
    }
};
