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
        CREATE OR REPLACE FUNCTION get_book_values_from_categories_widget_data(
                p_tenant_id BIGINT,
                p_date DATE,
                p_type TEXT DEFAULT 'web'
        )

        RETURNS TABLE (
            status TEXT,
            message TEXT,
            bookvalue_category JSONB
        )

        LANGUAGE plpgsql
        AS $$
        BEGIN
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RAISE EXCEPTION 'Invalid tenant ID';
            END IF;

            IF p_date IS NULL THEN
                p_date := CURRENT_DATE;
            END IF;

            -- Early exit if no depreciation schedules exist for the date
            IF NOT EXISTS (
                SELECT 1 FROM asset_depreciation_schedules 
                WHERE tenant_id = p_tenant_id AND record_date = p_date
                LIMIT 1
            ) THEN
                RETURN QUERY
                SELECT 
                    CASE WHEN p_type = 'web' THEN 'success' ELSE 'failed' END AS status,
                    CASE WHEN p_type = 'web' THEN 'book value categories widget data fetch success' ELSE 'your request type is invalid' END AS message,
                    CASE WHEN p_type = 'web' THEN 
                        jsonb_build_object(
                            'summary', jsonb_build_object(
                                'totalBookValue', 0,
                                'totalCategories', 0,
                                'averageBookValuePerCategory', 0,
                                'highestValueCategory', '',
                                'lowestValueCategory', ''
                            ),
                            'categoryBreakdown', '[]'::jsonb
                        )
                    ELSE NULL END AS bookvalue_category;
                RETURN;
            END IF;

            RETURN QUERY
            WITH category_book_values AS (
                SELECT 
                    ac.id as category_id,
                    ac.name as category_name,
                    SUM(ads.book_value_end) as total_book_value,
                    COUNT(ai.id) as asset_count
                FROM asset_depreciation_schedules ads
                INNER JOIN asset_items ai ON ads.asset_item_id = ai.id 
                    AND ai.tenant_id = p_tenant_id 
                    AND ai.isactive = true
                INNER JOIN assets a ON ai.asset_id = a.id 
                    AND a.tenant_id = p_tenant_id
                INNER JOIN asset_categories ac ON a.category = ac.id 
                    AND ac.tenant_id = p_tenant_id 
                    AND ac.isactive = true
                WHERE ads.tenant_id = p_tenant_id 
                    AND ads.record_date = p_date
                    AND ads.book_value_end > 0
                GROUP BY ac.id, ac.name
                ORDER BY total_book_value DESC
            ),
            top_categories AS (
                SELECT 
                    category_name,
                    total_book_value,
                    ROW_NUMBER() OVER (ORDER BY total_book_value DESC) as rn_desc,
                    ROW_NUMBER() OVER (ORDER BY total_book_value ASC) as rn_asc,
                    SUM(total_book_value) OVER() as grand_total_book_value,
                    COUNT(*) OVER() as total_categories,
                    AVG(total_book_value) OVER() as average_book_value_per_category
                FROM category_book_values
            ),
            top_6_and_others AS (
                SELECT 
                    CASE 
                        WHEN rn_desc <= 6 THEN category_name
                        ELSE 'Other'
                    END as display_name,
                    SUM(total_book_value) as grouped_book_value,
                    MAX(grand_total_book_value) as grand_total_book_value,
                    MAX(total_categories) as total_categories,
                    MAX(average_book_value_per_category) as average_book_value_per_category,
                    MAX(CASE WHEN rn_desc = 1 THEN category_name END) as highest_category,
                    MAX(CASE WHEN rn_asc = 1 THEN category_name END) as lowest_category,
                    ROUND((SUM(total_book_value) / MAX(grand_total_book_value) * 100)::numeric, 1) as percentage
                FROM top_categories
                GROUP BY 
                    CASE 
                        WHEN rn_desc <= 6 THEN category_name
                        ELSE 'Other'
                    END,
                    CASE 
                        WHEN rn_desc <= 6 THEN rn_desc
                        ELSE 999
                    END
                ORDER BY 
                    CASE 
                        WHEN rn_desc <= 6 THEN rn_desc
                        ELSE 999
                    END
            ),
            aggregated_data AS (
                SELECT 
                    MAX(grand_total_book_value) as grand_total_book_value,
                    MAX(total_categories) as total_categories,
                    MAX(average_book_value_per_category) as average_book_value_per_category,
                    MAX(highest_category) as highest_category,
                    MAX(lowest_category) as lowest_category,
                    jsonb_agg(
                        jsonb_build_object(
                            'name', display_name,
                            'bookValue', grouped_book_value,
                            'percentage', percentage
                        ) ORDER BY 
                            CASE WHEN display_name = 'Other' THEN 999 ELSE grouped_book_value END DESC
                    ) as breakdown
                FROM top_6_and_others
            )
            SELECT 
                CASE WHEN p_type = 'web' THEN 'success' ELSE 'failed' END AS status,
                CASE WHEN p_type = 'web' THEN 'book value categories widget data fetch success' ELSE 'your request type is invalid' END AS message,
                CASE WHEN p_type = 'web' THEN 
                    jsonb_build_object(
                        'summary', jsonb_build_object(
                            'totalBookValue', COALESCE(ad.grand_total_book_value, 0),
                            'totalCategories', COALESCE(ad.total_categories, 0),
                            'averageBookValuePerCategory', ROUND(COALESCE(ad.average_book_value_per_category, 0)::numeric, 0),
                            'highestValueCategory', COALESCE(ad.highest_category, ''),
                            'lowestValueCategory', COALESCE(ad.lowest_category, '')
                        ),
                        'categoryBreakdown', COALESCE(ad.breakdown, '[]'::jsonb)
                    )
                ELSE NULL END AS bookvalue_category
            FROM aggregated_data ad;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_book_values_from_categories_widget_data(BIGINT, TEXT, DATE);");
    }
};
