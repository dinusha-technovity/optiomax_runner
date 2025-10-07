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
        CREATE OR REPLACE FUNCTION get_item_types(
        IN p_tenant_id BIGINT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            id BIGINT,
            name TEXT,
            description TEXT,
            is_active BOOLEAN
        )
        LANGUAGE plpgsql
        AS $$
        BEGIN
            -- Case 1: If no tenant_id is provided, return all item types
            IF p_tenant_id IS NULL THEN
                RETURN QUERY
                SELECT 
                    'SUCCESS'::TEXT AS status,
                    'All item types retrieved successfully'::TEXT AS message,
                    it.id,
                    it.name::TEXT,  
                    it.description::TEXT,  
                    it.is_active
                FROM item_types it;
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
                    NULL::TEXT AS description,
                    NULL::BOOLEAN AS is_active;
                RETURN;
            END IF;

            -- Case 3: Retrieve item types for a specific tenant
            RETURN QUERY
            SELECT 
                'SUCCESS'::TEXT AS status,
                'Item types retrieved successfully for the given tenant'::TEXT AS message,
                it.id,
                it.name::TEXT,  
                it.description::TEXT,  
                it.is_active
            FROM item_types it
            WHERE it.tenant_id = p_tenant_id;

        END;
        $$;

        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_item_types(BIGINT);");
    }
};
