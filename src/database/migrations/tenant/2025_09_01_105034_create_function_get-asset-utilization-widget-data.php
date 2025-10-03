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
        CREATE OR REPLACE FUNCTION get_asset_utilization_widget_data(
            p_tenant_id BIGINT,
            p_date DATE,
            p_type TEXT DEFAULT 'web',
            p_count INT DEFAULT 0
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            utilization_data JSONB
        )
        LANGUAGE plpgsql
        AS $$
        
        DECLARE
            v_total_assets INT;
            v_utilized_assets INT;
            v_idle_assets INT;
            v_utilization_percentage NUMERIC;
            v_period TEXT;
        BEGIN
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RAISE EXCEPTION 'Invalid tenant ID';
            END IF;

            -- Validate p_count
            IF p_count < 0 THEN
                RAISE EXCEPTION 'Invalid count value';
            END IF;

            -- Calculate period as the month and year of p_date
            v_period := TO_CHAR(p_date, 'Month YYYY');

            -- Calculate total assets and utilized assets
            SELECT 
                COUNT(DISTINCT ai.id) AS total,
                COUNT(DISTINCT CASE WHEN air.created_at >= p_date - INTERVAL '30 days' THEN ai.id END) AS utilized
            INTO
                v_total_assets,
                v_utilized_assets
            FROM asset_items ai
            JOIN assets a ON ai.asset_id = a.id
            LEFT JOIN asset_items_readings air ON ai.id = air.asset_item
            WHERE ai.tenant_id = p_tenant_id;

            -- Calculate idle assets and utilization percentage
            v_idle_assets := v_total_assets - v_utilized_assets;
            v_utilization_percentage := CASE 
                WHEN v_total_assets > 0 THEN ROUND((v_utilized_assets::NUMERIC / v_total_assets) * 100, 1)
                ELSE 0
            END;

            RETURN QUERY
            WITH idle_assets AS (
                -- Get idle assets (no readings in the last 30 days)
                SELECT 
                    ai.serial_number AS id,
                    a.name,
                    MAX(air.created_at)::DATE AS last_reading,
                    CASE 
                        WHEN MAX(air.created_at) IS NOT NULL THEN (p_date - MAX(air.created_at)::DATE)
                        ELSE NULL
                    END AS days_since_last_reading,
                    ac.name AS category
                FROM asset_items ai
                JOIN assets a ON ai.asset_id = a.id
                JOIN asset_categories ac ON a.category = ac.id
                LEFT JOIN asset_items_readings air ON ai.id = air.asset_item
                WHERE ai.tenant_id = p_tenant_id
                GROUP BY ai.serial_number, a.name, ac.name
                HAVING MAX(air.created_at) IS NULL OR MAX(air.created_at) < p_date - INTERVAL '30 days'
                ORDER BY days_since_last_reading DESC NULLS LAST
                LIMIT CASE WHEN p_count > 0 THEN p_count ELSE NULL END
            ),
            never_used AS (
                -- Assets with no readings ever
                SELECT 
                    ai.serial_number AS id,
                    a.name,
                    MIN(ai.created_at)::DATE AS install_date,
                    ac.name AS category
                FROM asset_items ai
                JOIN assets a ON ai.asset_id = a.id
                JOIN asset_categories ac ON a.category = ac.id
                LEFT JOIN asset_items_readings air ON ai.id = air.asset_item
                WHERE ai.tenant_id = p_tenant_id
                AND air.asset_item IS NULL
                GROUP BY ai.serial_number, a.name, ac.name
                ORDER BY install_date DESC
                LIMIT CASE WHEN p_count > 0 THEN p_count ELSE NULL END
            ),
            consecutive_idle AS (
                -- Assets idle for more than 30 days
                SELECT 
                    ai.serial_number AS id,
                    a.name,
                    (p_date - MAX(air.created_at)::DATE) AS days_idle,
                    ac.name AS category
                FROM asset_items ai
                JOIN assets a ON ai.asset_id = a.id
                JOIN asset_categories ac ON a.category = ac.id
                JOIN asset_items_readings air ON ai.id = air.asset_item
                WHERE ai.tenant_id = p_tenant_id
                GROUP BY ai.serial_number, a.name, ac.name
                HAVING MAX(air.created_at) < p_date - INTERVAL '30 days'
                ORDER BY days_idle DESC
                LIMIT CASE WHEN p_count > 0 THEN p_count ELSE NULL END
            ),
            declining_trend AS (
                -- Assets with declining utilization (comparing last 30 days to previous 30 days)
                SELECT 
                    a.name AS asset,
                    COUNT(CASE WHEN air.created_at >= p_date - INTERVAL '30 days' THEN 1 END) AS this_month,
                    COUNT(CASE WHEN air.created_at >= p_date - INTERVAL '60 days' 
                            AND air.created_at < p_date - INTERVAL '30 days' THEN 1 END) AS last_month,
                    CASE 
                        WHEN COUNT(CASE WHEN air.created_at >= p_date - INTERVAL '60 days' 
                                    AND air.created_at < p_date - INTERVAL '30 days' THEN 1 END) > 0
                        THEN ROUND(
                            ((COUNT(CASE WHEN air.created_at >= p_date - INTERVAL '30 days' THEN 1 END)::NUMERIC -
                            COUNT(CASE WHEN air.created_at >= p_date - INTERVAL '60 days' 
                                        AND air.created_at < p_date - INTERVAL '30 days' THEN 1 END)) * 100.0 /
                            COUNT(CASE WHEN air.created_at >= p_date - INTERVAL '60 days' 
                                        AND air.created_at < p_date - INTERVAL '30 days' THEN 1 END)), 1)
                        ELSE NULL
                    END AS decline,
                    ac.name AS category
                FROM asset_items ai
                JOIN assets a ON ai.asset_id = a.id
                JOIN asset_categories ac ON a.category = ac.id
                LEFT JOIN asset_items_readings air ON ai.id = air.asset_item
                WHERE ai.tenant_id = p_tenant_id
                AND air.created_at >= p_date - INTERVAL '60 days'
                GROUP BY a.name, ac.name
                HAVING COUNT(CASE WHEN air.created_at >= p_date - INTERVAL '60 days' 
                                AND air.created_at < p_date - INTERVAL '30 days' THEN 1 END) > 0
                AND (
                    COUNT(CASE WHEN air.created_at >= p_date - INTERVAL '30 days' THEN 1 END)::NUMERIC /
                    NULLIF(COUNT(CASE WHEN air.created_at >= p_date - INTERVAL '60 days' 
                                AND air.created_at < p_date - INTERVAL '30 days' THEN 1 END), 0)
                ) < 0.75
                ORDER BY decline DESC
                LIMIT CASE WHEN p_count > 0 THEN p_count ELSE NULL END
            )
            SELECT 
                CASE WHEN p_type = 'web' THEN 'success' ELSE 'failed' END AS status,
                CASE WHEN p_type = 'web' THEN 'asset utilization data fetch success' ELSE 'invalid request type' END AS message,
                CASE WHEN p_type = 'web' THEN 
                    jsonb_build_object(
                        'summary', jsonb_build_object(
                            'totalAssets', v_total_assets,
                            'utilizedAssets', v_utilized_assets,
                            'idleAssets', v_idle_assets,
                            'utilizationPercentage', v_utilization_percentage,
                            'period', v_period
                        ),
                        'idleAssets', (SELECT jsonb_agg(
                            jsonb_build_object(
                                'id', ia.id,
                                'name', ia.name,
                                'lastReading', ia.last_reading,
                                'daysSinceLastReading', ia.days_since_last_reading,
                                'category', ia.category
                            )
                        ) FROM idle_assets ia),
                        'criticalFlags', jsonb_build_object(
                            'neverUsed', (SELECT jsonb_agg(
                                jsonb_build_object(
                                    'id', nu.id,
                                    'name', nu.name,
                                    'installDate', nu.install_date,
                                    'category', nu.category
                                )
                            ) FROM never_used nu),
                            'consecutiveIdle', (SELECT jsonb_agg(
                                jsonb_build_object(
                                    'id', ci.id,
                                    'name', ci.name,
                                    'daysIdle', ci.days_idle,
                                    'category', ci.category
                                )
                            ) FROM consecutive_idle ci),
                            'decliningTrend', (SELECT jsonb_agg(
                                jsonb_build_object(
                                    'asset', dt.asset,
                                    'thisMonth', dt.this_month,
                                    'lastMonth', dt.last_month,
                                    'decline', dt.decline,
                                    'category', dt.category
                                )
                            ) FROM declining_trend dt)
                        )
                    )
                ELSE NULL END AS utilization_data;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_asset_utilization_widget_data(BIGINT, DATE, TEXT, INT);");
    }
};
