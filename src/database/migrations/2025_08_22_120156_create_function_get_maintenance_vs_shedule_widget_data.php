<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
        CREATE OR REPLACE FUNCTION get_maintenance_vs_schedule_widget_data(
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
        BEGIN
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RAISE EXCEPTION 'Invalid tenant ID';
            END IF;

            RETURN QUERY
            WITH years_data AS (
                SELECT DISTINCT EXTRACT(YEAR FROM COALESCE(wo.created_at, wo.work_order_start))::TEXT as year
                FROM work_orders wo
                WHERE wo.tenant_id = p_tenant_id
                  AND wo.deleted_at IS NULL
                  AND COALESCE(wo.created_at, wo.work_order_start) IS NOT NULL
                ORDER BY year
            ),
            yearly_stats AS (
                SELECT 
                    EXTRACT(YEAR FROM COALESCE(wo.created_at, wo.work_order_start))::TEXT as year,
                    COUNT(*) as total_maintenances,
                    COUNT(DISTINCT wo.asset_item_id) as total_assets
                FROM work_orders wo
                WHERE wo.tenant_id = p_tenant_id
                  AND wo.deleted_at IS NULL
                  AND wo.status IN ('APPROVED', 'IN_PROGRESS', 'COMPLETED')
                  AND COALESCE(wo.created_at, wo.work_order_start) IS NOT NULL
                  AND wo.asset_item_id IS NOT NULL
                GROUP BY EXTRACT(YEAR FROM COALESCE(wo.created_at, wo.work_order_start))
                ORDER BY year
            ),
            monthly_data AS (
                SELECT 
                    EXTRACT(YEAR FROM COALESCE(wo.created_at, wo.work_order_start))::TEXT as year,
                    EXTRACT(MONTH FROM COALESCE(wo.created_at, wo.work_order_start))::INTEGER as month,
                    wo.status,
                    COUNT(*) as count
                FROM work_orders wo
                WHERE wo.tenant_id = p_tenant_id
                  AND wo.deleted_at IS NULL
                  AND COALESCE(wo.created_at, wo.work_order_start) IS NOT NULL
                  AND wo.status IN ('APPROVED', 'IN_PROGRESS', 'COMPLETED')
                GROUP BY 
                    EXTRACT(YEAR FROM COALESCE(wo.created_at, wo.work_order_start)),
                    EXTRACT(MONTH FROM COALESCE(wo.created_at, wo.work_order_start)),
                    wo.status
            ),
            yearly_status_counts AS (
                SELECT 
                    EXTRACT(YEAR FROM COALESCE(wo.created_at, wo.work_order_start))::TEXT as year,
                    wo.status,
                    COUNT(*) as count
                FROM work_orders wo
                WHERE wo.tenant_id = p_tenant_id
                  AND wo.deleted_at IS NULL
                  AND COALESCE(wo.created_at, wo.work_order_start) IS NOT NULL
                  AND wo.status IN ('APPROVED', 'IN_PROGRESS', 'COMPLETED')
                GROUP BY 
                    EXTRACT(YEAR FROM COALESCE(wo.created_at, wo.work_order_start)),
                    wo.status
            ),
            maintenance_type_categories AS (
                SELECT 
                    wmt.name,
                    COUNT(wo.id) as count,
                    ROUND((COUNT(wo.id) * 100.0 / NULLIF(total_count.total, 0)), 2) as percentage
                FROM work_orders wo
                JOIN work_order_maintenance_types wmt ON wo.maintenance_type_id = wmt.id
                CROSS JOIN (
                    SELECT COUNT(*) as total 
                    FROM work_orders wo2 
                    WHERE wo2.tenant_id = p_tenant_id 
                      AND wo2.deleted_at IS NULL
                      AND wo2.status IN ('APPROVED', 'IN_PROGRESS', 'COMPLETED')
                      AND wo2.maintenance_type_id IS NOT NULL
                ) total_count
                WHERE wo.tenant_id = p_tenant_id
                  AND wo.deleted_at IS NULL
                  AND wo.maintenance_type_id IS NOT NULL
                  AND wo.status IN ('APPROVED', 'IN_PROGRESS', 'COMPLETED')
                GROUP BY wmt.name, total_count.total
                ORDER BY count DESC
                LIMIT 3
            )
            SELECT 
                CASE WHEN p_type = 'web' THEN 'success' ELSE 'failed' END AS status,
                CASE WHEN p_type = 'web' THEN 'maintenance vs schedule widget data fetch success' ELSE 'your request type is invalid' END AS message,
                CASE WHEN p_type = 'web' THEN 
                    jsonb_build_object(
                        'years', (SELECT jsonb_agg(year ORDER BY year) FROM years_data),
                        'stats', (
                            SELECT jsonb_object_agg(
                                year, 
                                jsonb_build_object(
                                    'totalMaintenances', total_maintenances,
                                    'totalAssets', total_assets
                                )
                            )
                            FROM yearly_stats
                        ),
                        'monthly', (
                            SELECT jsonb_object_agg(
                                year,
                                jsonb_build_object(
                                    'scheduled', COALESCE((
                                        SELECT jsonb_agg(
                                            COALESCE(count, 0) ORDER BY generate_series
                                        )
                                        FROM generate_series(1, 12) AS generate_series
                                        LEFT JOIN monthly_data md ON md.year = ys.year 
                                            AND md.month = generate_series 
                                            AND (md.status = 'APPROVED')
                                    ), (SELECT jsonb_agg(0) FROM generate_series(1, 12))),
                                    'inProgress', COALESCE((
                                        SELECT jsonb_agg(
                                            COALESCE(count, 0) ORDER BY generate_series
                                        )
                                        FROM generate_series(1, 12) AS generate_series
                                        LEFT JOIN monthly_data md ON md.year = ys.year 
                                            AND md.month = generate_series 
                                            AND md.status = 'IN_PROGRESS'
                                    ), (SELECT jsonb_agg(0) FROM generate_series(1, 12))),
                                    'completed', COALESCE((
                                        SELECT jsonb_agg(
                                            COALESCE(count, 0) ORDER BY generate_series
                                        )
                                        FROM generate_series(1, 12) AS generate_series
                                        LEFT JOIN monthly_data md ON md.year = ys.year 
                                            AND md.month = generate_series 
                                            AND md.status = 'COMPLETED'
                                    ), (SELECT jsonb_agg(0) FROM generate_series(1, 12)))
                                )
                            )
                            FROM years_data ys
                        ),
                        'yearly', (
                            SELECT jsonb_build_object(
                                'scheduled', (
                                    SELECT jsonb_agg(COALESCE(ysc.count, 0) ORDER BY yd.year)
                                    FROM years_data yd
                                    LEFT JOIN yearly_status_counts ysc ON yd.year = ysc.year AND ysc.status = 'APPROVED'
                                ),
                                'inProgress', (
                                    SELECT jsonb_agg(COALESCE(ysc.count, 0) ORDER BY yd.year)
                                    FROM years_data yd
                                    LEFT JOIN yearly_status_counts ysc ON yd.year = ysc.year AND ysc.status = 'IN_PROGRESS'
                                ),
                                'completed', (
                                    SELECT jsonb_agg(COALESCE(ysc.count, 0) ORDER BY yd.year)
                                    FROM years_data yd
                                    LEFT JOIN yearly_status_counts ysc ON yd.year = ysc.year AND ysc.status = 'COMPLETED'
                                )
                            )
                        ),
                        'categories', (
                            SELECT jsonb_object_agg(
                                name,
                                jsonb_build_object(
                                    'percentage', percentage,
                                    'color', CASE 
                                        WHEN name ILIKE '%preventive%' THEN '#10b981'
                                        WHEN name ILIKE '%corrective%' THEN '#f59e0b'
                                        WHEN name ILIKE '%emergency%' THEN '#ef4444'
                                        ELSE '#6b7280'
                                    END
                                )
                            )
                            FROM maintenance_type_categories
                        )
                    )
                ELSE NULL END AS maintenance_data;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_maintenance_vs_schedule_widget_data(BIGINT, TEXT);");

    }
};
