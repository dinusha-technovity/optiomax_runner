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
            DROP FUNCTION IF EXISTS get_time_periods(VARCHAR);

            CREATE OR REPLACE FUNCTION get_time_periods(
                IN p_get_type VARCHAR DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                name TEXT,
                slug TEXT,
                is_date_type BOOLEAN,
                is_time_type BOOLEAN,
                created_at TIMESTAMP,
                updated_at TIMESTAMP
            ) 
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Return all records if parameter is NULL
                IF p_get_type IS NULL THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS',
                        'All time periods fetched successfully',
                        t.id,
                        t.name::TEXT,
                        t.slug::TEXT,
                        t.is_date_type,
                        t.is_time_type,
                        t.created_at,
                        t.updated_at
                    FROM time_period_entries t;
                    RETURN;
                END IF;

                -- Return time periods only (hour, minute, second)
                IF p_get_type = '-1' THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS',
                        'Time periods fetched successfully',
                        t.id,
                        t.name::TEXT,
                        t.slug::TEXT,
                        t.is_date_type,
                        t.is_time_type,
                        t.created_at,
                        t.updated_at
                    FROM time_period_entries t
                    WHERE t.is_time_type = TRUE;
                    RETURN;
                END IF;

                -- Return date periods only (day, month, year)
                IF p_get_type = '0' THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS',
                        'Date periods fetched successfully',
                        t.id,
                        t.name::TEXT,
                        t.slug::TEXT,
                        t.is_date_type,
                        t.is_time_type,
                        t.created_at,
                        t.updated_at
                    FROM time_period_entries t
                    WHERE t.is_date_type = TRUE;
                    RETURN;
                END IF;

                -- Invalid parameter value
                RETURN QUERY 
                SELECT 
                    'FAILURE',
                    'Invalid parameter value. Use NULL for all, -1 for time periods, or 0 for date periods',
                    NULL, NULL, NULL, NULL, NULL, NULL, NULL;
            END; 
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_time_periods(INT);");
    }
};
