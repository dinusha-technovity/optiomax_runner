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
                    WHERE proname = 'set_asset_availability_blockout_schedule_publish_status'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION set_asset_availability_blockout_schedule_publish_status(
                p_blockout_schedule_id BIGINT,
                p_tenant_id BIGINT,
                p_action VARCHAR, -- 'PUBLISH' or 'UNPUBLISH'
                p_current_time TIMESTAMP WITH TIME ZONE,
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
                v_current_status TEXT;
            BEGIN
                -- Get current publish_status
                SELECT publish_status INTO v_current_status
                FROM asset_availability_blockout_schedules
                WHERE id = p_blockout_schedule_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL
                LIMIT 1;

                IF NOT FOUND THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Blockout schedule not found.'::TEXT AS message;
                    RETURN;
                END IF;

                IF p_action = 'PUBLISHED' THEN
                    IF v_current_status NOT IN ('DRAFT', 'UNPUBLISHED') THEN
                        RETURN QUERY SELECT 
                            'FAILURE'::TEXT AS status,
                            'Only blockout schedules with publish_status = DRAFT or UNPUBLISHED can be published'::TEXT AS message;
                        RETURN;
                    END IF;
                    UPDATE asset_availability_blockout_schedules
                    SET publish_status = 'PUBLISHED',
                        updated_at = p_current_time
                    WHERE id = p_blockout_schedule_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL
                    AND publish_status IN ('DRAFT', 'UNPUBLISHED');
                ELSIF p_action = 'UNPUBLISHED' THEN
                    IF v_current_status <> 'PUBLISHED' THEN
                        RETURN QUERY SELECT 
                            'FAILURE'::TEXT AS status,
                            'Only blockout schedules with publish_status = PUBLISH can be unpublished'::TEXT AS message;
                        RETURN;
                    END IF;
                    UPDATE asset_availability_blockout_schedules
                    SET publish_status = 'UNPUBLISHED',
                        updated_at = p_current_time
                    WHERE id = p_blockout_schedule_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL
                    AND publish_status = 'PUBLISHED';
                ELSE
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid action. Use PUBLISHED or UNPUBLISHED.'::TEXT AS message;
                    RETURN;
                END IF;

                GET DIAGNOSTICS rows_updated = ROW_COUNT;

                IF rows_updated > 0 THEN
                    -- Build log data
                    v_log_data := jsonb_build_object(
                        'blockout_schedule_id', p_blockout_schedule_id,
                        'action', p_action,
                        'changed_at', p_current_time,
                        'tenant_id', p_tenant_id,
                        'changed_by', p_user_id
                    );

                    -- Log activity if user info provided
                    IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
                        BEGIN
                            PERFORM log_activity(
                                CASE WHEN p_action = 'PUBLISHED' THEN 'asset_availability_blockout_schedule.published'
                                    ELSE 'asset_availability_blockout_schedule.unpublished' END,
                                'Blockout schedule ' || lower(p_action) || 'ed by ' || p_user_name || ': ' || p_blockout_schedule_id,
                                'asset_availability_blockout_schedule',
                                p_blockout_schedule_id,
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
                        'Asset Availability Blockout Schedule ' || lower(p_action) || 'ed successfully'::TEXT AS message;
                ELSE
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'No rows updated. Blockout schedule not found or already in desired state.'::TEXT AS message;
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
        DB::unprepared('DROP FUNCTION IF EXISTS set_asset_availability_blockout_schedule_publish_status(BIGINT, BIGINT, VARCHAR, TIMESTAMPTZ, BIGINT, VARCHAR)');
    }
};