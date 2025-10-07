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
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'delete_asset_availability_schedule'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION delete_asset_availability_schedule(
                p_asset_availability_schedule_id BIGINT,
                p_tenant_id BIGINT,
                p_current_time TIMESTAMPTZ,
                p_user_id BIGINT DEFAULT NULL,
                p_user_name VARCHAR DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                rows_updated INT;
                v_log_data JSONB;
                v_log_success BOOLEAN;
                v_error_message TEXT;
                v_is_draft BOOLEAN;
            BEGIN
                -- Check if record exists and is draft or unpublished
                SELECT TRUE INTO v_is_draft
                FROM asset_availability_schedules
                WHERE id = p_asset_availability_schedule_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL
                AND publish_status IN ('DRAFT', 'UNPUBLISHED')
                LIMIT 1;

                IF NOT FOUND THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Only schedules with publish_status = DRAFT or UNPUBLISHED can be deleted'::TEXT AS message;
                    RETURN;
                END IF;

                -- Soft delete the schedule
                UPDATE asset_availability_schedules
                SET deleted_at = p_current_time,
                    is_active = FALSE
                WHERE id = p_asset_availability_schedule_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL
                AND publish_status IN ('DRAFT', 'UNPUBLISHED');

                GET DIAGNOSTICS rows_updated = ROW_COUNT;

                -- Soft delete all related occurrences
                UPDATE asset_availability_schedule_occurrences
                SET deleted_at = p_current_time,
                    isactive = FALSE
                WHERE schedule_id = p_asset_availability_schedule_id
                AND deleted_at IS NULL
                AND isactive = TRUE;

                IF rows_updated > 0 THEN
                    -- Build log data
                    v_log_data := jsonb_build_object(
                        'schedule_id', p_asset_availability_schedule_id,
                        'deleted_at', p_current_time,
                        'tenant_id', p_tenant_id,
                        'deleted_by', p_user_id
                    );

                    -- Log activity if user info provided
                    IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
                        BEGIN
                            PERFORM log_activity(
                                'asset_availability_schedule.deleted',
                                'Schedule deleted by ' || p_user_name || ': ' || p_asset_availability_schedule_id,
                                'asset_availability_schedule',
                                p_asset_availability_schedule_id,
                                'user',
                                p_user_id,
                                v_log_data,
                                p_tenant_id
                            );
                            v_log_success := TRUE;
                        EXCEPTION WHEN OTHERS THEN
                            v_log_success := FALSE;
                            v_error_message := 'Logging failed: ' || SQLERRM;
                        END;
                    END IF;

                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status,
                        'Asset Availability Schedule deleted successfully'::TEXT AS message;
                ELSE
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'No rows updated. Schedule not found or already deleted.'::TEXT AS message;
                END IF;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS delete_asset_availability_schedule(BIGINT, BIGINT, TIMESTAMPTZ, BIGINT, VARCHAR)');
    }
};