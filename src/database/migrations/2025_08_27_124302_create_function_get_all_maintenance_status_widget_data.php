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
        CREATE OR REPLACE FUNCTION get_all_maintenance_status_widget_data(
            p_tenant_id BIGINT,
            p_type TEXT DEFAULT 'web'
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            maintenance_data JSONB
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

            -- Get the start year from the oldest work order ticket record
            SELECT EXTRACT(YEAR FROM MIN(created_at))::INT
            INTO v_start_year
            FROM work_order_tickets
            WHERE tenant_id = p_tenant_id 
            AND deleted_at IS NULL;

            v_current_year := EXTRACT(YEAR FROM CURRENT_DATE)::INT;

            RETURN QUERY
            WITH available_years AS (
                SELECT DISTINCT EXTRACT(YEAR FROM created_at)::INT AS year
                FROM work_order_tickets
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
                    COUNT(*) as total_maintenances,
                    -- Calculate completion rate (all closed tickets)
                    ROUND(
                        (COUNT(CASE WHEN is_closed = true THEN 1 END)::FLOAT * 100 / 
                        NULLIF(COUNT(*), 0))::NUMERIC,
                    2) as completion_rate,
                    -- Calculate action taken rate
                    ROUND(
                        (COUNT(CASE WHEN is_get_action = true AND is_closed = true THEN 1 END)::FLOAT * 100 / 
                        NULLIF(COUNT(*), 0))::NUMERIC,
                    2) as action_taken_rate,
                    -- Calculate closure rate
                    ROUND(
                        (COUNT(CASE WHEN is_closed = true THEN 1 END)::FLOAT * 100 / 
                        NULLIF(COUNT(*), 0))::NUMERIC,
                    2) as closure_rate
                FROM work_order_tickets
                WHERE tenant_id = p_tenant_id 
                AND deleted_at IS NULL
                AND EXTRACT(YEAR FROM created_at) BETWEEN v_start_year AND v_current_year
                GROUP BY EXTRACT(YEAR FROM created_at)
            ),
            monthly_stats AS (
                SELECT 
                    EXTRACT(YEAR FROM created_at) AS year,
                    EXTRACT(MONTH FROM created_at) AS month,
                    COUNT(CASE WHEN NOT is_get_action AND NOT is_closed THEN 1 END) as open_pending,
                    COUNT(CASE WHEN NOT is_get_action AND is_closed THEN 1 END) as closed_no_action,
                    COUNT(CASE WHEN is_get_action AND is_closed THEN 1 END) as closed_with_action
                FROM work_order_tickets
                WHERE tenant_id = p_tenant_id 
                AND deleted_at IS NULL
                AND EXTRACT(YEAR FROM created_at) BETWEEN v_start_year AND v_current_year
                GROUP BY EXTRACT(YEAR FROM created_at), EXTRACT(MONTH FROM created_at)
            ),
            yearly_totals AS (
                SELECT 
                    EXTRACT(YEAR FROM created_at) AS year,
                    COUNT(CASE WHEN NOT is_get_action AND NOT is_closed THEN 1 END) as open_pending,
                    COUNT(CASE WHEN NOT is_get_action AND is_closed THEN 1 END) as closed_no_action,
                    COUNT(CASE WHEN is_get_action AND is_closed THEN 1 END) as closed_with_action
                FROM work_order_tickets
                WHERE tenant_id = p_tenant_id 
                AND deleted_at IS NULL
                AND EXTRACT(YEAR FROM created_at) BETWEEN v_start_year AND v_current_year
                GROUP BY EXTRACT(YEAR FROM created_at)
            )
            SELECT 
                CASE WHEN p_type = 'web' THEN 'success' ELSE 'failed' END,
                CASE WHEN p_type = 'web' THEN 'maintenance status data fetch success' ELSE 'your request type is invalid' END,
                CASE WHEN p_type = 'web' THEN 
                    jsonb_build_object(
                        'years', (SELECT jsonb_agg(y.year::TEXT) FROM years y),
                        'stats', (
                            SELECT jsonb_object_agg(
                                y.year::TEXT,
                                jsonb_build_object(
                                    'totalMaintenances', COALESCE(ys.total_maintenances, 0),
                                    'completionRate', COALESCE(ys.completion_rate, 0),
                                    'actionTakenRate', COALESCE(ys.action_taken_rate, 0),
                                    'closureRate', COALESCE(ys.closure_rate, 0)
                                )
                            )
                            FROM years y
                            LEFT JOIN yearly_stats ys ON ys.year = y.year
                        ),
                        'monthly', (
                            SELECT jsonb_object_agg(
                                y.year::TEXT,
                                jsonb_build_object(
                                    'openPending', (
                                        SELECT jsonb_agg(COALESCE(ms.open_pending, 0))
                                        FROM generate_series(1,12) m 
                                        LEFT JOIN monthly_stats ms ON ms.year = y.year AND ms.month = m
                                    ),
                                    'closedNoAction', (
                                        SELECT jsonb_agg(COALESCE(ms.closed_no_action, 0))
                                        FROM generate_series(1,12) m 
                                        LEFT JOIN monthly_stats ms ON ms.year = y.year AND ms.month = m
                                    ),
                                    'closedWithAction', (
                                        SELECT jsonb_agg(COALESCE(ms.closed_with_action, 0))
                                        FROM generate_series(1,12) m 
                                        LEFT JOIN monthly_stats ms ON ms.year = y.year AND ms.month = m
                                    )
                                )
                            )
                            FROM years y
                        ),
                        'yearly', jsonb_build_object(
                            'openPending', (
                                SELECT jsonb_agg(COALESCE(yt.open_pending, 0) ORDER BY y.year)
                                FROM years y
                                LEFT JOIN yearly_totals yt ON yt.year = y.year
                            ),
                            'closedNoAction', (
                                SELECT jsonb_agg(COALESCE(yt.closed_no_action, 0) ORDER BY y.year)
                                FROM years y
                                LEFT JOIN yearly_totals yt ON yt.year = y.year
                            ),
                            'closedWithAction', (
                                SELECT jsonb_agg(COALESCE(yt.closed_with_action, 0) ORDER BY y.year)
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
        DB::unprepared("DROP FUNCTION IF EXISTS get_all_maintenance_status_widget_data(BIGINT, TEXT);");
    }
};
