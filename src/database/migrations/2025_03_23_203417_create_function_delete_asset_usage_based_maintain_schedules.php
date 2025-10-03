<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION delete_usage_based_maintenance_schedule(
                p_schedule_id BIGINT,
                p_tenant_id BIGINT,
                p_deleted_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
            )
            RETURNS TABLE (
                status TEXT, 
                message TEXT,
                deleted_data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                deleted_data JSONB;  -- Stores schedule data before deletion
                schedule_exists BOOLEAN; -- Checks if schedule exists
                affected_rows INT; -- Tracks number of updated rows
            BEGIN
                -- Validate p_schedule_id
                IF p_schedule_id IS NULL OR p_schedule_id = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Schedule ID cannot be null or zero'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                    RETURN;
                END IF;

                -- Validate p_tenant_id
                IF p_tenant_id IS NULL OR p_tenant_id = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Tenant ID cannot be null or zero'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                    RETURN;
                END IF;

                -- Check if the schedule exists and is not already deleted
                SELECT EXISTS(
                    SELECT 1 FROM asset_usage_based_maintain_schedules
                    WHERE id = p_schedule_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL
                ) INTO schedule_exists;

                IF NOT schedule_exists THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Schedule with the given ID does not exist or is already deleted'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                    RETURN;
                END IF;

                -- Fetch schedule data before deletion
                SELECT jsonb_build_object(
                    'id', id,
                    'asset', asset,
                    'maintain_schedule_parameters', maintain_schedule_parameters,
                    'limit_or_value', limit_or_value,
                    'operator', operator,
                    'reading_parameters', reading_parameters,
                    'tenant_id', tenant_id,
                    'created_at', created_at,
                    'updated_at', updated_at
                ) INTO deleted_data
                FROM asset_usage_based_maintain_schedules
                WHERE id = p_schedule_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

                -- Soft delete the schedule
                UPDATE asset_usage_based_maintain_schedules
                SET 
                    deleted_at = p_deleted_at, 
                    isactive = FALSE
                WHERE id = p_schedule_id;

                -- Check if any row was affected
                GET DIAGNOSTICS affected_rows = ROW_COUNT;
                IF affected_rows = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No schedule deleted. It may not exist or was already deleted'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                    RETURN;
                END IF;

                -- Return success response
                RETURN QUERY SELECT 
                    'SUCCESS'::TEXT AS status, 
                    'Schedule deleted successfully'::TEXT AS message,
                    deleted_data;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS delete_usage_based_maintenance_schedule');
    }
};
