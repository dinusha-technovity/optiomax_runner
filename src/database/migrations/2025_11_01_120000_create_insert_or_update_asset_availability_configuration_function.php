<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<SQL
        CREATE OR REPLACE FUNCTION insert_or_update_asset_availability_configuration(
            IN p_asset_id BIGINT,
            IN p_tenant_id BIGINT,
            IN p_id BIGINT DEFAULT NULL,
            IN p_visibility_id BIGINT DEFAULT NULL,
            IN p_approval_type_id BIGINT DEFAULT NULL,
            IN p_rate NUMERIC DEFAULT NULL,
            IN p_rate_currency_type_id BIGINT DEFAULT NULL,
            IN p_rate_period_type_id BIGINT DEFAULT NULL,
            IN p_deposit_required BOOLEAN DEFAULT FALSE,
            IN p_deposit_amount NUMERIC DEFAULT NULL,
            IN p_attachment JSONB DEFAULT NULL,
            IN p_cancellation_enabled BOOLEAN DEFAULT FALSE,
            IN p_cancellation_notice_period INTEGER DEFAULT NULL,
            IN p_cancellation_notice_period_type BIGINT DEFAULT NULL,
            IN p_cancellation_fee_enabled BOOLEAN DEFAULT FALSE,
            IN p_cancellation_fee_type BIGINT DEFAULT NULL,
            IN p_cancellation_fee_amount NUMERIC DEFAULT NULL,
            IN p_cancellation_fee_percentage NUMERIC DEFAULT 0,
            IN p_asset_booking_cancellation_refund_policy_type BIGINT DEFAULT NULL,
            IN p_term_types JSONB DEFAULT NULL,
            IN p_required_documents JSONB DEFAULT NULL,
            IN p_created_by BIGINT DEFAULT NULL,
            IN p_user_name VARCHAR DEFAULT NULL,
            IN p_is_active BOOLEAN DEFAULT TRUE,
            IN p_current_time TIMESTAMPTZ DEFAULT now()
        ) RETURNS TABLE (
            status TEXT,
            message TEXT,
            config_id BIGINT
        ) LANGUAGE plpgsql AS $$
        DECLARE
            v_id BIGINT;
            v_new_data JSONB;
            v_log_data JSONB;
            v_log_success BOOLEAN;
            v_error_message TEXT;
            v_term_type JSONB;
            v_required_doc JSONB;
        BEGIN
            -- Validate asset_id and tenant_id
            IF p_asset_id IS NULL OR p_tenant_id IS NULL OR p_asset_id <= 0 OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT 'FAILURE', 'Invalid asset or tenant ID', NULL::BIGINT;
                RETURN;
            END IF;

            -- Verify asset belongs to tenant
            IF NOT EXISTS (
                SELECT 1 FROM asset_items ai 
                WHERE ai.id = p_asset_id AND ai.tenant_id = p_tenant_id
            ) THEN
                RETURN QUERY SELECT 'FAILURE', 'Asset not found or does not belong to tenant', NULL::BIGINT;
                RETURN;
            END IF;

            IF p_id IS NULL THEN
                -- Insert new configuration
                INSERT INTO asset_availability_configurations(
                    asset_items_id, visibility_id, approval_type_id, rate, rate_currency_type_id,
                    rate_period_type_id, deposit_required, deposit_amount, attachment,
                    cancellation_enabled, cancellation_notice_period, cancellation_notice_period_type,
                    cancellation_fee_enabled, cancellation_fee_type, cancellation_fee_amount,
                    cancellation_fee_percentage, asset_booking_cancellation_refund_policy_type,
                    tenant_id, created_at, updated_at
                ) VALUES (
                    p_asset_id, p_visibility_id, p_approval_type_id, p_rate, p_rate_currency_type_id,
                    p_rate_period_type_id, p_deposit_required, p_deposit_amount, p_attachment,
                    p_cancellation_enabled, p_cancellation_notice_period, p_cancellation_notice_period_type,
                    p_cancellation_fee_enabled, p_cancellation_fee_type, p_cancellation_fee_amount,
                    p_cancellation_fee_percentage, p_asset_booking_cancellation_refund_policy_type,
                    p_tenant_id, p_current_time, p_current_time
                ) RETURNING id INTO v_id;

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
                    'tenant_id', p_tenant_id,
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
                );
                v_log_data := jsonb_build_object(
                    'config_id', v_id,
                    'new_data', v_new_data
                );

                IF p_created_by IS NOT NULL AND p_user_name IS NOT NULL THEN
                    BEGIN
                        PERFORM log_activity(
                            'asset_availability_configuration.created',
                            'Configuration created by ' || p_user_name || ' for asset: ' || p_asset_id,
                            'asset_availability_configuration',
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

                RETURN QUERY SELECT 'SUCCESS', 'Configuration created successfully', v_id;
            ELSE
                -- Update existing configuration
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
                WHERE id = p_id AND asset_items_id = p_asset_id AND tenant_id = p_tenant_id
                RETURNING id INTO v_id;

                IF v_id IS NULL THEN
                    RETURN QUERY SELECT 'FAILURE', 'Configuration not found for update', NULL::BIGINT;
                ELSE
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
                        'tenant_id', p_tenant_id,
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
                    );
                    v_log_data := jsonb_build_object(
                        'config_id', v_id,
                        'new_data', v_new_data
                    );

                    IF p_created_by IS NOT NULL AND p_user_name IS NOT NULL THEN
                        BEGIN
                            PERFORM log_activity(
                                'asset_availability_configuration.updated',
                                'Configuration updated by ' || p_user_name || ' for asset: ' || p_asset_id,
                                'asset_availability_configuration',
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

                    RETURN QUERY SELECT 'SUCCESS', 'Configuration updated successfully', v_id;
                END IF;
            END IF;
        END;
        $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS insert_or_update_asset_availability_configuration(BIGINT, BIGINT, BIGINT, BIGINT, BIGINT, NUMERIC, BIGINT, BIGINT, BOOLEAN, NUMERIC, JSONB, BOOLEAN, INTEGER, BIGINT, BOOLEAN, BIGINT, NUMERIC, NUMERIC, BIGINT, JSONB, JSONB, BIGINT, VARCHAR, BOOLEAN, TIMESTAMPTZ)');
    }
};
