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
        CREATE OR REPLACE FUNCTION get_asset_maintenance_incident_analytics(
            p_tenant_id BIGINT,
            p_type TEXT DEFAULT 'web'
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            incident_data JSONB
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_current_year INT;
            v_start_year INT;
            v_total_incidents INT;
            v_total_cost NUMERIC;
            v_avg_cost NUMERIC;
            v_forecast_amount NUMERIC;
        BEGIN
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RAISE EXCEPTION 'Invalid tenant ID';
            END IF;

            -- Get the start year from the oldest incident record
            SELECT EXTRACT(YEAR FROM MIN(start_time))::INT
            INTO v_start_year
            FROM asset_maintenance_incident_reports
            WHERE tenant_id = p_tenant_id 
            AND deleted_at IS NULL;

            v_current_year := EXTRACT(YEAR FROM CURRENT_DATE)::INT;

            -- Calculate totals for summary
            SELECT 
                COUNT(*),
                COALESCE(SUM(wo.est_cost), 0),
                COALESCE(AVG(wo.est_cost), 0)
            INTO v_total_incidents, v_total_cost, v_avg_cost
            FROM asset_maintenance_incident_reports amir
            LEFT JOIN work_order_tickets wot ON wot.asset_id = amir.asset 
                AND wot.type = 'incident_report' 
                AND wot.tenant_id = p_tenant_id
                AND wot.deleted_at IS NULL
            LEFT JOIN work_orders wo ON wo.work_order_ticket_id = wot.id
                AND wo.deleted_at IS NULL
            WHERE amir.tenant_id = p_tenant_id 
            AND amir.deleted_at IS NULL;

            -- Simple forecast calculation: average of last 2 years * 1.1 (10% growth)
            SELECT COALESCE(AVG(yearly_cost) * 1.1, 0)
            INTO v_forecast_amount
            FROM (
                SELECT 
                    EXTRACT(YEAR FROM amir.start_time) as year,
                    SUM(COALESCE(wo.est_cost, 0)) as yearly_cost
                FROM asset_maintenance_incident_reports amir
                LEFT JOIN work_order_tickets wot ON wot.asset_id = amir.asset 
                    AND wot.type = 'incident_report' 
                    AND wot.tenant_id = p_tenant_id
                    AND wot.deleted_at IS NULL
                LEFT JOIN work_orders wo ON wo.work_order_ticket_id = wot.id
                    AND wo.deleted_at IS NULL
                WHERE amir.tenant_id = p_tenant_id 
                AND amir.deleted_at IS NULL
                AND EXTRACT(YEAR FROM amir.start_time) >= v_current_year - 2
                GROUP BY EXTRACT(YEAR FROM amir.start_time)
                ORDER BY year DESC
                LIMIT 2
            ) recent_years;

            RETURN QUERY
            WITH high_incident_assets AS (
                SELECT 
                    ai.serial_number as asset_id,
                    a.name as asset_name,
                    COUNT(amir.id) as incident_count,
                    COALESCE(SUM(wo.est_cost), 0) as total_cost,
                    MAX(amir.start_time) as last_incident_date,
                    prio.value as severity_level,
                    -- Convert total downtime to HH:MM:SS format
                    COALESCE(
                        TO_CHAR(
                            EXTRACT(EPOCH FROM SUM(amir.downtime_duration))::INT / 3600, 
                            'FM00'
                        ) || ':' ||
                        TO_CHAR(
                            (EXTRACT(EPOCH FROM SUM(amir.downtime_duration))::INT % 3600) / 60, 
                            'FM00'
                        ) || ':' ||
                        TO_CHAR(
                            EXTRACT(EPOCH FROM SUM(amir.downtime_duration))::INT % 60, 
                            'FM00'
                        ),
                        '00:00:00'
                    ) as total_downtime
                FROM asset_maintenance_incident_reports amir
                INNER JOIN asset_items ai ON ai.id = amir.asset
                INNER JOIN assets a ON a.id = ai.asset_id
                LEFT JOIN asset_maintenance_incident_report_priority_levels prio 
                    ON prio.id = amir.priority_level
                LEFT JOIN work_order_tickets wot ON wot.asset_id = amir.asset 
                    AND wot.type = 'incident_report' 
                    AND wot.tenant_id = p_tenant_id
                    AND wot.deleted_at IS NULL
                LEFT JOIN work_orders wo ON wo.work_order_ticket_id = wot.id
                    AND wo.deleted_at IS NULL
                WHERE amir.tenant_id = p_tenant_id 
                AND amir.deleted_at IS NULL
                AND ai.isactive = true
                GROUP BY ai.id, ai.serial_number, a.name, prio.value
                ORDER BY incident_count DESC, total_cost DESC
                LIMIT 10
            ),
            yearly_costs AS (
                SELECT 
                    EXTRACT(YEAR FROM amir.start_time) as year,
                    SUM(COALESCE(wo.est_cost, 0)) as total_cost
                FROM asset_maintenance_incident_reports amir
                LEFT JOIN work_order_tickets wot ON wot.asset_id = amir.asset 
                    AND wot.type = 'incident_report' 
                    AND wot.tenant_id = p_tenant_id
                    AND wot.deleted_at IS NULL
                LEFT JOIN work_orders wo ON wo.work_order_ticket_id = wot.id
                    AND wo.deleted_at IS NULL
                WHERE amir.tenant_id = p_tenant_id 
                AND amir.deleted_at IS NULL
                AND EXTRACT(YEAR FROM amir.start_time) BETWEEN COALESCE(v_start_year, v_current_year) AND v_current_year
                GROUP BY EXTRACT(YEAR FROM amir.start_time)
                ORDER BY year
            )
            SELECT 
                CASE WHEN p_type = 'web' THEN 'success' ELSE 'failed' END,
                CASE WHEN p_type = 'web' THEN 'incident analytics data fetch success' ELSE 'your request type is invalid' END,
                CASE WHEN p_type = 'web' THEN 
                    jsonb_build_object(
                        'highIncidentAssets', (
                            SELECT jsonb_agg(
                                jsonb_build_object(
                                    'id', hia.asset_id,
                                    'name', hia.asset_name,
                                    'incidents', hia.incident_count,
                                    'totalCost', hia.total_cost,
                                    'lastIncident', TO_CHAR(hia.last_incident_date, 'YYYY-MM-DD'),
                                    'severity', COALESCE(hia.severity_level, 'unknown'),
                                    'totalDowntime', hia.total_downtime
                                )
                            )
                            FROM high_incident_assets hia
                        ),
                        'historicalCosts', jsonb_build_object(
                            'years', (
                                SELECT jsonb_agg(
                                    CASE 
                                        WHEN yc.year = v_current_year THEN yc.year::TEXT || ' (YTD)'
                                        WHEN yc.year = v_current_year + 1 THEN yc.year::TEXT || ' (Forecast)'
                                        ELSE yc.year::TEXT 
                                    END
                                    ORDER BY yc.year
                                )
                                FROM (
                                    SELECT year FROM yearly_costs
                                    UNION 
                                    SELECT v_current_year + 1 as year
                                ) yc
                            ),
                            'actualCosts', (
                                SELECT jsonb_agg(
                                    CASE 
                                        WHEN yc.year = v_current_year + 1 THEN NULL
                                        ELSE COALESCE(yc.total_cost, 0)
                                    END
                                    ORDER BY yc.year
                                )
                                FROM (
                                    SELECT year, total_cost FROM yearly_costs
                                    UNION 
                                    SELECT v_current_year + 1 as year, NULL as total_cost
                                ) yc
                            ),
                            'forecastCosts', (
                                SELECT jsonb_agg(
                                    CASE 
                                        WHEN yc.year = v_current_year + 1 THEN v_forecast_amount
                                        ELSE NULL
                                    END
                                    ORDER BY yc.year
                                )
                                FROM (
                                    SELECT year FROM yearly_costs
                                    UNION 
                                    SELECT v_current_year + 1 as year
                                ) yc
                            )
                        ),
                        'forecastedMaintenance', jsonb_build_object(
                            'nextPeriod', 'Next 12 months',
                            'amount', ROUND(v_forecast_amount, 0),
                            'breakdown', jsonb_build_object(
                                'preventive', ROUND(v_forecast_amount * 0.6, 0),
                                'corrective', ROUND(v_forecast_amount * 0.28, 0),
                                'emergency', ROUND(v_forecast_amount * 0.12, 0)
                            ),
                            'confidence', 85,
                            'lastUpdated', TO_CHAR(CURRENT_DATE, 'YYYY-MM-DD')
                        ),
                        'summary', jsonb_build_object(
                            'totalIncidents', v_total_incidents,
                            'totalIncidentCost', ROUND(v_total_cost, 0),
                            'averageCostPerIncident', ROUND(v_avg_cost, 0),
                            'trendDirection', CASE 
                                WHEN v_forecast_amount > v_total_cost THEN 'up'
                                WHEN v_forecast_amount < v_total_cost THEN 'down'
                                ELSE 'stable'
                            END
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
        DB::unprepared("DROP FUNCTION IF EXISTS get_asset_maintenance_incident_analytics(BIGINT, TEXT);");
    }
};
