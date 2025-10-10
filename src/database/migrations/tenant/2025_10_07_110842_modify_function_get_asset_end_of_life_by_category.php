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

         DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_asset_end_of_life_by_category'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

        CREATE OR REPLACE FUNCTION get_asset_end_of_life_by_category(
                p_tenant_id BIGINT,
                p_date DATE,
                p_type TEXT DEFAULT 'web'
        )

        RETURNS TABLE (
            status TEXT,
            message TEXT,
            endoflife_category JSONB
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
            WITH categorized_assets AS (
                SELECT 
                    ac.id as category_id,
                    ac.name as category_name,
                    ai.id as asset_id,
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
                    AND ac.deleted_at IS NULL
                WHERE ai.tenant_id = p_tenant_id
                AND ai.isactive = true
                AND ai.deleted_at IS NULL
                AND ai.depreciation_start_date IS NOT NULL 
                AND ai.expected_life_time IS NOT NULL 
                AND ai.expected_life_time_unit IS NOT NULL
            ),
            eol_assets AS (
                SELECT 
                    category_id,
                    category_name,
                    COUNT(*) as eol_count,
                    SUM(CASE 
                        WHEN end_of_life_date <= p_date THEN 1
                        ELSE 0
                    END) as expired_eol,
                    SUM(CASE 
                        WHEN end_of_life_date > p_date 
                        AND end_of_life_date <= date_trunc('year', p_date) + interval '1 year' - interval '1 day'
                        THEN 1
                        ELSE 0
                    END) as upcomming_eol_count
                FROM categorized_assets
                WHERE end_of_life_date <= date_trunc('year', p_date) + interval '1 year' - interval '1 day'
                GROUP BY category_id, category_name
            ),
            top_categories AS (
                SELECT 
                    category_id,
                    category_name,
                    eol_count,
                    upcomming_eol_count,
                    expired_eol
                FROM eol_assets 
                ORDER BY eol_count DESC
                LIMIT 6
            ),
            others_count AS (
                SELECT 
                    0 as category_id,
                    'Others' as category_name,
                    COALESCE(SUM(eol_count), 0) as eol_count,
                    COALESCE(SUM(upcomming_eol_count), 0) as upcomming_eol_count,
                    COALESCE(SUM(expired_eol), 0) as expired_eol
                FROM eol_assets
                WHERE category_id NOT IN (SELECT category_id FROM top_categories)
            )
            SELECT 
                CASE WHEN p_type = 'web' THEN 'success' ELSE 'failed' END AS status,
                CASE WHEN p_type = 'web' THEN 'asset end of life by category fetch success' ELSE 'your request type is invalid' END AS message,
                CASE WHEN p_type = 'web' THEN 
                    (
                        SELECT jsonb_agg(
                            jsonb_build_object(
                                'category_id', category_id,
                                'category_name', category_name,
                                'eol_count', eol_count,
                                'upcomming_eol_count', upcomming_eol_count,
                                'expired_eol', expired_eol
                            )
                        )
                        FROM (
                            SELECT 
                                t.category_id,
                                t.category_name,
                                t.eol_count,
                                t.upcomming_eol_count,
                                t.expired_eol
                            FROM top_categories t
                            WHERE t.category_id != 0
                            UNION ALL
                            SELECT 
                                o.category_id,
                                o.category_name,
                                o.eol_count,
                                COALESCE(SUM(e.upcomming_eol_count), 0) as upcomming_eol_count,
                                COALESCE(SUM(e.expired_eol), 0) as expired_eol
                            FROM others_count o
                            LEFT JOIN eol_assets e ON e.category_id NOT IN (SELECT category_id FROM top_categories)
                            GROUP BY o.category_id, o.category_name, o.eol_count
                        ) combined_data
                    )
                ELSE NULL END AS endoflife_category;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_asset_end_of_life_by_category(BIGINT, DATE, TEXT);");
    }
};
