<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
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
                WHERE proname = 'insert_or_update_asset_availability_schedule'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION insert_or_update_asset_availability_schedule(
            IN p_asset_id BIGINT,
            IN p_start_datetime TIMESTAMPTZ,
            IN p_end_datetime TIMESTAMPTZ,
            IN p_tenant_id BIGINT,
            IN p_title VARCHAR(250) DEFAULT NULL,
            IN p_id BIGINT DEFAULT NULL,
            IN p_publish_status VARCHAR DEFAULT 'DRAFT',
            IN p_visibility_id BIGINT DEFAULT NULL,
            IN p_approval_type_id BIGINT DEFAULT NULL,
            IN p_rate NUMERIC DEFAULT NULL,
            IN p_rate_currency_type_id BIGINT DEFAULT NULL,
            IN p_rate_period_type_id BIGINT DEFAULT NULL,
            IN p_deposit_required BOOLEAN DEFAULT FALSE,
            IN p_deposit_amount NUMERIC DEFAULT NULL,
            IN p_description TEXT DEFAULT NULL,
            IN p_recurring_enabled BOOLEAN DEFAULT FALSE,
            IN p_recurring_pattern VARCHAR DEFAULT NULL,
            IN p_recurring_config JSONB DEFAULT NULL,
            IN p_attachment JSONB DEFAULT NULL,
            IN p_created_by BIGINT DEFAULT NULL,
            IN p_user_name VARCHAR DEFAULT NULL,
            IN p_is_active BOOLEAN DEFAULT TRUE,
            IN p_current_time TIMESTAMPTZ DEFAULT now(),
            IN p_term_types JSONB DEFAULT NULL,
            IN p_required_documents JSONB DEFAULT NULL,
            IN p_cancellation_enabled BOOLEAN DEFAULT FALSE,
            IN p_cancellation_notice_period INTEGER DEFAULT NULL,
            IN p_cancellation_notice_period_type BIGINT DEFAULT NULL,
            IN p_cancellation_fee_enabled BOOLEAN DEFAULT FALSE,
            IN p_cancellation_fee_type BIGINT DEFAULT NULL,
            IN p_cancellation_fee_amount NUMERIC DEFAULT NULL,
            IN p_cancellation_fee_percentage NUMERIC DEFAULT 0,
            IN p_asset_booking_cancellation_refund_policy_type BIGINT DEFAULT NULL
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
            v_term_type JSONB;
            v_required_doc JSONB;
            v_config_id BIGINT;
        BEGIN
            -- Validate asset_id and tenant_id
            IF p_asset_id IS NULL OR p_tenant_id IS NULL OR p_asset_id <= 0 OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT 'FAILURE', 'Invalid asset or tenant ID', NULL::BIGINT;
                RETURN;
            END IF;

            IF p_id IS NULL THEN
                -- Insert new schedule (only core fields)
                INSERT INTO asset_availability_schedules(
                    asset_id, start_datetime, end_datetime, title, publish_status,
                    description, recurring_enabled, recurring_pattern, recurring_config,
                    created_by, is_active, tenant_id, created_at, updated_at
                ) VALUES (
                    p_asset_id, p_start_datetime, p_end_datetime, p_title, p_publish_status,
                    p_description, p_recurring_enabled, p_recurring_pattern, p_recurring_config,
                    p_created_by, p_is_active, p_tenant_id, p_current_time, p_current_time
                ) RETURNING id INTO v_id;

                -- Insert configuration row
                INSERT INTO asset_availability_configurations(
                    asset_items_id, visibility_id, approval_type_id, rate, rate_currency_type_id,
                    rate_period_type_id, deposit_required, deposit_amount, attachment,
                    cancellation_enabled, cancellation_notice_period, cancellation_notice_period_type,
                    cancellation_fee_enabled, cancellation_fee_type, cancellation_fee_amount,
                    cancellation_fee_percentage, asset_booking_cancellation_refund_policy_type, tenant_id, created_at, updated_at
                ) VALUES (
                    p_asset_id, p_visibility_id, p_approval_type_id, p_rate, p_rate_currency_type_id,
                    p_rate_period_type_id, p_deposit_required, p_deposit_amount, p_attachment,
                    p_cancellation_enabled, p_cancellation_notice_period, p_cancellation_notice_period_type,
                    p_cancellation_fee_enabled, p_cancellation_fee_type, p_cancellation_fee_amount,
                    p_cancellation_fee_percentage, p_asset_booking_cancellation_refund_policy_type, p_tenant_id, p_current_time, p_current_time
                ) RETURNING id INTO v_config_id;

                -- Insert term types if provided
                IF p_term_types IS NOT NULL THEN
                    FOR v_term_type IN SELECT * FROM jsonb_array_elements(p_term_types)
                    LOOP
                        INSERT INTO asset_availability_schedule_term_types(asset_items_id, term_type_id, created_at, updated_at)
                        VALUES (p_asset_id, (v_term_type->>'id')::BIGINT, p_current_time, p_current_time);
                    END LOOP;
                END IF;

                -- Insert required documents if provided
                IF p_required_documents IS NOT NULL THEN
                    FOR v_required_doc IN SELECT * FROM jsonb_array_elements(p_required_documents)
                    LOOP
                        INSERT INTO asset_availability_required_documents_for_booking(asset_items_id, document_category_field_id, created_at, updated_at)
                        VALUES (p_asset_id, (v_required_doc->>'field_id')::BIGINT, p_current_time, p_current_time);
                    END LOOP;
                END IF;

                -- Logging
                v_new_data := jsonb_build_object(
                    'id', v_id,
                    'asset_id', p_asset_id,
                    'start_datetime', p_start_datetime,
                    'end_datetime', p_end_datetime,
                    'title', p_title,
                    'publish_status', p_publish_status,
                    'description', p_description,
                    'recurring_enabled', p_recurring_enabled,
                    'recurring_pattern', p_recurring_pattern,
                    'recurring_config', p_recurring_config,
                    'created_by', p_created_by,
                    'is_active', p_is_active,
                    'tenant_id', p_tenant_id,
                    'configuration', jsonb_build_object(
                        'visibility_id', p_visibility_id,
                        'approval_type_id', p_approval_type_id,
                        'rate', p_rate,
                        'rate_currency_type_id', p_rate_currency_type_id,
                        'rate_period_type_id', p_rate_period_type_id,
                        'deposit_required', p_deposit_required,
                        'deposit_amount', p_deposit_amount,
                        'attachment', p_attachment,
                        'cancellation_enabled', p_cancellation_enabled,
                        'cancellation_notice_period', p_cancellation_notice_period,
                        'cancellation_notice_period_type', p_cancellation_notice_period_type,
                        'cancellation_fee_enabled', p_cancellation_fee_enabled,
                        'cancellation_fee_type', p_cancellation_fee_type,
                        'cancellation_fee_amount', p_cancellation_fee_amount,
                        'cancellation_fee_percentage', p_cancellation_fee_percentage,
                        'asset_booking_cancellation_refund_policy_type', p_asset_booking_cancellation_refund_policy_type
                    )
                );
                v_log_data := jsonb_build_object(
                    'schedule_id', v_id,
                    'new_data', v_new_data
                );
                IF p_created_by IS NOT NULL AND p_user_name IS NOT NULL THEN
                    BEGIN
                        PERFORM log_activity(
                            'asset_availability_schedule.created',
                            'Schedule created by ' || p_user_name || ': ' || v_id,
                            'asset_availability_schedule',
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

                RETURN QUERY SELECT 'SUCCESS', 'Schedule created successfully', v_id;
            ELSE
                -- Update existing schedule (core fields)
                UPDATE asset_availability_schedules SET
                    asset_id = p_asset_id,
                    start_datetime = p_start_datetime,
                    end_datetime = p_end_datetime,
                    title = p_title,
                    publish_status = p_publish_status,
                    description = p_description,
                    recurring_enabled = p_recurring_enabled,
                    recurring_pattern = p_recurring_pattern,
                    recurring_config = p_recurring_config,
                    created_by = p_created_by,
                    is_active = p_is_active,
                    tenant_id = p_tenant_id,
                    updated_at = p_current_time
                WHERE id = p_id
                RETURNING id INTO v_id;

                IF v_id IS NULL THEN
                    RETURN QUERY SELECT 'FAILURE', 'Schedule not found for update', NULL::BIGINT;
                ELSE
                    -- Update or insert configuration row
                    UPDATE asset_availability_configurations SET
                        visibility_id = p_visibility_id,
                        approval_type_id = p_approval_type_id,
                        rate = p_rate,
                        rate_currency_type_id = p_rate_currency_type_id,
                        rate_period_type_id = p_rate_period_type_id,
                        deposit_required = p_deposit_required,
                        deposit_amount = p_deposit_amount,
                        attachment = p_attachment,
                        cancellation_enabled = p_cancellation_enabled,
                        cancellation_notice_period = p_cancellation_notice_period,
                        cancellation_notice_period_type = p_cancellation_notice_period_type,
                        cancellation_fee_enabled = p_cancellation_fee_enabled,
                        cancellation_fee_type = p_cancellation_fee_type,
                        cancellation_fee_amount = p_cancellation_fee_amount,
                        cancellation_fee_percentage = p_cancellation_fee_percentage,
                        asset_booking_cancellation_refund_policy_type = p_asset_booking_cancellation_refund_policy_type,
                        updated_at = p_current_time
                    WHERE asset_items_id = p_asset_id;

                    IF NOT FOUND THEN
                        INSERT INTO asset_availability_configurations(
                            asset_items_id, visibility_id, approval_type_id, rate, rate_currency_type_id,
                            rate_period_type_id, deposit_required, deposit_amount, attachment,
                            cancellation_enabled, cancellation_notice_period, cancellation_notice_period_type,
                            cancellation_fee_enabled, cancellation_fee_type, cancellation_fee_amount,
                            cancellation_fee_percentage, asset_booking_cancellation_refund_policy_type, created_at, updated_at
                        ) VALUES (
                            p_asset_id, p_visibility_id, p_approval_type_id, p_rate, p_rate_currency_type_id,
                            p_rate_period_type_id, p_deposit_required, p_deposit_amount, p_attachment,
                            p_cancellation_enabled, p_cancellation_notice_period, p_cancellation_notice_period_type,
                            p_cancellation_fee_enabled, p_cancellation_fee_type, p_cancellation_fee_amount,
                            p_cancellation_fee_percentage, p_asset_booking_cancellation_refund_policy_type, p_current_time, p_current_time
                        ) RETURNING id INTO v_config_id;
                    END IF;

                    -- Delete old term types and insert new ones
                    DELETE FROM asset_availability_schedule_term_types WHERE asset_items_id = p_asset_id;
                    IF p_term_types IS NOT NULL THEN
                        FOR v_term_type IN SELECT * FROM jsonb_array_elements(p_term_types)
                        LOOP
                            INSERT INTO asset_availability_schedule_term_types(asset_items_id, term_type_id, created_at, updated_at)
                            VALUES (p_asset_id, (v_term_type->>'id')::BIGINT, p_current_time, p_current_time);
                        END LOOP;
                    END IF;

                    -- Delete old required documents and insert new ones
                    DELETE FROM asset_availability_required_documents_for_booking WHERE asset_items_id = p_asset_id;
                    IF p_required_documents IS NOT NULL THEN
                        FOR v_required_doc IN SELECT * FROM jsonb_array_elements(p_required_documents)
                        LOOP
                            INSERT INTO asset_availability_required_documents_for_booking(asset_items_id, document_category_field_id, created_at, updated_at)
                            VALUES (p_asset_id, (v_required_doc->>'field_id')::BIGINT, p_current_time, p_current_time);
                        END LOOP;
                    END IF;

                    -- Logging
                    v_new_data := jsonb_build_object(
                        'id', v_id,
                        'asset_id', p_asset_id,
                        'start_datetime', p_start_datetime,
                        'end_datetime', p_end_datetime,
                        'title', p_title,
                        'publish_status', p_publish_status,
                        'description', p_description,
                        'recurring_enabled', p_recurring_enabled,
                        'recurring_pattern', p_recurring_pattern,
                        'recurring_config', p_recurring_config,
                        'created_by', p_created_by,
                        'is_active', p_is_active,
                        'tenant_id', p_tenant_id,
                        'configuration', jsonb_build_object(
                            'visibility_id', p_visibility_id,
                            'approval_type_id', p_approval_type_id,
                            'rate', p_rate,
                            'rate_currency_type_id', p_rate_currency_type_id,
                            'rate_period_type_id', p_rate_period_type_id,
                            'deposit_required', p_deposit_required,
                            'deposit_amount', p_deposit_amount,
                            'attachment', p_attachment,
                            'cancellation_enabled', p_cancellation_enabled,
                            'cancellation_notice_period', p_cancellation_notice_period,
                            'cancellation_notice_period_type', p_cancellation_notice_period_type,
                            'cancellation_fee_enabled', p_cancellation_fee_enabled,
                            'cancellation_fee_type', p_cancellation_fee_type,
                            'cancellation_fee_amount', p_cancellation_fee_amount,
                            'cancellation_fee_percentage', p_cancellation_fee_percentage,
                            'asset_booking_cancellation_refund_policy_type', p_asset_booking_cancellation_refund_policy_type
                        )
                    );
                    v_log_data := jsonb_build_object(
                        'schedule_id', v_id,
                        'new_data', v_new_data
                    );
                    IF p_created_by IS NOT NULL AND p_user_name IS NOT NULL THEN
                        BEGIN
                            PERFORM log_activity(
                                'asset_availability_schedule.updated',
                                'Schedule updated by ' || p_user_name || ': ' || v_id,
                                'asset_availability_schedule',
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

                    RETURN QUERY SELECT 'SUCCESS', 'Schedule updated successfully', v_id;
                END IF;
            END IF;
        END;
        $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS insert_or_update_asset_availability_schedule(BIGINT, TIMESTAMPTZ, TIMESTAMPTZ, BIGINT, VARCHAR, BIGINT, VARCHAR, BIGINT, BIGINT, NUMERIC, BIGINT, BIGINT, BOOLEAN, NUMERIC, TEXT, BOOLEAN, VARCHAR, JSONB, JSONB, BIGINT, VARCHAR, BOOLEAN, TIMESTAMPTZ, JSONB, JSONB, BOOLEAN, INTEGER, BIGINT, BOOLEAN, BIGINT, NUMERIC, NUMERIC, BIGINT)');
    }
};