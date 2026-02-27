<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Create function to get paginated financial years list
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        DROP FUNCTION IF EXISTS get_financial_years_list CASCADE;

        CREATE OR REPLACE FUNCTION get_financial_years_list(
            p_tenant_id BIGINT,
            p_page INT DEFAULT 1,
            p_per_page INT DEFAULT 10,
            p_search VARCHAR DEFAULT NULL,
            p_status VARCHAR DEFAULT NULL,
            p_is_running_year BOOLEAN DEFAULT NULL,
            p_sort_by VARCHAR DEFAULT 'newest'
        ) RETURNS JSONB LANGUAGE plpgsql AS $$
        DECLARE
            v_offset INT;
            v_total_records BIGINT;
            v_data JSONB;
            v_order_clause TEXT;
        BEGIN
            -- Validate pagination
            IF p_page < 1 THEN
                p_page := 1;
            END IF;

            IF p_per_page < 1 OR p_per_page > 100 THEN
                p_per_page := 10;
            END IF;

            v_offset := (p_page - 1) * p_per_page;

            -- Determine sort order
            v_order_clause := CASE p_sort_by
                WHEN 'oldest' THEN 'fy.created_at ASC'
                WHEN 'az' THEN 'fy.year_name ASC'
                WHEN 'za' THEN 'fy.year_name DESC'
                WHEN 'start_date_asc' THEN 'fy.start_date ASC'
                WHEN 'start_date_desc' THEN 'fy.start_date DESC'
                ELSE 'fy.created_at DESC' -- newest
            END;

            -- Get total count
            SELECT COUNT(*)
            INTO v_total_records
            FROM financial_years fy
            WHERE fy.tenant_id = p_tenant_id
                AND fy.deleted_at IS NULL
                AND fy.isactive = TRUE
                AND (p_search IS NULL OR fy.year_name ILIKE '%' || p_search || '%')
                AND (p_status IS NULL OR fy.status = p_status)
                AND (p_is_running_year IS NULL OR fy.is_running_year = p_is_running_year);

            -- Get paginated data with creator/updater info
            EXECUTE format($sql$
                SELECT COALESCE(json_agg(t.*), '[]'::JSON)
                FROM (
                    SELECT 
                        fy.id,
                        fy.tenant_id,
                        fy.year_name,
                        fy.start_date,
                        fy.end_date,
                        fy.is_running_year,
                        fy.status,
                        fy.isactive,
                        fy.created_at,
                        fy.updated_at,
                        fy.created_by,
                        creator.name as created_by_name,
                        fy.updated_by,
                        updater.name as updated_by_name,
                        CASE 
                            WHEN fy.end_date < CURRENT_DATE THEN 'expired'
                            WHEN fy.start_date > CURRENT_DATE THEN 'upcoming'
                            ELSE 'current'
                        END as period_status
                    FROM financial_years fy
                    LEFT JOIN users creator ON fy.created_by = creator.id AND creator.deleted_at IS NULL
                    LEFT JOIN users updater ON fy.updated_by = updater.id AND updater.deleted_at IS NULL
                    WHERE fy.tenant_id = $1
                        AND fy.deleted_at IS NULL
                        AND fy.isactive = TRUE
                        AND ($2 IS NULL OR fy.year_name ILIKE '%%' || $2 || '%%')
                        AND ($3 IS NULL OR fy.status = $3)
                        AND ($4 IS NULL OR fy.is_running_year = $4)
                    ORDER BY %s
                    LIMIT $5 OFFSET $6
                ) t
            $sql$, v_order_clause)
            INTO v_data
            USING p_tenant_id, p_search, p_status, p_is_running_year, p_per_page, v_offset;

            RETURN jsonb_build_object(
                'success', TRUE,
                'status', 'SUCCESS',
                'message', 'Financial years retrieved successfully',
                'data', v_data,
                'pagination', jsonb_build_object(
                    'current_page', p_page,
                    'per_page', p_per_page,
                    'total_records', v_total_records,
                    'total_pages', CEIL(v_total_records::NUMERIC / p_per_page)
                )
            );

        EXCEPTION
            WHEN OTHERS THEN
                RETURN jsonb_build_object(
                    'success', FALSE,
                    'status', 'ERROR',
                    'message', 'An error occurred: ' || SQLERRM
                );
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_financial_years_list CASCADE');
    }
};
