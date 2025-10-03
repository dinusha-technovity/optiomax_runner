<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_procurement_staff_details(
                p_tenant_id BIGINT,
                p_staff_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                user_id BIGINT,
                user_name TEXT,
                asset_type_id BIGINT,
                asset_type_name TEXT,
                created_at TIMESTAMP,
                updated_at TIMESTAMP
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, 
                        NULL::TIMESTAMP, NULL::TIMESTAMP;
                    RETURN;
                END IF;
            
                -- Validate staff ID (if provided)
                IF p_staff_id IS NOT NULL AND p_staff_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid staff ID provided'::TEXT AS message,
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, 
                        NULL::TIMESTAMP, NULL::TIMESTAMP;
                    RETURN;
                END IF;
            
                -- Return the matching records
                RETURN QUERY
                SELECT 
                    'SUCCESS'::TEXT AS status,
                    'Procurement staff details retrieved successfully'::TEXT AS message,
                    ps.id, 
                    ps.user_id,
                    u.name::TEXT AS user_name, -- Cast to TEXT
                    ps.asset_type_id,
                    at.name::TEXT AS asset_type_name, -- Cast to TEXT
                    ps.created_at,
                    ps.updated_at
                FROM 
                    procurement_staff ps
                INNER JOIN 
                    users u ON ps.user_id = u.id
                INNER JOIN 
                    assets_types at ON ps.asset_type_id = at.id
                WHERE
                    ps.tenant_id = p_tenant_id
                    AND (ps.id = p_staff_id OR p_staff_id IS NULL)
                    AND ps.deleted_at IS NULL
                    AND ps.isactive = TRUE;
            END;
            $$;
        SQL);       
    
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_procurement_staff_details');
    }
};