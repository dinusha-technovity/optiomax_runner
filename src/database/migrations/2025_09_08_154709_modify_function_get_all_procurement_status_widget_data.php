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
        CREATE OR REPLACE FUNCTION get_all_procurement_status_widget_data(
            p_tenant_id BIGINT,
            p_type TEXT DEFAULT 'web'
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            procurement_data JSONB
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_current_year INT;
            v_start_year INT;
        BEGIN
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RAISE EXCEPTION 'Invalid tenant ID';
            END IF;

            -- Get the start year from the oldest procurement record
            SELECT EXTRACT(YEAR FROM MIN(created_at))::INT
            INTO v_start_year
            FROM procurements
            WHERE tenant_id = p_tenant_id 
            AND deleted_at IS NULL;

            v_current_year := EXTRACT(YEAR FROM CURRENT_DATE)::INT;

            RETURN QUERY
            WITH available_years AS (
                SELECT DISTINCT EXTRACT(YEAR FROM created_at)::INT AS year
                FROM procurements
                WHERE tenant_id = p_tenant_id 
                AND deleted_at IS NULL
                AND created_at IS NOT NULL
                ORDER BY year
            ),
            years AS (
                SELECT year FROM available_years
            ),
            yearly_stats AS (
                SELECT 
                    EXTRACT(YEAR FROM created_at) AS year,
                    COUNT(*) as total_procurements,
                    -- Calculate percentage only for completed procurements (APPROVED + REJECT)
                    ROUND(
                        (COUNT(CASE WHEN procurement_status = 'APPROVED' THEN 1 END)::FLOAT * 100 / 
                        NULLIF(COUNT(CASE WHEN procurement_status IN ('APPROVED', 'REJECT') THEN 1 END), 0))::NUMERIC, 
                    2) as approval_percentage,
                    ROUND(
                        (COUNT(CASE WHEN procurement_status = 'REJECT' THEN 1 END)::FLOAT * 100 / 
                        NULLIF(COUNT(CASE WHEN procurement_status IN ('APPROVED', 'REJECT') THEN 1 END), 0))::NUMERIC, 
                    2) as rejection_percentage,
                    -- Add completed vs pending ratio
                    ROUND(
                        (COUNT(CASE WHEN procurement_status IN ('APPROVED', 'REJECT') THEN 1 END)::FLOAT * 100 / 
                        NULLIF(COUNT(*), 0))::NUMERIC,
                    2) as completion_rate
                FROM procurements
                WHERE tenant_id = p_tenant_id 
                AND deleted_at IS NULL
                AND EXTRACT(YEAR FROM created_at) BETWEEN v_start_year AND v_current_year
                GROUP BY EXTRACT(YEAR FROM created_at)
            ),
            monthly_stats AS (
                SELECT 
                    EXTRACT(YEAR FROM created_at) AS year,
                    EXTRACT(MONTH FROM created_at) AS month,
                    COUNT(CASE WHEN procurement_status = 'PENDING' THEN 1 END) as pending,
                    COUNT(CASE WHEN procurement_status = 'save' THEN 1 END) as save,
                    COUNT(CASE WHEN procurement_status = 'APPROVED' THEN 1 END) as approved,
                    COUNT(CASE WHEN procurement_status = 'REJECT' THEN 1 END) as reject,
                    COUNT(CASE WHEN procurement_status = 'COMPLETE' THEN 1 END) as complete
                FROM procurements
                WHERE tenant_id = p_tenant_id 
                AND deleted_at IS NULL
                AND EXTRACT(YEAR FROM created_at) BETWEEN v_start_year AND v_current_year
                GROUP BY EXTRACT(YEAR FROM created_at), EXTRACT(MONTH FROM created_at)
            ),
            yearly_totals AS (
                SELECT 
                    EXTRACT(YEAR FROM created_at) AS year,
                    COUNT(CASE WHEN procurement_status = 'PENDING' THEN 1 END) as pending,
                    COUNT(CASE WHEN procurement_status = 'save' THEN 1 END) as save,
                    COUNT(CASE WHEN procurement_status = 'APPROVED' THEN 1 END) as approved,
                    COUNT(CASE WHEN procurement_status = 'REJECT' THEN 1 END) as reject,
                    COUNT(CASE WHEN procurement_status = 'COMPLETE' THEN 1 END) as complete
                FROM procurements
                WHERE tenant_id = p_tenant_id 
                AND deleted_at IS NULL
                AND EXTRACT(YEAR FROM created_at) BETWEEN v_start_year AND v_current_year
                GROUP BY EXTRACT(YEAR FROM created_at)
            )
            SELECT 
                CASE WHEN p_type = 'web' THEN 'success' ELSE 'failed' END,
                CASE WHEN p_type = 'web' THEN 'procurement status data fetch success' ELSE 'your request type is invalid' END,
                CASE WHEN p_type = 'web' THEN 
                    jsonb_build_object(
                        'years', (SELECT jsonb_agg(y.year::TEXT) FROM years y),
                        'stats', (
                            SELECT jsonb_object_agg(
                                y.year::TEXT,
                                jsonb_build_object(
                                    'totalProcurements', COALESCE(ys.total_procurements, 0),
                                    'approvalPercentage', COALESCE(ys.approval_percentage, 0),
                                    'rejectionPercentage', COALESCE(ys.rejection_percentage, 0),
                                    'completionRate', COALESCE(ys.completion_rate, 0)
                                )
                            )
                            FROM years y
                            LEFT JOIN yearly_stats ys ON ys.year = y.year
                        ),
                        'monthly', (
                            SELECT jsonb_object_agg(
                                y.year::TEXT,
                                jsonb_build_object(
                                    'pending', (
                                        SELECT jsonb_agg(COALESCE(ms.pending, 0))
                                        FROM generate_series(1,12) m 
                                        LEFT JOIN monthly_stats ms ON ms.year = y.year AND ms.month = m
                                    ),
                                    'save', (
                                        SELECT jsonb_agg(COALESCE(ms.save, 0))
                                        FROM generate_series(1,12) m 
                                        LEFT JOIN monthly_stats ms ON ms.year = y.year AND ms.month = m
                                    ),
                                    'approved', (
                                        SELECT jsonb_agg(COALESCE(ms.approved, 0))
                                        FROM generate_series(1,12) m 
                                        LEFT JOIN monthly_stats ms ON ms.year = y.year AND ms.month = m
                                    ),
                                    'reject', (
                                        SELECT jsonb_agg(COALESCE(ms.reject, 0))
                                        FROM generate_series(1,12) m 
                                        LEFT JOIN monthly_stats ms ON ms.year = y.year AND ms.month = m
                                    ),
                                    'complete', (
                                        SELECT jsonb_agg(COALESCE(ms.complete, 0))
                                        FROM generate_series(1,12) m 
                                        LEFT JOIN monthly_stats ms ON ms.year = y.year AND ms.month = m
                                    )
                                )
                            )
                            FROM years y
                        ),
                        'yearly', jsonb_build_object(
                            'pending', (
                                SELECT jsonb_agg(COALESCE(yt.pending, 0) ORDER BY y.year)
                                FROM years y
                                LEFT JOIN yearly_totals yt ON yt.year = y.year
                            ),
                            'save', (
                                SELECT jsonb_agg(COALESCE(yt.save, 0) ORDER BY y.year)
                                FROM years y
                                LEFT JOIN yearly_totals yt ON yt.year = y.year
                            ),
                            'approved', (
                                SELECT jsonb_agg(COALESCE(yt.approved, 0) ORDER BY y.year)
                                FROM years y
                                LEFT JOIN yearly_totals yt ON yt.year = y.year
                            ),
                            'reject', (
                                SELECT jsonb_agg(COALESCE(yt.reject, 0) ORDER BY y.year)
                                FROM years y
                                LEFT JOIN yearly_totals yt ON yt.year = y.year
                            ),
                            'complete', (
                                SELECT jsonb_agg(COALESCE(yt.complete, 0) ORDER BY y.year)
                                FROM years y
                                LEFT JOIN yearly_totals yt ON yt.year = y.year
                            )
                        )
                    )
                ELSE NULL END;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_all_procurement_status_widget_data(BIGINT, TEXT);");
    }
};
