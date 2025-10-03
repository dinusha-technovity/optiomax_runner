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
            CREATE OR REPLACE FUNCTION get_portal_dashboard_layout_widgets(
                p_layout_id INT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                x DOUBLE PRECISION,
                y DOUBLE PRECISION,
                w DOUBLE PRECISION,
                h DOUBLE PRECISION,
                style TEXT,
                status_flag BOOLEAN,
                deleted_at TIMESTAMP,
                created_at TIMESTAMP,
                updated_at TIMESTAMP,
                widget_id BIGINT,
                widget_type TEXT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                widget_count INT;
            BEGIN
                -- Validate layout ID (optional)
                IF p_layout_id IS NOT NULL AND p_layout_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid layout ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::DOUBLE PRECISION AS x,
                        NULL::DOUBLE PRECISION AS y,
                        NULL::DOUBLE PRECISION AS w,
                        NULL::DOUBLE PRECISION AS h,
                        NULL::TEXT AS style,
                        NULL::BOOLEAN AS status_flag,
                        NULL::TIMESTAMP AS deleted_at,
                        NULL::TIMESTAMP AS created_at,
                        NULL::TIMESTAMP AS updated_at,
                        NULL::BIGINT AS widget_id,
                        NULL::TEXT AS widget_type;
                    RETURN;
                END IF;

                -- Check if any matching records exist
                SELECT COUNT(*) INTO widget_count
                FROM portal_layout_widgets alw
                WHERE 
                    (p_layout_id IS NULL OR alw.id = p_layout_id OR p_layout_id = 0);

                IF widget_count = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'No matching widgets found'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::DOUBLE PRECISION AS x,
                        NULL::DOUBLE PRECISION AS y,
                        NULL::DOUBLE PRECISION AS w,
                        NULL::DOUBLE PRECISION AS h,
                        NULL::TEXT AS style,
                        NULL::BOOLEAN AS status_flag,
                        NULL::TIMESTAMP AS deleted_at,
                        NULL::TIMESTAMP AS created_at,
                        NULL::TIMESTAMP AS updated_at,
                        NULL::BIGINT AS widget_id,
                        NULL::TEXT AS widget_type;
                    RETURN;
                END IF;

                -- Return all matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Widgets fetched successfully'::TEXT AS message,
                    alw.id,
                    alw.x,
                    alw.y,
                    alw.w,
                    alw.h,
                    alw.style,
                    alw.status AS status_flag,
                    alw.deleted_at,
                    alw.created_at,
                    alw.updated_at,
                    alw.widget_id,
                    alw.widget_type::TEXT -- Explicitly cast widget_type to TEXT
                FROM
                    portal_layout_widgets alw
                WHERE
                    (p_layout_id IS NULL OR alw.id = p_layout_id OR p_layout_id = 0)
                ORDER BY alw.id;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_portal_dashboard_layout_widgets');
    }
};
