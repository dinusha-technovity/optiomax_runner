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
    public function up()
    {
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_currencies(
            IN p_tenant_id BIGINT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            id BIGINT,
            code TEXT,
            name TEXT,
            symbol TEXT,
            exchange_rate_to_usd DECIMAL(15,6),
            is_active BOOLEAN
        )
        LANGUAGE plpgsql
        AS $$
        BEGIN
            -- Case 1: If no tenant_id is provided, return all currencies
            IF p_tenant_id IS NULL THEN
                RETURN QUERY
                SELECT 
                    'SUCCESS'::TEXT AS status,
                    'All currencies retrieved successfully'::TEXT AS message,
                    c.id,
                    c.code::TEXT,  
                    c.name::TEXT,  
                    c.symbol::TEXT, 
                    c.exchange_rate_to_usd,
                    c.is_active
                FROM currencies c;
                RETURN;
            END IF;

            -- Case 2: Validate tenant ID
            IF p_tenant_id < 0 THEN
                RETURN QUERY 
                SELECT 
                    'FAILURE'::TEXT AS status,
                    'Invalid tenant ID provided'::TEXT AS message,
                    NULL::BIGINT AS id,
                    NULL::TEXT AS code,
                    NULL::TEXT AS name,
                    NULL::TEXT AS symbol,
                    NULL::DECIMAL(15,6) AS exchange_rate_to_usd,
                    NULL::BOOLEAN AS is_active;
                RETURN;
            END IF;

            -- Case 3: Retrieve currencies for a specific tenant
            RETURN QUERY
            SELECT 
                'SUCCESS'::TEXT AS status,
                'Currencies retrieved successfully for the given tenant'::TEXT AS message,
                c.id,
                c.code::TEXT,  
                c.name::TEXT,  
                c.symbol::TEXT, 
                c.exchange_rate_to_usd,
                c.is_active
            FROM currencies c
            WHERE c.tenant_id = p_tenant_id;

        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_currencies(BIGINT);");
    }
};
