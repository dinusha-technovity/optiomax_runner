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
            DROP FUNCTION IF EXISTS get_procurement_staff_details(
                BIGINT, BIGINT
            );

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
                asset_category_id BIGINT,
                asset_category_name TEXT,
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
                    ps.asset_category AS asset_category_id,
                    ac.name::TEXT AS asset_category_name, -- Cast to TEXT
                    ps.created_at,
                    ps.updated_at
                FROM 
                    procurement_staff ps
                INNER JOIN 
                    users u ON ps.user_id = u.id
                INNER JOIN 
                    asset_categories ac ON ps.asset_category = ac.id
                WHERE
                    ps.tenant_id = p_tenant_id
                    AND (ps.id = p_staff_id OR p_staff_id IS NULL)
                    AND ps.deleted_at IS NULL
                    AND ps.isactive = TRUE;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_procurement_staff_details');
    }
};
