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
                WHERE proname = 'insert_or_update_employee_asset_scheduling'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION insert_or_update_employee_asset_scheduling(
            IN p_asset_id BIGINT,
            IN p_start_datetime TIMESTAMPTZ,
            IN p_end_datetime TIMESTAMPTZ,
            IN p_tenant_id BIGINT,
            IN p_assignee_type VARCHAR DEFAULT 'Individual',
            IN p_employee_id JSONB DEFAULT NULL,
            IN p_note TEXT DEFAULT NULL,
            IN p_publish_status VARCHAR DEFAULT 'DRAFT',
            IN p_recurring_enabled BOOLEAN DEFAULT FALSE,
            IN p_recurring_pattern VARCHAR DEFAULT NULL,
            IN p_recurring_config JSONB DEFAULT NULL,
            IN p_created_by BIGINT DEFAULT NULL,
            IN p_user_name VARCHAR DEFAULT NULL,
            IN p_is_active BOOLEAN DEFAULT TRUE,
            IN p_current_time TIMESTAMPTZ DEFAULT now(),
            IN p_id BIGINT DEFAULT NULL
        ) RETURNS TABLE (
            status TEXT,
            message TEXT,
            schedule_id BIGINT
        ) LANGUAGE plpgsql AS $$
        DECLARE
            v_id BIGINT;
            v_new_data JSONB;
            v_log_data JSONB;
            v_log_success BOOLEAN;
            v_error_message TEXT;
            v_employee JSONB;
            v_employee_id_val BIGINT;
            v_occurrence_id BIGINT;
            v_conflict_count INTEGER;
        BEGIN
            -- Validate asset_id and tenant_id
            IF p_asset_id IS NULL OR p_tenant_id IS NULL OR p_asset_id <= 0 OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Invalid asset or tenant ID'::TEXT, NULL::BIGINT;
                RETURN;
            END IF;

            -- Validate employee_id based on assignee_type
            IF p_assignee_type = 'Individual' THEN
                IF p_employee_id IS NULL OR jsonb_typeof(p_employee_id) != 'number' THEN
                    RETURN QUERY SELECT 'FAILURE'::TEXT, 'Employee ID must be a single number for Individual assignee type'::TEXT, NULL::BIGINT;
                    RETURN;
                END IF;
            ELSIF p_assignee_type = 'Group' THEN
                IF p_employee_id IS NULL OR jsonb_typeof(p_employee_id) != 'array' OR jsonb_array_length(p_employee_id) = 0 THEN
                    RETURN QUERY SELECT 'FAILURE'::TEXT, 'Employee ID must be an array with at least one employee for Group assignee type'::TEXT, NULL::BIGINT;
                    RETURN;
                END IF;
            ELSE
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Invalid assignee type. Must be either Individual or Group'::TEXT, NULL::BIGINT;
                RETURN;
            END IF;

            -- Check for scheduling conflicts (overlapping time slots for the same asset)
            IF p_id IS NULL THEN
                -- For new schedules, check if there's any overlap
                SELECT COUNT(*) INTO v_conflict_count
                FROM employee_asset_scheduling
                WHERE asset_id = p_asset_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL
                AND is_active = TRUE
                AND (
                    (start_datetime <= p_start_datetime AND end_datetime > p_start_datetime)
                    OR (start_datetime < p_end_datetime AND end_datetime >= p_end_datetime)
                    OR (start_datetime >= p_start_datetime AND end_datetime <= p_end_datetime)
                );
            ELSE
                -- For updates, check if there's any overlap excluding the current schedule
                SELECT COUNT(*) INTO v_conflict_count
                FROM employee_asset_scheduling
                WHERE asset_id = p_asset_id
                AND tenant_id = p_tenant_id
                AND id != p_id
                AND deleted_at IS NULL
                AND is_active = TRUE
                AND (
                    (start_datetime <= p_start_datetime AND end_datetime > p_start_datetime)
                    OR (start_datetime < p_end_datetime AND end_datetime >= p_end_datetime)
                    OR (start_datetime >= p_start_datetime AND end_datetime <= p_end_datetime)
                );
            END IF;

            IF v_conflict_count > 0 THEN
                RETURN QUERY SELECT 'error'::TEXT, 'Schedule conflict: another schedule exists in this time slot for this asset.'::TEXT, NULL::BIGINT;
                RETURN;
            END IF;

            IF p_id IS NULL THEN
                -- Insert new schedule
                INSERT INTO employee_asset_scheduling(
                    asset_id, start_datetime, end_datetime, status, "Note",
                    recurring_enabled, recurring_pattern, recurring_config,
                    created_by, is_active, tenant_id, created_at, updated_at
                ) VALUES (
                    p_asset_id, p_start_datetime, p_end_datetime, p_publish_status, p_note,
                    p_recurring_enabled, p_recurring_pattern, p_recurring_config,
                    p_created_by, p_is_active, p_tenant_id, p_current_time, p_current_time
                ) RETURNING id INTO v_id;

                -- Insert employee relationships
                IF p_assignee_type = 'Individual' THEN
                    -- Single employee
                    v_employee_id_val := (p_employee_id::TEXT)::BIGINT;
                    INSERT INTO asset_schedule_related_employees(employee_id, asset_schedule_id, created_at, updated_at)
                    VALUES (v_employee_id_val, v_id, p_current_time, p_current_time);
                ELSIF p_assignee_type = 'Group' THEN
                    -- Multiple employees
                    FOR v_employee IN SELECT * FROM jsonb_array_elements(p_employee_id)
                    LOOP
                        v_employee_id_val := (v_employee::TEXT)::BIGINT;
                        INSERT INTO asset_schedule_related_employees(employee_id, asset_schedule_id, created_at, updated_at)
                        VALUES (v_employee_id_val, v_id, p_current_time, p_current_time);
                    END LOOP;
                END IF;

                -- Insert occurrence record
                INSERT INTO employee_asset_scheduling_occurrences(
                    schedule_id, asset_id, occurrence_start, occurrence_end,
                    is_cancelled, isactive, tenant_id, created_at, updated_at
                ) VALUES (
                    v_id, p_asset_id, p_start_datetime, p_end_datetime,
                    FALSE, p_is_active, p_tenant_id, p_current_time, p_current_time
                ) RETURNING id INTO v_occurrence_id;

                -- Logging
                v_new_data := jsonb_build_object(
                    'id', v_id,
                    'asset_id', p_asset_id,
                    'start_datetime', p_start_datetime,
                    'end_datetime', p_end_datetime,
                    'status', p_publish_status,
                    'note', p_note,
                    'assignee_type', p_assignee_type,
                    'employee_id', p_employee_id,
                    'recurring_enabled', p_recurring_enabled,
                    'recurring_pattern', p_recurring_pattern,
                    'recurring_config', p_recurring_config,
                    'created_by', p_created_by,
                    'is_active', p_is_active,
                    'tenant_id', p_tenant_id,
                    'occurrence_id', v_occurrence_id
                );
                v_log_data := jsonb_build_object(
                    'schedule_id', v_id,
                    'new_data', v_new_data
                );
                IF p_created_by IS NOT NULL AND p_user_name IS NOT NULL THEN
                    BEGIN
                        PERFORM log_activity(
                            'employee_asset_scheduling.created',
                            'Employee asset scheduling created by ' || p_user_name || ': ' || v_id,
                            'employee_asset_scheduling',
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

                RETURN QUERY SELECT 'SUCCESS'::TEXT, 'Employee asset scheduling created successfully'::TEXT, v_id;
            ELSE
                -- Update existing schedule
                UPDATE employee_asset_scheduling SET
                    asset_id = p_asset_id,
                    start_datetime = p_start_datetime,
                    end_datetime = p_end_datetime,
                    status = p_publish_status,
                    "Note" = p_note,
                    recurring_enabled = p_recurring_enabled,
                    recurring_pattern = p_recurring_pattern,
                    recurring_config = p_recurring_config,
                    created_by = p_created_by,
                    is_active = p_is_active,
                    tenant_id = p_tenant_id,
                    updated_at = p_current_time
                WHERE id = p_id AND tenant_id = p_tenant_id AND deleted_at IS NULL
                RETURNING id INTO v_id;

                IF v_id IS NULL THEN
                    RETURN QUERY SELECT 'FAILURE'::TEXT, 'Schedule not found for update'::TEXT, NULL::BIGINT;
                ELSE
                    -- Delete old employee relationships and insert new ones
                    DELETE FROM asset_schedule_related_employees WHERE asset_schedule_id = p_id;

                    IF p_assignee_type = 'Individual' THEN
                        -- Single employee
                        v_employee_id_val := (p_employee_id::TEXT)::BIGINT;
                        INSERT INTO asset_schedule_related_employees(employee_id, asset_schedule_id, created_at, updated_at)
                        VALUES (v_employee_id_val, v_id, p_current_time, p_current_time);
                    ELSIF p_assignee_type = 'Group' THEN
                        -- Multiple employees
                        FOR v_employee IN SELECT * FROM jsonb_array_elements(p_employee_id)
                        LOOP
                            v_employee_id_val := (v_employee::TEXT)::BIGINT;
                            INSERT INTO asset_schedule_related_employees(employee_id, asset_schedule_id, created_at, updated_at)
                            VALUES (v_employee_id_val, v_id, p_current_time, p_current_time);
                        END LOOP;
                    END IF;

                    -- Update occurrence record
                    UPDATE employee_asset_scheduling_occurrences SET
                        asset_id = p_asset_id,
                        occurrence_start = p_start_datetime,
                        occurrence_end = p_end_datetime,
                        isactive = p_is_active,
                        updated_at = p_current_time
                    WHERE schedule_id = p_id AND tenant_id = p_tenant_id
                    RETURNING id INTO v_occurrence_id;

                    -- If occurrence doesn't exist, create it
                    IF v_occurrence_id IS NULL THEN
                        INSERT INTO employee_asset_scheduling_occurrences(
                            schedule_id, asset_id, occurrence_start, occurrence_end,
                            is_cancelled, isactive, tenant_id, created_at, updated_at
                        ) VALUES (
                            v_id, p_asset_id, p_start_datetime, p_end_datetime,
                            FALSE, p_is_active, p_tenant_id, p_current_time, p_current_time
                        ) RETURNING id INTO v_occurrence_id;
                    END IF;

                    -- Logging
                    v_new_data := jsonb_build_object(
                        'id', v_id,
                        'asset_id', p_asset_id,
                        'start_datetime', p_start_datetime,
                        'end_datetime', p_end_datetime,
                        'status', p_publish_status,
                        'note', p_note,
                        'assignee_type', p_assignee_type,
                        'employee_id', p_employee_id,
                        'recurring_enabled', p_recurring_enabled,
                        'recurring_pattern', p_recurring_pattern,
                        'recurring_config', p_recurring_config,
                        'created_by', p_created_by,
                        'is_active', p_is_active,
                        'tenant_id', p_tenant_id,
                        'occurrence_id', v_occurrence_id
                    );
                    v_log_data := jsonb_build_object(
                        'schedule_id', v_id,
                        'new_data', v_new_data
                    );
                    IF p_created_by IS NOT NULL AND p_user_name IS NOT NULL THEN
                        BEGIN
                            PERFORM log_activity(
                                'employee_asset_scheduling.updated',
                                'Employee asset scheduling updated by ' || p_user_name || ': ' || v_id,
                                'employee_asset_scheduling',
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

                    RETURN QUERY SELECT 'SUCCESS'::TEXT, 'Employee asset scheduling updated successfully'::TEXT, v_id;
                END IF;
            END IF;
        EXCEPTION
            WHEN OTHERS THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, ('Error: ' || SQLERRM)::TEXT, NULL::BIGINT;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS insert_or_update_employee_asset_scheduling(BIGINT, TIMESTAMPTZ, TIMESTAMPTZ, BIGINT, VARCHAR, JSONB, TEXT, VARCHAR, BOOLEAN, VARCHAR, JSONB, BIGINT, VARCHAR, BOOLEAN, TIMESTAMPTZ, BIGINT)');
    }
};
