<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Create function to get financial years for master entry (simplified)
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        DROP FUNCTION IF EXISTS get_financial_years_for_master_entry CASCADE;

        CREATE OR REPLACE FUNCTION get_financial_years_for_master_entry(
            p_tenant_id BIGINT
        ) RETURNS JSONB LANGUAGE plpgsql AS $$
        DECLARE
            v_data JSONB;
        BEGIN
            -- Validate input
            IF p_tenant_id IS NULL THEN
                RETURN jsonb_build_object(
                    'success', FALSE,
                    'status', 'ERROR',
                    'message', 'Tenant ID is required'
                );
            END IF;

            -- Get all active financial years (simplified for master entry)
            SELECT COALESCE(json_agg(
                json_build_object(
                    'id', fy.id,
                    'year_name', fy.year_name,
                    'start_date', fy.start_date,
                    'end_date', fy.end_date,
                    'is_running_year', fy.is_running_year
                ) ORDER BY fy.is_running_year DESC, fy.start_date DESC
            ), '[]'::JSON) INTO v_data
            FROM financial_years fy
            WHERE fy.tenant_id = p_tenant_id
                AND fy.deleted_at IS NULL
                AND fy.isactive = TRUE
                AND fy.status = 'active';

            RETURN jsonb_build_object(
                'success', TRUE,
                'status', 'SUCCESS',
                'message', 'Financial years retrieved successfully',
                'data', v_data
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
        DB::unprepared('DROP FUNCTION IF EXISTS get_financial_years_for_master_entry CASCADE');
    }
};
