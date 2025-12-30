<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** 
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_auth_related_mobile_dashboard_layout_widgets(
                p_tenant_id BIGINT,
                p_user_id BIGINT,
                p_layout_id BIGINT DEFAULT NULL
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
                widget_id BIGINT,
                widget_type TEXT,
                user_id BIGINT,
                tenant_id BIGINT,
                is_active BOOLEAN,
                created_at TIMESTAMP,
                updated_at TIMESTAMP,
                deleted_at TIMESTAMP,
                min_width DOUBLE PRECISION,
                min_height DOUBLE PRECISION
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                widget_count INT;
            BEGIN
                -- Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT, 
                        'Invalid tenant ID'::TEXT,
                        NULL::BIGINT, NULL::DOUBLE PRECISION, NULL::DOUBLE PRECISION,
                        NULL::DOUBLE PRECISION, NULL::DOUBLE PRECISION,
                        NULL::TEXT, NULL::BIGINT, NULL::TEXT,
                        NULL::BIGINT, NULL::BIGINT,
                        NULL::BOOLEAN, NULL::TIMESTAMP, NULL::TIMESTAMP, NULL::TIMESTAMP,  
                        NULL::DOUBLE PRECISION,
                        NULL::DOUBLE PRECISION;
                    RETURN;
                END IF;

                -- Count matching widgets
                SELECT COUNT(*) INTO widget_count
                FROM mobile_app_layout_widgets alw
                WHERE alw.tenant_id = p_tenant_id
                AND (p_layout_id IS NULL OR p_layout_id = 0 OR alw.id = p_layout_id)
                AND (p_user_id IS NULL OR alw.user_id = p_user_id)
                AND alw.deleted_at IS NULL;

                -- If no widgets found
                IF widget_count = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'No matching dashboard widgets found'::TEXT,
                        NULL::BIGINT, NULL::DOUBLE PRECISION, NULL::DOUBLE PRECISION,
                        NULL::DOUBLE PRECISION, NULL::DOUBLE PRECISION,
                        NULL::TEXT, NULL::BIGINT, NULL::TEXT,
                        NULL::BIGINT, NULL::BIGINT,
                        NULL::BOOLEAN, NULL::TIMESTAMP, NULL::TIMESTAMP, NULL::TIMESTAMP, NULL::DOUBLE PRECISION, NULL::DOUBLE PRECISION;
                    RETURN;
                END IF;

                -- Otherwise return results
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT,
                    'Dashboard widgets retrieved successfully'::TEXT,
                    alw.id,
                    alw.x,
                    alw.y,
                    alw.w,
                    alw.h,
                    alw.style::TEXT,       -- cast varchar → text
                    alw.widget_id,
                    alw.widget_type::TEXT, -- cast varchar → text
                    alw.user_id,
                    alw.tenant_id,
                    alw.is_active,
                    alw.created_at,
                    alw.updated_at,
                    alw.deleted_at,
                    (aw.design_obj->>'width')::DOUBLE PRECISION as min_width,
                    (aw.design_obj->>'height')::DOUBLE PRECISION as min_height
                FROM mobile_app_layout_widgets alw
                LEFT JOIN app_widgets aw ON alw.widget_id = aw.id
                WHERE alw.tenant_id = p_tenant_id
                AND (p_layout_id IS NULL OR p_layout_id = 0 OR alw.id = p_layout_id)
                AND (p_user_id IS NULL OR alw.user_id = p_user_id)
                AND alw.deleted_at IS NULL
                ORDER BY alw.id;

                -- Log retrieval (non-breaking)
                BEGIN
                    PERFORM log_activity(
                        'view_dashboard_widgets',
                        format('Retrieved dashboard widgets for tenant %s, user %s', p_tenant_id, p_user_id),
                        'mobile_app_layout_widgets',
                        p_layout_id,
                        'user',
                        p_user_id,
                        NULL,
                        p_tenant_id
                    );
                EXCEPTION WHEN OTHERS THEN
                    NULL;
                END;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_auth_related_mobile_dashboard_layout_widgets(BIGINT, BIGINT, BIGINT);");
    }
};