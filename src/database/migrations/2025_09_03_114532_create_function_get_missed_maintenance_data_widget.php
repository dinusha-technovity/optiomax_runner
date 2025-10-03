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
            CREATE OR REPLACE FUNCTION get_missed_maintenance_widget_data(
                p_tenant_id BIGINT,
                p_type TEXT DEFAULT 'web'
            )
            RETURNS TABLE(
                status TEXT,
                message TEXT,
                maintenance_data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                result JSONB;
            BEGIN
                -- IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                --     RAISE EXCEPTION 'Invalid tenant ID';
                -- END IF;
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'failed'::TEXT AS status,
                        'Invalid tenant ID'::TEXT AS message,
                        NULL::JSONB AS maintenance_data;
                    RETURN;
            END IF;

                -- IF p_type != 'web' THEN
                --     RETURN jsonb_build_object(
                --         'status', 'failed',
                --         'message', 'your request type is invalid'
                --     );
                -- END IF;

                IF p_type != 'web' THEN
                    RETURN QUERY SELECT 
                        'failed'::TEXT AS status,
                        'your request type is invalid'::TEXT AS message,
                        NULL::JSONB AS maintenance_data;
                    RETURN;
                END IF;

                -- getting data if work orders are closed without getting actions
                RETURN QUERY
                WITH missed_maintenance_tickets AS (
                    SELECT wot.*, wo.expected_duration, wo.expected_duration_unit, wo.priority
                    FROM work_order_tickets wot
                    LEFT JOIN work_orders wo ON wot.id = wo.work_order_ticket_id
                    WHERE wot.tenant_id = p_tenant_id
                    AND wot.is_get_action = false
                    AND wot.is_closed = true
                    AND wot.type IN ('maintenance_alert', 'incident_report')
                ),
                total_missed AS (
                    SELECT COUNT(*) AS cnt FROM missed_maintenance_tickets
                ),
                maintenance_alerts AS (
                    SELECT COUNT(*) AS cnt FROM missed_maintenance_tickets WHERE type = 'maintenance_alert'
                ),
                incident_reports AS (
                    SELECT COUNT(*) AS cnt FROM missed_maintenance_tickets WHERE type = 'incident_report'
                ),
                total_work_orders AS (
                    SELECT COUNT(*) AS cnt FROM work_order_tickets WHERE tenant_id = p_tenant_id
                ),
                missed_percentage AS (
                    SELECT 
                        CASE 
                            WHEN (SELECT cnt FROM total_work_orders) = 0 THEN 0 
                            ELSE ROUND(((SELECT cnt FROM total_missed)::numeric / (SELECT cnt FROM total_work_orders)) * 100, 2) 
                        END AS percentage
                ),
                category_breakdown AS (
                    SELECT ac.name, COUNT(mmt.id) AS count
                    FROM missed_maintenance_tickets mmt
                    JOIN asset_items ai ON mmt.asset_id = ai.id
                    JOIN assets a ON ai.asset_id = a.id
                    JOIN asset_categories ac ON a.category = ac.id
                    WHERE mmt.tenant_id = p_tenant_id
                    GROUP BY ac.id, ac.name
                    ORDER BY count DESC
                    LIMIT 5
                ),
                trend_data AS (
                    SELECT 
                        TO_CHAR(month_series.month, 'Mon') AS month,
                        COALESCE(monthly_counts.missed, 0) AS missed
                    FROM (
                        SELECT generate_series(
                            date_trunc('month', CURRENT_DATE - INTERVAL '5 months'),
                            date_trunc('month', CURRENT_DATE),
                            '1 month'::interval
                        ) AS month
                    ) month_series
                    LEFT JOIN (
                        SELECT 
                            date_trunc('month', created_at) AS month,
                            COUNT(*) AS missed
                        FROM missed_maintenance_tickets
                        WHERE created_at >= date_trunc('month', CURRENT_DATE - INTERVAL '5 months')
                        GROUP BY date_trunc('month', created_at)
                    ) monthly_counts ON month_series.month = monthly_counts.month
                    ORDER BY month_series.month
                ),
                avg_resolution_days AS (
                    SELECT 
                        COALESCE(AVG(
                            CASE 
                                WHEN expected_duration_unit = 'hours' THEN expected_duration / 24.0
                                ELSE expected_duration
                            END
                        ), 0) AS avg_days
                    FROM missed_maintenance_tickets
                    WHERE expected_duration IS NOT NULL AND expected_duration_unit IS NOT NULL
                ),
                critical_missed AS (
                    SELECT COUNT(*) AS cnt 
                    FROM missed_maintenance_tickets 
                    WHERE priority = '4'
                ),
                monthly_increase_rate AS (
                    SELECT 
                        CASE 
                            WHEN prev.cnt = 0 THEN 0 
                            ELSE ROUND(((curr.cnt - prev.cnt)::numeric / prev.cnt) * 100, 2) 
                        END AS rate
                    FROM (
                        SELECT COUNT(*) AS cnt 
                        FROM missed_maintenance_tickets 
                        WHERE date_trunc('month', created_at) = date_trunc('month', CURRENT_DATE)
                    ) curr,
                    (
                        SELECT COUNT(*) AS cnt 
                        FROM missed_maintenance_tickets 
                        WHERE date_trunc('month', created_at) = date_trunc('month', CURRENT_DATE - INTERVAL '1 month')
                    ) prev
                )
                SELECT
                
                CASE WHEN p_type = 'web' THEN 'success' ELSE 'failed' END,
                CASE WHEN p_type = 'web' THEN 'missed maintenance fetch success' ELSE 'your request type is invalid' END,
                CASE WHEN p_type = 'web' THEN 
                jsonb_build_object(
                    'summary', jsonb_build_object(
                        'totalMissedMaintenance', (SELECT cnt FROM total_missed),
                        'maintenanceAlerts', (SELECT cnt FROM maintenance_alerts),
                        'incidentReports', (SELECT cnt FROM incident_reports),
                        'missedPercentage', (SELECT percentage FROM missed_percentage),
                        'totalWorkOrders', (SELECT cnt FROM total_work_orders)
                    ),
                    'typeBreakdown', jsonb_build_object(
                        'maintenance_alert', (SELECT cnt FROM maintenance_alerts),
                        'incident_report', (SELECT cnt FROM incident_reports)
                    ),
                    'categoryBreakdown', (
                        SELECT COALESCE(jsonb_agg(jsonb_build_object('name', name, 'count', count)), '[]'::jsonb)
                        FROM category_breakdown
                    ),
                    'trendData', (
                        SELECT COALESCE(jsonb_agg(jsonb_build_object('month', month, 'missed', missed) ORDER BY month), '[]'::jsonb)
                        FROM trend_data
                    ),
                    'additionalMetrics', jsonb_build_object(
                        'avgResolutionDays', ROUND((SELECT avg_days FROM avg_resolution_days)::numeric, 1),
                        'criticalMissed', (SELECT cnt FROM critical_missed),
                        'monthlyIncreaseRate', (SELECT rate FROM monthly_increase_rate)
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
        DB::unprepared("DROP FUNCTION IF EXISTS get_missed_maintenance_widget_data(BIGINT, TEXT);");
    }
};
