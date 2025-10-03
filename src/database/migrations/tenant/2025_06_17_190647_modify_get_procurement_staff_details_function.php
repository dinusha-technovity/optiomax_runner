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
        DROP FUNCTION IF EXISTS get_procurement_staff_details(BIGINT, BIGINT);

        CREATE OR REPLACE FUNCTION get_procurement_staff_details(
            p_tenant_id BIGINT,
            p_user_id BIGINT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            user_id BIGINT,
            user_name TEXT,
            profile_image TEXT,
            staff_details JSONB
        )
        LANGUAGE plpgsql
        AS $$
        BEGIN
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE',
                    'Invalid tenant ID provided',
                    NULL,
                    NULL,
                    NULL,
                    NULL;
                RETURN;
            END IF;

            IF p_user_id IS NOT NULL AND p_user_id <= 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE',
                    'Invalid user ID provided',
                    NULL,
                    NULL,
                    NULL,
                    NULL;
                RETURN;
            END IF;

            RETURN QUERY
            SELECT
                'SUCCESS',
                'Procurement staff list retrieved successfully',
                u.id,
                u.name::TEXT,
                u.profile_image::TEXT,
                jsonb_agg(
                    jsonb_build_object(
                        'id', ps.id,
                        'asset_category_id', ps.asset_category,
                        'asset_category_name', ac.name,
                        'created_at', ps.created_at,
                        'updated_at', ps.updated_at
                    )
                )
            FROM procurement_staff ps
            INNER JOIN users u ON u.id = ps.user_id
            INNER JOIN asset_categories ac ON ac.id = ps.asset_category
            WHERE ps.tenant_id = p_tenant_id
            AND ps.deleted_at IS NULL
            AND ps.isactive = TRUE
            AND (ps.user_id = p_user_id OR p_user_id IS NULL)
            GROUP BY u.id, u.name, u.profile_image;
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