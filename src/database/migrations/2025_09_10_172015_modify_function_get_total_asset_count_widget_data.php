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

            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_total_asset_count_widget_data'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;


            CREATE OR REPLACE FUNCTION get_total_asset_count_widget_data(
                p_tenant_id BIGINT,
                p_type TEXT DEFAULT 'web'
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                summary JSONB,
                statusBreakdown JSONB,
                assetGroupBreakdown JSONB,
                additionalMetrics JSONB
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RAISE EXCEPTION 'Invalid tenant ID';
                END IF;

                -- Precompute work order asset items for performance
                CREATE TEMP TABLE tmp_work_order_asset_items AS
                    SELECT DISTINCT asset_item_id FROM work_orders wo WHERE tenant_id = p_tenant_id AND wo.status = 'IN_PROGRESS' AND asset_item_id IS NOT NULL;

                RETURN QUERY
                WITH total_assets AS (
                    SELECT COUNT(*) AS cnt FROM asset_items WHERE tenant_id = p_tenant_id AND isactive = true AND deleted_at IS NULL
                ),
                total_categories AS (
                    SELECT COUNT(*) AS cnt FROM asset_categories WHERE tenant_id = p_tenant_id AND isactive = true AND deleted_at IS NULL
                ),
                total_asset_groups AS (
                    SELECT COUNT(*) AS cnt FROM assets WHERE tenant_id = p_tenant_id AND isactive = true AND deleted_at IS NULL
                ),
                active_assets AS (
                    SELECT COUNT(*) AS cnt FROM asset_items ai
                    WHERE ai.tenant_id = p_tenant_id
                      AND ai.isactive = true
                      AND ai.deleted_at IS NULL
                      AND ai.id NOT IN (SELECT asset_item_id FROM tmp_work_order_asset_items)
                ),
                expired_assets AS (
                    SELECT COUNT(*) AS cnt FROM asset_items ai
                    WHERE ai.tenant_id = p_tenant_id AND ai.deleted_at IS NULL
                      AND (
                        (ai.depreciation_start_date IS NOT NULL AND ai.expected_life_time IS NOT NULL AND ai.expected_life_time_unit IS NOT NULL)
                        AND (
                            (
                                ai.expected_life_time_unit = 1 AND ai.depreciation_start_date + (ai.expected_life_time || ' days')::interval < CURRENT_DATE
                            ) OR (
                                ai.expected_life_time_unit = 2 AND ai.depreciation_start_date + (ai.expected_life_time || ' months')::interval < CURRENT_DATE
                            ) OR (
                                ai.expected_life_time_unit = 3 AND ai.depreciation_start_date + (ai.expected_life_time || ' years')::interval < CURRENT_DATE
                            )
                        )
                      )
                ),
                maintenance_assets AS (
                    SELECT COUNT(DISTINCT asset_item_id) AS cnt FROM work_orders wo WHERE tenant_id = p_tenant_id AND wo.status = 'IN_PROGRESS' AND asset_item_id IS NOT NULL
                ),
                inactive_assets AS (
                    SELECT COUNT(*) AS cnt FROM asset_items ai
                    WHERE ai.tenant_id = p_tenant_id
                      AND (ai.isactive = false OR ai.id IN (SELECT asset_item_id FROM tmp_work_order_asset_items))
                ),
                retired_assets AS (
                    SELECT cnt FROM expired_assets
                ),
                asset_group_breakdown AS (
                    SELECT a.name, COUNT(ai.id) AS count
                    FROM asset_items ai
                    JOIN assets a ON ai.asset_id = a.id
                    WHERE ai.tenant_id = p_tenant_id
                    GROUP BY a.id, a.name
                    ORDER BY count DESC
                    LIMIT 5
                ),
                total_value AS (
                    SELECT COALESCE(SUM(item_value),0) AS val FROM asset_items WHERE tenant_id = p_tenant_id
                ),
                average_age AS (
                    SELECT COALESCE(AVG(
                        CASE 
                            WHEN expected_life_time_unit = 1 THEN (expected_life_time::numeric) / 365.0
                            WHEN expected_life_time_unit = 2 THEN (expected_life_time::numeric) / 12.0
                            WHEN expected_life_time_unit = 3 THEN expected_life_time::numeric
                            ELSE NULL
                        END
                    ),0) AS avg_age
                    FROM asset_items WHERE tenant_id = p_tenant_id
                ),
                monthly_growth AS (
                    SELECT 
                        CASE WHEN prev.cnt = 0 THEN 0 ELSE ROUND(((curr.cnt - prev.cnt)::numeric / prev.cnt) * 100, 2) END AS growth
                    FROM (
                        SELECT COUNT(*) AS cnt FROM asset_items WHERE tenant_id = p_tenant_id AND deleted_at IS NULL AND date_trunc('month', created_at) = date_trunc('month', CURRENT_DATE)
                    ) curr,
                    (
                        SELECT COUNT(*) AS cnt FROM asset_items WHERE tenant_id = p_tenant_id AND deleted_at IS NULL AND date_trunc('month', created_at) = date_trunc('month', CURRENT_DATE - INTERVAL '1 month')
                    ) prev
                )
                SELECT 
                    CASE WHEN p_type = 'web' THEN 'success' ELSE 'failed' END AS status,
                    CASE WHEN p_type = 'web' THEN 'total asset count widget data fetch success' ELSE 'your request type is invalid' END AS message,
                    CASE WHEN p_type = 'web' THEN 
                        jsonb_build_object(
                            'totalAssets', (SELECT cnt FROM total_assets),
                            'activeAssets', (SELECT cnt FROM active_assets),
                            'totalCategories', (SELECT cnt FROM total_categories),
                            'totalAssetGroups', (SELECT cnt FROM total_asset_groups),
                            'activePercentage', 
                                CASE WHEN (SELECT cnt FROM total_assets) = 0 THEN 0 ELSE ROUND(((SELECT cnt FROM active_assets)::numeric / (SELECT cnt FROM total_assets)) * 100, 2) END
                        )
                    ELSE NULL END AS summary,
                    CASE WHEN p_type = 'web' THEN 
                        jsonb_build_object(
                            'active', (SELECT cnt FROM active_assets),
                            'inactive', (SELECT cnt FROM inactive_assets),
                            'maintenance', (SELECT cnt FROM maintenance_assets),
                            'retired', (SELECT cnt FROM retired_assets)
                        )
                    ELSE NULL END AS statusBreakdown,
                    CASE WHEN p_type = 'web' THEN 
                        (SELECT jsonb_agg(jsonb_build_object('name', name, 'count', count)) FROM asset_group_breakdown)
                    ELSE NULL END AS assetGroupBreakdown,
                    CASE WHEN p_type = 'web' THEN 
                        jsonb_build_object(
                            'totalValue', (SELECT val FROM total_value),
                            'averageAge', (SELECT avg_age FROM average_age),
                            'monthlyGrowthRate', (SELECT growth FROM monthly_growth)
                        )
                    ELSE NULL END AS additionalMetrics;

                DROP TABLE IF EXISTS tmp_work_order_asset_items;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_total_asset_count_widget_data(BIGINT, BIGINT, BIGINT);");
    }
};
