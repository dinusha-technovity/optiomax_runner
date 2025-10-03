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
        CREATE OR REPLACE FUNCTION get_all_eol_asset_details_of_category(
                p_tenant_id BIGINT,
                p_category_id BIGINT,
                p_date DATE,
                p_type TEXT DEFAULT 'web'
        )

        RETURNS TABLE (
            status TEXT,
            message TEXT,
            eolassetdata_category JSONB
        )

        

        LANGUAGE plpgsql
        AS $$
        BEGIN
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RAISE EXCEPTION 'Invalid tenant ID';
            END IF;

            IF p_date IS NULL THEN
                RAISE EXCEPTION 'Invalid date parameter';
            END IF;

            RETURN QUERY
            WITH base_assets AS (
                SELECT 
                    ac.id as category_id,
                    ac.name as category_name,
                    ai.id as asset_id,
                    ai.model_number,
                    ai.serial_number,
                    ai.purchase_cost,
                    ai.purchase_cost_currency_id,
                    ai.depreciation_start_date,
                    ai.expected_life_time,
                    ai.expected_life_time_unit,
                    CASE 
                        WHEN ai.expected_life_time_unit = 1 THEN ai.depreciation_start_date + (ai.expected_life_time || ' days')::interval
                        WHEN ai.expected_life_time_unit = 2 THEN ai.depreciation_start_date + (ai.expected_life_time || ' months')::interval
                        WHEN ai.expected_life_time_unit = 3 THEN ai.depreciation_start_date + (ai.expected_life_time || ' years')::interval
                    END as end_of_life_date
                FROM asset_items ai
                INNER JOIN assets a ON ai.asset_id = a.id 
                    AND a.tenant_id = p_tenant_id
                INNER JOIN asset_categories ac ON a.category = ac.id 
                    AND ac.tenant_id = p_tenant_id 
                    AND ac.isactive = true
                WHERE ai.tenant_id = p_tenant_id
                AND ai.isactive = true
                AND ai.depreciation_start_date IS NOT NULL 
                AND ai.expected_life_time IS NOT NULL 
                AND ai.expected_life_time_unit IS NOT NULL
            ),
            top_categories AS (
                SELECT DISTINCT category_id
                FROM base_assets
                WHERE category_id != p_category_id
                ORDER BY category_id
                LIMIT 6
            ),
            filtered_assets AS (
                SELECT *
                FROM base_assets ba
                WHERE (
                    CASE 
                        WHEN p_category_id = 0 THEN 
                            ba.category_id NOT IN (SELECT category_id FROM top_categories)
                        ELSE 
                            ba.category_id = p_category_id
                    END
                )
                AND ba.end_of_life_date <= date_trunc('year', p_date) + interval '1 year' - interval '1 day'
            )
            SELECT 
                CASE WHEN p_type = 'web' THEN 'success' ELSE 'failed' END AS status,
                CASE WHEN p_type = 'web' THEN 'asset end of life by category fetch success' ELSE 'your request type is invalid' END AS message,
                CASE WHEN p_type = 'web' THEN 
                    jsonb_build_object(
                        'category_info', (
                            SELECT jsonb_build_object(
                                'category_id', COALESCE(MIN(category_id), p_category_id),
                                'category_name', COALESCE(MIN(category_name), 
                                    CASE WHEN p_category_id = 0 THEN 'Others' ELSE NULL END
                                ),
                                'total_count', COUNT(*),
                                'expired_count', SUM(CASE WHEN end_of_life_date <= p_date THEN 1 ELSE 0 END),
                                'upcoming_count', SUM(CASE 
                                    WHEN end_of_life_date > p_date 
                                    AND end_of_life_date <= date_trunc('year', p_date) + interval '1 year' - interval '1 day'
                                    THEN 1 ELSE 0 END)
                            )
                            FROM filtered_assets
                        ),
                        'assets', (
                            SELECT jsonb_agg(
                                jsonb_build_object(
                                    'asset_id', asset_id,
                                    'category_id', category_id,
                                    'category_name', category_name,
                                    'model_number', model_number,
                                    'serial_number', serial_number,
                                    'purchase_cost', purchase_cost,
                                    'purchase_cost_currency_id', purchase_cost_currency_id,
                                    'end_of_life_date', end_of_life_date,
                                    'status', CASE 
                                        WHEN end_of_life_date <= p_date THEN 'expired'
                                        WHEN end_of_life_date <= date_trunc('year', p_date) + interval '1 year' - interval '1 day'
                                        THEN 'upcoming'
                                    END
                                )
                                ORDER BY end_of_life_date ASC
                            )
                            FROM filtered_assets
                        )
                    )
                ELSE NULL END AS eolassetdata_category;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_all_eol_asset_details_of_category(BIGINT, BIGINT, DATE, TEXT);");
    }
};
