<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Create function to get single financial year details
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        DROP FUNCTION IF EXISTS get_financial_year_details CASCADE;

        CREATE OR REPLACE FUNCTION get_financial_year_details(
            p_tenant_id BIGINT,
            p_year_id BIGINT
        ) RETURNS JSONB LANGUAGE plpgsql AS $$
        DECLARE
            v_data JSONB;
        BEGIN
            -- Validate inputs
            IF p_tenant_id IS NULL THEN
                RETURN jsonb_build_object(
                    'success', FALSE,
                    'status', 'ERROR',
                    'message', 'Tenant ID is required'
                );
            END IF;

            IF p_year_id IS NULL THEN
                RETURN jsonb_build_object(
                    'success', FALSE,
                    'status', 'ERROR',
                    'message', 'Year ID is required'
                );
            END IF;

            -- Get financial year details with creator/updater info
            SELECT jsonb_build_object(
                'id', fy.id,
                'tenant_id', fy.tenant_id,
                'year_name', fy.year_name,
                'start_date', fy.start_date,
                'end_date', fy.end_date,
                'is_running_year', fy.is_running_year,
                'status', fy.status,
                'isactive', fy.isactive,
                'created_at', fy.created_at,
                'updated_at', fy.updated_at,
                'created_by', fy.created_by,
                'created_by_name', creator.name,
                'updated_by', fy.updated_by,
                'updated_by_name', updater.name,
                'period_status', CASE 
                    WHEN fy.end_date < CURRENT_DATE THEN 'expired'
                    WHEN fy.start_date > CURRENT_DATE THEN 'upcoming'
                    ELSE 'current'
                END,
                'days_remaining', CASE 
                    WHEN fy.end_date >= CURRENT_DATE THEN fy.end_date - CURRENT_DATE
                    ELSE 0
                END,
                'total_days', fy.end_date - fy.start_date
            ) INTO v_data
            FROM financial_years fy
            LEFT JOIN users creator ON fy.created_by = creator.id AND creator.deleted_at IS NULL
            LEFT JOIN users updater ON fy.updated_by = updater.id AND updater.deleted_at IS NULL
            WHERE fy.id = p_year_id
                AND fy.tenant_id = p_tenant_id
                AND fy.deleted_at IS NULL
                AND fy.isactive = TRUE;

            IF v_data IS NULL THEN
                RETURN jsonb_build_object(
                    'success', FALSE,
                    'status', 'ERROR',
                    'message', 'Financial year not found'
                );
            END IF;

            RETURN jsonb_build_object(
                'success', TRUE,
                'status', 'SUCCESS',
                'message', 'Financial year details retrieved successfully',
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
        DB::unprepared('DROP FUNCTION IF EXISTS get_financial_year_details CASCADE');
    }
};
