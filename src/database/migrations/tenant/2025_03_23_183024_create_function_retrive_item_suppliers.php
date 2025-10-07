<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
        CREATE OR REPLACE FUNCTION get_item_suppliers(
            IN p_tenant_id BIGINT DEFAULT NULL
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
            -- Case 1: If no tenant_id is provided, return all suppliers
            IF p_tenant_id IS NULL THEN
                RETURN QUERY
                SELECT 
                    'SUCCESS'::TEXT AS status,
                    'All suppliers retrieved successfully'::TEXT AS message,
                    s.id,
                    s.name::TEXT,  
                    s.organization_name::TEXT AS description
                FROM item_suppliers s;
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
                    NULL::TEXT AS description;
                RETURN;
            END IF;

            -- Case 3: Retrieve suppliers for a specific tenant
            RETURN QUERY
            SELECT 
                'SUCCESS'::TEXT AS status,
                'Suppliers retrieved successfully for the given tenant'::TEXT AS message,
                s.id,
                s.name::TEXT,  
                s.organization_name::TEXT AS description
            FROM item_suppliers s
            WHERE s.tenant_id = p_tenant_id;

        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_item_suppliers");
    }
};
