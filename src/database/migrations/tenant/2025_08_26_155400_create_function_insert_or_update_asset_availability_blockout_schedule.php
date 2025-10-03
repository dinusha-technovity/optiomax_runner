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
            CREATE OR REPLACE FUNCTION insert_or_update_asset_availability_blockout_schedule(
                IN p_asset_id BIGINT,
                IN p_block_start_datetime TIMESTAMPTZ,
                IN p_block_end_datetime TIMESTAMPTZ,
                IN p_publish_status VARCHAR DEFAULT 'DRAFT',
                IN p_reason_type_id BIGINT DEFAULT NULL,
                IN p_custom_reason VARCHAR DEFAULT NULL,
                IN p_description TEXT DEFAULT NULL,
                IN p_recurring_enabled BOOLEAN DEFAULT FALSE,
                IN p_recurring_pattern VARCHAR DEFAULT NULL,
                IN p_recurring_config JSONB DEFAULT NULL,
                IN p_created_by BIGINT DEFAULT NULL,
                IN p_is_active BOOLEAN DEFAULT TRUE, 
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_id BIGINT DEFAULT NULL,
                IN p_deleted_at TIMESTAMPTZ DEFAULT NULL,
                IN p_user_name VARCHAR DEFAULT NULL,
                IN p_current_time TIMESTAMPTZ DEFAULT now()
            ) RETURNS TABLE (
                status TEXT,
                message TEXT,
                blockout_id BIGINT
            ) LANGUAGE plpgsql AS $$
            DECLARE
                v_id BIGINT;
                v_new_data JSONB;
                v_log_data JSONB;
                v_log_success BOOLEAN;
                v_error_message TEXT;
            BEGIN
                -- Validate asset_id
                IF p_asset_id IS NULL OR p_asset_id <= 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Invalid asset ID', NULL::BIGINT;
                    RETURN;
                END IF;

                -- Always normalize to UTC
                -- p_block_start_datetime := (p_block_start_datetime AT TIME ZONE 'UTC');
                -- p_block_end_datetime   := (p_block_end_datetime   AT TIME ZONE 'UTC');
                -- p_current_time         := (p_current_time         AT TIME ZONE 'UTC');

                IF p_id IS NULL THEN
                    -- Insert new blockout schedule
                    INSERT INTO asset_availability_blockout_schedules(
                        asset_id, block_start_datetime, block_end_datetime, publish_status, reason_type_id,
                        custom_reason, description, recurring_enabled, recurring_pattern, recurring_config,
                        created_by, is_active, tenant_id, deleted_at, created_at, updated_at
                    ) VALUES (
                        p_asset_id, p_block_start_datetime, p_block_end_datetime, p_publish_status, p_reason_type_id,
                        p_custom_reason, p_description, p_recurring_enabled, p_recurring_pattern, p_recurring_config,
                        p_created_by, p_is_active, p_tenant_id, p_deleted_at, p_current_time, p_current_time
                    ) RETURNING id INTO v_id;

                    -- Build new data snapshot
                    v_new_data := jsonb_build_object(
                        'id', v_id,
                        'asset_id', p_asset_id,
                        'block_start_datetime', p_block_start_datetime,
                        'block_end_datetime', p_block_end_datetime,
                        'publish_status', p_publish_status,
                        'reason_type_id', p_reason_type_id,
                        'custom_reason', p_custom_reason,
                        'description', p_description,
                        'recurring_enabled', p_recurring_enabled,
                        'recurring_pattern', p_recurring_pattern,
                        'recurring_config', p_recurring_config,
                        'created_by', p_created_by,
                        'is_active', p_is_active,
                        'tenant_id', p_tenant_id,
                        'deleted_at', p_deleted_at
                    );
                    v_log_data := jsonb_build_object(
                        'blockout_id', v_id,
                        'new_data', v_new_data
                    );

                    -- Log activity if user info provided
                    IF p_created_by IS NOT NULL AND p_user_name IS NOT NULL THEN
                        BEGIN
                            PERFORM log_activity(
                                'asset_availability_blockout_schedule.created',
                                'Blockout created by ' || p_user_name || ': ' || v_id,
                                'asset_availability_blockout_schedule',
                                v_id,
                                'user',
                                p_created_by,
                                v_log_data,
                                p_tenant_id
                            );
                            v_log_success := TRUE;
                        EXCEPTION WHEN OTHERS THEN
                            v_log_success := FALSE;
                            v_error_message := 'Logging failed: ' || SQLERRM;
                        END;
                    END IF;

                    RETURN QUERY SELECT 'SUCCESS', 'Blockout created successfully', v_id;
                ELSE
                    -- Update existing blockout schedule
                    UPDATE asset_availability_blockout_schedules SET
                        asset_id = p_asset_id,
                        block_start_datetime = p_block_start_datetime,
                        block_end_datetime = p_block_end_datetime,
                        publish_status = p_publish_status,
                        reason_type_id = p_reason_type_id,
                        custom_reason = p_custom_reason,
                        description = p_description,
                        recurring_enabled = p_recurring_enabled,
                        recurring_pattern = p_recurring_pattern,
                        recurring_config = p_recurring_config,
                        created_by = p_created_by,
                        is_active = p_is_active,
                        tenant_id = p_tenant_id,
                        deleted_at = p_deleted_at,
                        updated_at = p_current_time
                    WHERE id = p_id
                    RETURNING id INTO v_id;

                    IF v_id IS NULL THEN
                        RETURN QUERY SELECT 'FAILURE', 'Blockout not found for update', NULL::BIGINT;
                    ELSE
                        -- Build new data snapshot
                        v_new_data := jsonb_build_object(
                            'id', v_id,
                            'asset_id', p_asset_id,
                            'block_start_datetime', p_block_start_datetime,
                            'block_end_datetime', p_block_end_datetime,
                            'publish_status', p_publish_status,
                            'reason_type_id', p_reason_type_id,
                            'custom_reason', p_custom_reason,
                            'description', p_description,
                            'recurring_enabled', p_recurring_enabled,
                            'recurring_pattern', p_recurring_pattern,
                            'recurring_config', p_recurring_config,
                            'created_by', p_created_by,
                            'is_active', p_is_active,
                            'tenant_id', p_tenant_id,
                            'deleted_at', p_deleted_at
                        );
                        v_log_data := jsonb_build_object(
                            'blockout_id', v_id,
                            'new_data', v_new_data
                        );

                        -- Log activity if user info provided
                        IF p_created_by IS NOT NULL AND p_user_name IS NOT NULL THEN
                            BEGIN
                                PERFORM log_activity(
                                    'asset_availability_blockout_schedule.updated',
                                    'Blockout updated by ' || p_user_name || ': ' || v_id,
                                    'asset_availability_blockout_schedule',
                                    v_id,
                                    'user',
                                    p_created_by,
                                    v_log_data,
                                    p_tenant_id
                                );
                                v_log_success := TRUE;
                            EXCEPTION WHEN OTHERS THEN
                                v_log_success := FALSE;
                                v_error_message := 'Logging failed: ' || SQLERRM;
                            END;
                        END IF;

                        RETURN QUERY SELECT 'SUCCESS', 'Blockout updated successfully', v_id;
                    END IF;
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
        DB::unprepared('DROP FUNCTION IF EXISTS insert_or_update_asset_availability_blockout_schedule(BIGINT, TIMESTAMPTZ, TIMESTAMPTZ, BIGINT, VARCHAR, TEXT, BOOLEAN, VARCHAR, JSONB, BIGINT, BOOLEAN, BIGINT, BIGINT, TIMESTAMPTZ, VARCHAR, TIMESTAMPTZ);');
    }
};