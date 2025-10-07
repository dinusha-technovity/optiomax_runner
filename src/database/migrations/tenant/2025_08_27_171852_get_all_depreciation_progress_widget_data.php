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
        CREATE OR REPLACE FUNCTION get_all_depreciation_progress_widget_data(
            p_tenant_id BIGINT,
            p_date DATE,
            p_type TEXT DEFAULT 'web'
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            depreciation_data JSONB
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

            -- Get the start year from the oldest depreciation start date
            SELECT EXTRACT(YEAR FROM MIN(ai.depreciation_start_date))::INT
            INTO v_start_year
            FROM asset_depreciation_schedules ads
            JOIN asset_items ai ON ai.id = ads.asset_item_id
            WHERE ads.tenant_id = p_tenant_id;

            v_current_year := EXTRACT(YEAR FROM p_date)::INT;

            RETURN QUERY
            WITH years AS (
                SELECT generate_series(v_start_year, v_current_year)::TEXT AS year
            ),
            latest_depreciation AS (
                -- Get the latest depreciation record for each asset
                SELECT DISTINCT ON (ads.asset_item_id, ads.depreciation_method_id)
                    ads.asset_item_id,
                    ads.depreciation_method_id,
                    ai.item_value as original_value,
                    ads.book_value_end as remaining_value,
                    (ai.item_value - ads.book_value_end) as depreciated_value,
                    EXTRACT(YEAR FROM ai.depreciation_start_date)::TEXT as year
                FROM asset_depreciation_schedules ads
                JOIN asset_items ai ON ai.id = ads.asset_item_id
                WHERE ads.tenant_id = p_tenant_id
                AND ads.depreciation_method_id IN (1, 2)  -- Straight Line (1) and Declining balance (2)
                AND ai.depreciation_start_date <= p_date
                ORDER BY ads.asset_item_id, ads.depreciation_method_id, ads.book_value_start DESC
            ),
            method_totals AS (
                -- Calculate totals for each depreciation method
                SELECT 
                    ld.depreciation_method_id,
                    ld.year,
                    SUM(ld.original_value) as total_original,
                    SUM(ld.depreciated_value) as total_depreciated,
                    SUM(ld.remaining_value) as total_remaining
                FROM latest_depreciation ld
                GROUP BY ld.depreciation_method_id, ld.year
            )
            SELECT 
                CASE WHEN p_type = 'web' THEN 'success' ELSE 'failed' END,
                CASE WHEN p_type = 'web' THEN 'depreciation progress data fetch success' ELSE 'your request type is invalid' END,
                CASE WHEN p_type = 'web' THEN 
                    jsonb_build_object(
                        'years', (SELECT jsonb_agg(y.year ORDER BY y.year) FROM years y),
                        'depreciationMethods', jsonb_build_object(
                            'straightLine', jsonb_build_object(
                                'label', 'Straight Line',
                                'yearlyData', (
                                    SELECT jsonb_object_agg(
                                        y.year,
                                        jsonb_build_object(
                                            'originalValue', COALESCE(mt.total_original, 0),
                                            'depreciated', COALESCE(mt.total_depreciated, 0),
                                            'remaining', COALESCE(mt.total_remaining, 0)
                                        )
                                    )
                                    FROM years y
                                    LEFT JOIN method_totals mt ON mt.year = y.year AND mt.depreciation_method_id = 1
                                )
                            ),
                            'decliningBalance', jsonb_build_object(
                                'label', 'Declining Balance',
                                'yearlyData', (
                                    SELECT jsonb_object_agg(
                                        y.year,
                                        jsonb_build_object(
                                            'originalValue', COALESCE(mt.total_original, 0),
                                            'depreciated', COALESCE(mt.total_depreciated, 0),
                                            'remaining', COALESCE(mt.total_remaining, 0)
                                        )
                                    )
                                    FROM years y
                                    LEFT JOIN method_totals mt ON mt.year = y.year AND mt.depreciation_method_id = 2
                                )
                            )
                        ),
                        'overallStats', (
                            SELECT jsonb_build_object(
                                'totalOriginalValue', COALESCE(SUM(original_value), 0),
                                'totalDepreciated', COALESCE(SUM(depreciated_value), 0),
                                'totalRemaining', COALESCE(SUM(remaining_value), 0),
                                'depreciationProgress', ROUND(
                                    CASE 
                                        WHEN SUM(original_value) = 0 THEN 0
                                        ELSE (SUM(depreciated_value) * 100.0 / NULLIF(SUM(original_value), 0))
                                    END, 3
                                )
                            )
                            FROM latest_depreciation
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
        DB::unprepared("DROP FUNCTION IF EXISTS get_all_depreciation_progress_widget_data(BIGINT, DATE, TEXT);");
    }
};
