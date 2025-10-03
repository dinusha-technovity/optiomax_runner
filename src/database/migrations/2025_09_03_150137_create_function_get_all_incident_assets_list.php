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
        CREATE OR REPLACE FUNCTION get_high_incident_assets(
            p_tenant_id BIGINT,
            p_type TEXT DEFAULT 'web',
            p_count INT DEFAULT 0
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            incident_data JSONB
        )
        LANGUAGE plpgsql
        AS $$
        BEGIN
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RAISE EXCEPTION 'Invalid tenant ID';
            END IF;

            IF p_count < 0 THEN
                RAISE EXCEPTION 'Count parameter cannot be negative';
            END IF;

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
                HAVING COUNT(amir.id) > 0  -- Only assets with incidents
                ORDER BY incident_count DESC, total_cost DESC
                -- Apply LIMIT only if p_count > 0
                LIMIT CASE WHEN p_count = 0 THEN NULL ELSE p_count END
            )
            SELECT 
                CASE WHEN p_type = 'web' THEN 'success' ELSE 'failed' END,
                CASE WHEN p_type = 'web' THEN 'high incident assets data fetch success' ELSE 'your request type is invalid' END,
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
        DB::unprepared("DROP FUNCTION IF EXISTS get_high_incident_assets(BIGINT, TEXT, INT);");
    }
};
