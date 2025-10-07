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
        // DB::unprepared(<<<SQL
        //     DO $$
        //     DECLARE
        //         r RECORD;
        //     BEGIN
        //         FOR r IN
        //             SELECT oid::regprocedure::text AS func_signature
        //             FROM pg_proc
        //             WHERE proname = 'insert_or_update_asset_booking_with_time_slots'
        //         LOOP
        //             EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
        //         END LOOP;
        //     END$$;

        //     CREATE OR REPLACE FUNCTION insert_or_update_asset_booking_with_time_slots(
        //         IN p_parent_booking_id BIGINT,
        //         IN p_organization_id BIGINT,
        //         IN p_asset_id BIGINT,
        //         IN p_booked_by BIGINT,
        //         IN p_booking_status VARCHAR,
        //         IN p_booking_type_id BIGINT,
        //         IN p_location_latitude VARCHAR,
        //         IN p_location_longitude VARCHAR,
        //         IN p_description TEXT,
        //         IN p_start_datetime TIMESTAMPTZ,
        //         IN p_end_datetime TIMESTAMPTZ,
        //         IN p_duration_hours NUMERIC,
        //         IN p_contact_email VARCHAR,
        //         IN p_contact_phone VARCHAR,
        //         IN p_asset_booking_purpose_or_use_case_type_id BIGINT,
        //         IN p_custom_purpose_name TEXT,
        //         IN p_custom_purpose_description TEXT,
        //         IN p_attendees_count INTEGER,
        //         IN p_special_requirements TEXT,
        //         IN p_rate_applied NUMERIC,
        //         IN p_currency_code BIGINT,
        //         IN p_total_cost NUMERIC,
        //         IN p_deposit_required BOOLEAN,
        //         IN p_deposit_amount NUMERIC,
        //         IN p_deposit_paid BOOLEAN,
        //         IN p_deposit_paid_at TIMESTAMPTZ,
        //         IN p_approval_required BOOLEAN,
        //         IN p_approved_by BIGINT,
        //         IN p_approved_at TIMESTAMPTZ,
        //         IN p_approval_notes TEXT,
        //         IN p_cancelled_at TIMESTAMPTZ,
        //         IN p_cancelled_by BIGINT,
        //         IN p_cancellation_reason TEXT,
        //         IN p_cancellation_fee NUMERIC,
        //         IN p_checked_in_at TIMESTAMPTZ,
        //         IN p_checked_in_by BIGINT,
        //         IN p_checked_out_at TIMESTAMPTZ,
        //         IN p_checked_out_by BIGINT,
        //         IN p_is_multi_slot BOOLEAN,
        //         IN p_slot_sequence INTEGER,
        //         IN p_reminder_sent BOOLEAN,
        //         IN p_reminder_sent_at TIMESTAMPTZ,
        //         IN p_tenant_id BIGINT,
        //         IN p_deleted_at TIMESTAMPTZ,
        //         IN p_locale VARCHAR,
        //         IN p_partition_key VARCHAR,
        //         IN p_custom_attributes JSONB,
        //         IN p_created_ip INET,
        //         IN p_updated_ip INET,
        //         IN p_time_slots JSONB,
        //         IN p_user_id BIGINT,
        //         IN p_user_name VARCHAR,
        //         IN p_current_time TIMESTAMPTZ DEFAULT now(),
        //         IN p_id BIGINT DEFAULT NULL,
        //         IN p_prefix TEXT DEFAULT 'ASBK'
        //     )
        //     RETURNS TABLE (
        //         status TEXT,
        //         message TEXT,
        //         booking_id BIGINT,
        //         booking_register_number VARCHAR,
        //         time_slot_id BIGINT,
        //         slot_approval_status TEXT,
        //         slot_data JSONB
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     DECLARE
        //         curr_val BIGINT;
        //         new_booking_id BIGINT;
        //         new_register_number VARCHAR(100);
        //         slot_record JSONB;
        //         idx INT := 0;
        //         slot_count INT;
        //         approval_type BIGINT;
        //         approval_status TEXT;

        //         v_log_success BOOLEAN;
        //         v_error_message TEXT;
        //         v_old_data JSONB;
        //         v_new_data JSONB;
        //         v_log_data JSONB;
        //         v_action_type TEXT;
        //     BEGIN
        //         -- Validation
        //         IF p_asset_id IS NULL THEN
        //             RETURN QUERY SELECT
        //                 'FAILURE'::TEXT,
        //                 'Asset ID cannot be null'::TEXT,
        //                 NULL::BIGINT,
        //                 NULL::VARCHAR,
        //                 NULL::BIGINT,
        //                 NULL::TEXT,
        //                 NULL::JSONB;
        //             RETURN;
        //         END IF;
        //         IF p_organization_id IS NULL THEN
        //             RETURN QUERY SELECT
        //                 'FAILURE'::TEXT,
        //                 'Organization ID cannot be null'::TEXT,
        //                 NULL::BIGINT,
        //                 NULL::VARCHAR,
        //                 NULL::BIGINT,
        //                 NULL::TEXT,
        //                 NULL::JSONB;
        //             RETURN;
        //         END IF;
        //         IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
        //             RETURN QUERY SELECT
        //                 'FAILURE'::TEXT,
        //                 'Invalid tenant ID provided'::TEXT,
        //                 NULL::BIGINT,
        //                 NULL::VARCHAR,
        //                 NULL::BIGINT,
        //                 NULL::TEXT,
        //                 NULL::JSONB;
        //             RETURN;
        //         END IF;

        //         IF p_id IS NULL OR p_id = 0 THEN
        //             -- CREATE LOGIC
        //             v_action_type := 'created';

        //             SELECT nextval('asset_booking_register_number_seq') INTO curr_val;
        //             new_register_number := p_prefix || '-' || LPAD(curr_val::TEXT, 6, '0');

        //             INSERT INTO asset_bookings (
        //                 parent_booking_id, organization_id, asset_id, booked_by,
        //                 booking_register_number, booking_status, booking_type_id,
        //                 location_latitude, location_longitude, description,
        //                 start_datetime, end_datetime, duration_hours, contact_email, contact_phone,
        //                 asset_booking_purpose_or_use_case_type_id, custom_purpose_name, custom_purpose_description, attendees_count, special_requirements, rate_applied, currency_code,
        //                 total_cost, deposit_required, deposit_amount, deposit_paid, deposit_paid_at,
        //                 approval_required, approved_by, approved_at, approval_notes,
        //                 cancelled_at, cancelled_by, cancellation_reason, cancellation_fee,
        //                 checked_in_at, checked_in_by, checked_out_at, checked_out_by,
        //                 is_multi_slot, slot_sequence, reminder_sent, reminder_sent_at, tenant_id,
        //                 deleted_at, locale, partition_key, custom_attributes, created_ip, updated_ip, created_at, updated_at
        //             ) VALUES (
        //                 p_parent_booking_id, p_organization_id, p_asset_id, p_booked_by,
        //                 new_register_number, p_booking_status, p_booking_type_id,
        //                 p_location_latitude, p_location_longitude, p_description,
        //                 p_start_datetime, p_end_datetime, p_duration_hours, p_contact_email, p_contact_phone,
        //                 p_asset_booking_purpose_or_use_case_type_id, p_custom_purpose_name, p_custom_purpose_description, p_attendees_count, p_special_requirements, p_rate_applied, p_currency_code,
        //                 p_total_cost, p_deposit_required, p_deposit_amount, p_deposit_paid, p_deposit_paid_at,
        //                 p_approval_required, p_approved_by, p_approved_at, p_approval_notes,
        //                 p_cancelled_at, p_cancelled_by, p_cancellation_reason, p_cancellation_fee,
        //                 p_checked_in_at, p_checked_in_by, p_checked_out_at, p_checked_out_by,
        //                 p_is_multi_slot, p_slot_sequence, p_reminder_sent, p_reminder_sent_at, p_tenant_id,
        //                 p_deleted_at, p_locale, p_partition_key, p_custom_attributes, p_created_ip, p_updated_ip, p_current_time, p_current_time
        //             ) RETURNING id INTO new_booking_id;

        //             -- Insert slots
        //             IF p_time_slots IS NOT NULL AND jsonb_typeof(p_time_slots) = 'array' THEN
        //                 slot_count := jsonb_array_length(p_time_slots);
        //                 WHILE idx < slot_count LOOP
        //                     slot_record := p_time_slots->idx;

        //                     -- Extract approval_type_id and set approval_status logic
        //                     approval_type := (slot_record->>'approvalTypeId')::bigint;
        //                     IF approval_type = 1 THEN
        //                         approval_status := 'APPROVED';
        //                     ELSIF approval_type = 2 THEN
        //                         approval_status := 'PENDING';
        //                     ELSE
        //                         approval_status := 'APPROVED';
        //                     END IF;

        //                     INSERT INTO asset_booking_time_slots (
        //                         booking_id,
        //                         asset_availability_schedule_occurrences_id,
        //                         start_datetime,
        //                         end_datetime,
        //                         duration_hours,
        //                         rate_applied,
        //                         slot_cost,
        //                         sequence_order,
        //                         currency_code,
        //                         approval_type_id,
        //                         approval_status,
        //                         agreement_documents,
        //                         insurance_documents,
        //                         license_documents,
        //                         id_proof_documents,
        //                         any_attachment_document,
        //                         tenant_id,
        //                         partition_key,
        //                         custom_attributes,
        //                         created_ip,
        //                         updated_ip,
        //                         created_at,
        //                         updated_at
        //                     ) VALUES (
        //                         new_booking_id,
        //                         (slot_record->>'assetAvailabilityScheduleOccurrencesId')::bigint,
        //                         (slot_record->>'startDateTime')::timestamptz,
        //                         (slot_record->>'endDateTime')::timestamptz,
        //                         (slot_record->>'duration')::numeric,
        //                         (slot_record->>'rate')::numeric,
        //                         (slot_record->>'slot_cost')::numeric,
        //                         (slot_record->>'sequence_order')::integer,
        //                         (slot_record->>'currency_code')::bigint,
        //                         approval_type,
        //                         approval_status,
        //                         (slot_record->>'agreement_documents')::jsonb,
        //                         (slot_record->>'insurance_documents')::jsonb,
        //                         (slot_record->>'license_documents')::jsonb,
        //                         (slot_record->>'id_proof_documents')::jsonb,
        //                         (slot_record->>'any_attachment_document')::jsonb,
        //                         p_tenant_id,
        //                         (slot_record->>'partition_key')::varchar,
        //                         (slot_record->>'custom_attributes')::jsonb,
        //                         (slot_record->>'created_ip')::inet,
        //                         (slot_record->>'updated_ip')::inet,
        //                         p_current_time,
        //                         p_current_time
        //                     );
        //                     idx := idx + 1;
        //                 END LOOP;
        //             END IF;

        //             -- Build log data
        //             v_new_data := jsonb_build_object(
        //                 'booking_id', new_booking_id,
        //                 'booking_register_number', new_register_number,
        //                 'asset_id', p_asset_id,
        //                 'organization_id', p_organization_id,
        //                 'booked_by', p_booked_by,
        //                 'start_datetime', p_start_datetime,
        //                 'end_datetime', p_end_datetime,
        //                 'duration_hours', p_duration_hours,
        //                 'p_asset_booking_purpose_or_use_case_type_id', p_asset_booking_purpose_or_use_case_type_id,
        //                 'custom_purpose_name', p_custom_purpose_name,
        //                 'custom_purpose_description', p_custom_purpose_description,
        //                 'attendees_count', p_attendees_count,
        //                 'is_multi_slot', p_is_multi_slot,
        //                 'time_slots', p_time_slots
        //             );
        //             v_log_data := jsonb_build_object(
        //                 'new_data', v_new_data
        //             );

        //             IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
        //                 BEGIN
        //                     PERFORM log_activity(
        //                         'asset_booking.created',
        //                         'Booking created by ' || p_user_name || ': ' || new_register_number,
        //                         'asset_booking',
        //                         new_booking_id,
        //                         'user',
        //                         p_user_id,
        //                         v_log_data,
        //                         p_tenant_id
        //                     );
        //                     v_log_success := TRUE;
        //                 EXCEPTION WHEN OTHERS THEN
        //                     v_log_success := FALSE;
        //                     v_error_message := 'Logging failed: ' || SQLERRM;
        //                 END;
        //             END IF;

        //             -- Return booking info
        //             RETURN QUERY SELECT
        //                 'SUCCESS'::TEXT,
        //                 'Booking created successfully'::TEXT,
        //                 new_booking_id,
        //                 new_register_number,
        //                 NULL::BIGINT,
        //                 NULL::TEXT,
        //                 NULL::JSONB;

        //         ELSE
        //             -- UPDATE LOGIC
        //             v_action_type := 'updated';

        //             -- Fetch old data for log
        //             SELECT
        //                 jsonb_build_object(
        //                     'booking_id', ab.id,
        //                     'booking_register_number', ab.booking_register_number,
        //                     'asset_id', ab.asset_id,
        //                     'organization_id', ab.organization_id,
        //                     'booked_by', ab.booked_by,
        //                     'start_datetime', ab.start_datetime,
        //                     'end_datetime', ab.end_datetime,
        //                     'duration_hours', ab.duration_hours,
        //                     'purpose', ab.purpose,
        //                     'attendees_count', ab.attendees_count,
        //                     'is_multi_slot', ab.is_multi_slot
        //                 )
        //             INTO v_old_data
        //             FROM asset_bookings ab WHERE ab.id = p_id;

        //             -- Update booking
        //             UPDATE asset_bookings
        //             SET
        //                 parent_booking_id = p_parent_booking_id,
        //                 organization_id = p_organization_id,
        //                 asset_id = p_asset_id,
        //                 booked_by = p_booked_by,
        //                 booking_status = p_booking_status,
        //                 booking_type_id = p_booking_type_id,
        //                 location_latitude = p_location_latitude,
        //                 location_longitude = p_location_longitude,
        //                 description = p_description,
        //                 start_datetime = p_start_datetime,
        //                 end_datetime = p_end_datetime,
        //                 duration_hours = p_duration_hours,
        //                 contact_email = p_contact_email,
        //                 contact_phone = p_contact_phone,
        //                 asset_booking_purpose_or_use_case_type_id = p_asset_booking_purpose_or_use_case_type_id,
        //                 custom_purpose_name = p_custom_purpose_name,
        //                 custom_purpose_description = p_custom_purpose_description,
        //                 attendees_count = p_attendees_count,
        //                 special_requirements = p_special_requirements,
        //                 rate_applied = p_rate_applied,
        //                 currency_code = p_currency_code,
        //                 total_cost = p_total_cost,
        //                 deposit_required = p_deposit_required,
        //                 deposit_amount = p_deposit_amount,
        //                 deposit_paid = p_deposit_paid,
        //                 deposit_paid_at = p_deposit_paid_at,
        //                 approval_required = p_approval_required,
        //                 approved_by = p_approved_by,
        //                 approved_at = p_approved_at,
        //                 approval_notes = p_approval_notes,
        //                 cancelled_at = p_cancelled_at,
        //                 cancelled_by = p_cancelled_by,
        //                 cancellation_reason = p_cancellation_reason,
        //                 cancellation_fee = p_cancellation_fee,
        //                 checked_in_at = p_checked_in_at,
        //                 checked_in_by = p_checked_in_by,
        //                 checked_out_at = p_checked_out_at,
        //                 checked_out_by = p_checked_out_by,
        //                 is_multi_slot = p_is_multi_slot,
        //                 slot_sequence = p_slot_sequence,
        //                 reminder_sent = p_reminder_sent,
        //                 reminder_sent_at = p_reminder_sent_at,
        //                 tenant_id = p_tenant_id,
        //                 deleted_at = p_deleted_at,
        //                 locale = p_locale,
        //                 partition_key = p_partition_key,
        //                 custom_attributes = p_custom_attributes,
        //                 updated_ip = p_updated_ip,
        //                 updated_at = p_current_time
        //             WHERE id = p_id
        //             RETURNING id, booking_register_number INTO new_booking_id, new_register_number;

        //             IF NOT FOUND THEN
        //                 RETURN QUERY SELECT
        //                     'FAILURE'::TEXT,
        //                     'Booking not found for update'::TEXT,
        //                     NULL::BIGINT,
        //                     NULL::VARCHAR,
        //                     NULL::BIGINT,
        //                     NULL::TEXT,
        //                     NULL::JSONB;
        //                 RETURN;
        //             END IF;

        //             -- Delete old time slots
        //             DELETE FROM asset_booking_time_slots WHERE booking_id = new_booking_id;

        //             -- Insert new time slots
        //             idx := 0;
        //             IF p_time_slots IS NOT NULL AND jsonb_typeof(p_time_slots) = 'array' THEN
        //                 slot_count := jsonb_array_length(p_time_slots);
        //                 WHILE idx < slot_count LOOP
        //                     slot_record := p_time_slots->idx;

        //                     -- Extract approval_type_id and set approval_status logic
        //                     approval_type := (slot_record->>'approvalTypeId')::bigint;
        //                     IF approval_type = 1 THEN
        //                         approval_status := 'APPROVED';
        //                     ELSIF approval_type = 2 THEN
        //                         approval_status := 'PENDING';
        //                     ELSE
        //                         approval_status := 'APPROVED';
        //                     END IF;

        //                     INSERT INTO asset_booking_time_slots (
        //                         booking_id,
        //                         asset_availability_schedule_occurrences_id,
        //                         start_datetime,
        //                         end_datetime,
        //                         duration_hours,
        //                         rate_applied,
        //                         slot_cost,
        //                         sequence_order,
        //                         currency_code,
        //                         approval_type_id,
        //                         approval_status,
        //                         agreement_documents,
        //                         insurance_documents,
        //                         license_documents,
        //                         id_proof_documents,
        //                         any_attachment_document,
        //                         tenant_id,
        //                         partition_key,
        //                         custom_attributes,
        //                         created_ip,
        //                         updated_ip,
        //                         created_at,
        //                         updated_at
        //                     ) VALUES (
        //                         new_booking_id,
        //                         (slot_record->>'assetAvailabilityScheduleOccurrencesId')::bigint,
        //                         (slot_record->>'start_datetime')::timestamptz,
        //                         (slot_record->>'end_datetime')::timestamptz,
        //                         (slot_record->>'duration_hours')::numeric,
        //                         (slot_record->>'rate_applied')::numeric,
        //                         (slot_record->>'slot_cost')::numeric,
        //                         (slot_record->>'sequence_order')::integer,
        //                         (slot_record->>'currency_code')::bigint,
        //                         approval_type,
        //                         approval_status,
        //                         (slot_record->>'agreement_documents')::jsonb,
        //                         (slot_record->>'insurance_documents')::jsonb,
        //                         (slot_record->>'license_documents')::jsonb,
        //                         (slot_record->>'id_proof_documents')::jsonb,
        //                         (slot_record->>'any_attachment_document')::jsonb,
        //                         CASE WHEN (slot_record->>'tenant_id') IS NULL THEN NULL ELSE (slot_record->>'tenant_id')::bigint END,
        //                         (slot_record->>'partition_key')::varchar,
        //                         (slot_record->>'custom_attributes')::jsonb,
        //                         (slot_record->>'created_ip')::inet,
        //                         (slot_record->>'updated_ip')::inet,
        //                         p_current_time,
        //                         p_current_time
        //                     );
        //                     idx := idx + 1;
        //                 END LOOP;
        //             END IF;

        //             -- Build log data
        //             v_new_data := jsonb_build_object(
        //                 'booking_id', new_booking_id,
        //                 'booking_register_number', new_register_number,
        //                 'asset_id', p_asset_id,
        //                 'organization_id', p_organization_id,
        //                 'booked_by', p_booked_by,
        //                 'start_datetime', p_start_datetime,
        //                 'end_datetime', p_end_datetime,
        //                 'duration_hours', p_duration_hours,
        //                 'asset_booking_purpose_or_use_case_type_id', p_asset_booking_purpose_or_use_case_type_id,
        //                 'custom_purpose_name', p_custom_purpose_name,
        //                 'custom_purpose_description', p_custom_purpose_description,
        //                 'attendees_count', p_attendees_count,
        //                 'is_multi_slot', p_is_multi_slot,
        //                 'time_slots', p_time_slots
        //             );
        //             v_log_data := jsonb_build_object(
        //                 'old_data', v_old_data,
        //                 'new_data', v_new_data
        //             );

        //             IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
        //                 BEGIN
        //                     PERFORM log_activity(
        //                         'asset_booking.updated',
        //                         'Booking updated by ' || p_user_name || ': ' || new_register_number,
        //                         'asset_booking',
        //                         new_booking_id,
        //                         'user',
        //                         p_user_id,
        //                         v_log_data,
        //                         p_tenant_id
        //                     );
        //                     v_log_success := TRUE;
        //                 EXCEPTION WHEN OTHERS THEN
        //                     v_log_success := FALSE;
        //                     v_error_message := 'Logging failed: ' || SQLERRM;
        //                 END;
        //             END IF;

        //             -- Return booking info
        //             RETURN QUERY SELECT
        //                 'SUCCESS'::TEXT,
        //                 'Booking updated successfully'::TEXT,
        //                 new_booking_id,
        //                 new_register_number,
        //                 NULL::BIGINT,
        //                 NULL::TEXT,
        //                 NULL::JSONB;

        //         END IF;

        //         -- Return all time slots for this booking as JSONB (one per row)
        //         RETURN QUERY
        //         SELECT
        //             'SLOT'::TEXT,
        //             'Slot details'::TEXT,
        //             t.booking_id,
        //             NULL::VARCHAR,
        //             t.id,
        //             t.approval_status::TEXT,
        //             to_jsonb(t)
        //         FROM asset_booking_time_slots t
        //         WHERE t.booking_id = new_booking_id
        //         AND t.approval_type_id = 2;

        //         RETURN;
        //     END;
        //     $$;
        // SQL);

        DB::unprepared(<<<SQL
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'insert_or_update_asset_booking_with_time_slots'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION insert_or_update_asset_booking_with_time_slots(
                IN p_parent_booking_id BIGINT,
                IN p_organization_id BIGINT,
                IN p_asset_id BIGINT,
                IN p_booking_status VARCHAR,
                IN p_booking_type_id BIGINT,
                IN p_location_latitude VARCHAR,
                IN p_location_longitude VARCHAR,
                IN p_description TEXT,
                IN p_start_datetime TIMESTAMPTZ,
                IN p_end_datetime TIMESTAMPTZ,
                IN p_duration_hours NUMERIC,
                IN p_contact_email VARCHAR,
                IN p_contact_phone VARCHAR,
                IN p_asset_booking_purpose_or_use_case_type_id BIGINT,
                IN p_custom_purpose_name TEXT,
                IN p_custom_purpose_description TEXT,
                IN p_attendees_count INTEGER,
                IN p_special_requirements TEXT,
                IN p_rate_applied NUMERIC,
                IN p_currency_code BIGINT,
                IN p_total_cost NUMERIC,
                IN p_deposit_required BOOLEAN,
                IN p_deposit_amount NUMERIC,
                IN p_deposit_paid BOOLEAN,
                IN p_deposit_paid_at TIMESTAMPTZ,
                IN p_approval_required BOOLEAN,
                IN p_approved_by BIGINT,
                IN p_approved_at TIMESTAMPTZ,
                IN p_approval_notes TEXT,
                IN p_cancelled_at TIMESTAMPTZ,
                IN p_cancelled_by BIGINT,
                IN p_cancellation_reason TEXT,
                IN p_cancellation_fee NUMERIC,
                IN p_checked_in_at TIMESTAMPTZ,
                IN p_checked_in_by BIGINT,
                IN p_checked_out_at TIMESTAMPTZ,
                IN p_checked_out_by BIGINT,
                IN p_is_multi_slot BOOLEAN,
                IN p_slot_sequence INTEGER,
                IN p_reminder_sent BOOLEAN,
                IN p_reminder_sent_at TIMESTAMPTZ,
                IN p_tenant_id BIGINT,
                IN p_deleted_at TIMESTAMPTZ,
                IN p_locale VARCHAR,
                IN p_partition_key VARCHAR,
                IN p_custom_attributes JSONB,
                IN p_created_ip INET,
                IN p_updated_ip INET,
                IN p_time_slots JSONB,
                -- New columns
                IN p_booked_by_user_id BIGINT,
                IN p_booked_by_customer_id BIGINT,
                IN p_booking_created_by_user_id BIGINT,
                -- Logging
                IN p_user_id BIGINT,
                IN p_user_name VARCHAR,
                IN p_current_time TIMESTAMPTZ DEFAULT now(),
                IN p_id BIGINT DEFAULT NULL,
                IN p_prefix TEXT DEFAULT 'ASBK'
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                booking_id BIGINT,
                booking_register_number VARCHAR,
                time_slot_id BIGINT,
                slot_approval_status TEXT,
                slot_data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                curr_val BIGINT;
                new_booking_id BIGINT;
                new_register_number VARCHAR(100);
                slot_record JSONB;
                idx INT := 0;
                slot_count INT;
                approval_type BIGINT;
                approval_status TEXT;

                v_log_success BOOLEAN;
                v_error_message TEXT;
                v_old_data JSONB;
                v_new_data JSONB;
                v_log_data JSONB;
                v_action_type TEXT;
            BEGIN
                -- Validation
                IF p_asset_id IS NULL THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT,
                        'Asset ID cannot be null'::TEXT,
                        NULL::BIGINT,
                        NULL::VARCHAR,
                        NULL::BIGINT,
                        NULL::TEXT,
                        NULL::JSONB;
                    RETURN;
                END IF;
                IF p_organization_id IS NULL THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT,
                        'Organization ID cannot be null'::TEXT,
                        NULL::BIGINT,
                        NULL::VARCHAR,
                        NULL::BIGINT,
                        NULL::TEXT,
                        NULL::JSONB;
                    RETURN;
                END IF;
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT,
                        'Invalid tenant ID provided'::TEXT,
                        NULL::BIGINT,
                        NULL::VARCHAR,
                        NULL::BIGINT,
                        NULL::TEXT,
                        NULL::JSONB;
                    RETURN;
                END IF;

                IF p_id IS NULL OR p_id = 0 THEN
                    -- CREATE LOGIC
                    v_action_type := 'created';

                    SELECT nextval('asset_booking_register_number_seq') INTO curr_val;
                    new_register_number := p_prefix || '-' || LPAD(curr_val::TEXT, 6, '0');

                    INSERT INTO asset_bookings (
                        parent_booking_id, organization_id, asset_id, 
                        booking_register_number, booking_status, booking_type_id,
                        location_latitude, location_longitude, description,
                        start_datetime, end_datetime, duration_hours, contact_email, contact_phone,
                        asset_booking_purpose_or_use_case_type_id, custom_purpose_name, custom_purpose_description,
                        attendees_count, special_requirements, rate_applied, currency_code,
                        total_cost, deposit_required, deposit_amount, deposit_paid, deposit_paid_at,
                        approval_required, approved_by, approved_at, approval_notes,
                        cancelled_at, cancelled_by, cancellation_reason, cancellation_fee,
                        checked_in_at, checked_in_by, checked_out_at, checked_out_by,
                        is_multi_slot, slot_sequence, reminder_sent, reminder_sent_at, tenant_id,
                        deleted_at, locale, partition_key, custom_attributes, created_ip, updated_ip, 
                        booked_by_user_id, booked_by_customer_id, booking_created_by_user_id,
                        created_at, updated_at
                    ) VALUES (
                        p_parent_booking_id, p_organization_id, p_asset_id, 
                        new_register_number, p_booking_status, p_booking_type_id,
                        p_location_latitude, p_location_longitude, p_description,
                        p_start_datetime, p_end_datetime, p_duration_hours, p_contact_email, p_contact_phone,
                        p_asset_booking_purpose_or_use_case_type_id, p_custom_purpose_name, p_custom_purpose_description,
                        p_attendees_count, p_special_requirements, p_rate_applied, p_currency_code,
                        p_total_cost, p_deposit_required, p_deposit_amount, p_deposit_paid, p_deposit_paid_at,
                        p_approval_required, p_approved_by, p_approved_at, p_approval_notes,
                        p_cancelled_at, p_cancelled_by, p_cancellation_reason, p_cancellation_fee,
                        p_checked_in_at, p_checked_in_by, p_checked_out_at, p_checked_out_by,
                        p_is_multi_slot, p_slot_sequence, p_reminder_sent, p_reminder_sent_at, p_tenant_id,
                        p_deleted_at, p_locale, p_partition_key, p_custom_attributes, p_created_ip, p_updated_ip, 
                        p_booked_by_user_id, p_booked_by_customer_id, p_booking_created_by_user_id,
                        p_current_time, p_current_time
                    ) RETURNING id INTO new_booking_id;

                    -- Insert slots
                    IF p_time_slots IS NOT NULL AND jsonb_typeof(p_time_slots) = 'array' THEN
                        slot_count := jsonb_array_length(p_time_slots);
                        WHILE idx < slot_count LOOP
                            slot_record := p_time_slots->idx;

                            approval_type := (slot_record->>'approvalTypeId')::bigint;
                            IF approval_type = 1 THEN
                                approval_status := 'APPROVED';
                            ELSIF approval_type = 2 THEN
                                approval_status := 'PENDING';
                            ELSE
                                approval_status := 'APPROVED';
                            END IF;

                            INSERT INTO asset_booking_time_slots (
                                booking_id,
                                asset_availability_schedule_occurrences_id,
                                start_datetime,
                                end_datetime,
                                duration_hours,
                                rate_applied,
                                slot_cost,
                                sequence_order,
                                currency_code,
                                approval_type_id,
                                approval_status,
                                agreement_documents,
                                insurance_documents,
                                license_documents,
                                id_proof_documents,
                                any_attachment_document,
                                tenant_id,
                                partition_key,
                                custom_attributes,
                                created_ip,
                                updated_ip,
                                created_at,
                                updated_at
                            ) VALUES (
                                new_booking_id,
                                (slot_record->>'assetAvailabilityScheduleOccurrencesId')::bigint,
                                (slot_record->>'startDateTime')::timestamptz,
                                (slot_record->>'endDateTime')::timestamptz,
                                (slot_record->>'duration')::numeric,
                                (slot_record->>'rate')::numeric,
                                (slot_record->>'slot_cost')::numeric,
                                (slot_record->>'sequence_order')::integer,
                                (slot_record->>'currency_code')::bigint,
                                approval_type,
                                approval_status,
                                (slot_record->>'agreement_documents')::jsonb,
                                (slot_record->>'insurance_documents')::jsonb,
                                (slot_record->>'license_documents')::jsonb,
                                (slot_record->>'id_proof_documents')::jsonb,
                                (slot_record->>'any_attachment_document')::jsonb,
                                p_tenant_id,
                                (slot_record->>'partition_key')::varchar,
                                (slot_record->>'custom_attributes')::jsonb,
                                (slot_record->>'created_ip')::inet,
                                (slot_record->>'updated_ip')::inet,
                                p_current_time,
                                p_current_time
                            );
                            idx := idx + 1;
                        END LOOP;
                    END IF;

                    -- Build log data
                    v_new_data := jsonb_build_object(
                        'booking_id', new_booking_id,
                        'booking_register_number', new_register_number,
                        'asset_id', p_asset_id,
                        'organization_id', p_organization_id,
                        'booked_by_user_id', p_booked_by_user_id,
                        'booked_by_customer_id', p_booked_by_customer_id,
                        'booking_created_by_user_id', p_booking_created_by_user_id,
                        'start_datetime', p_start_datetime,
                        'end_datetime', p_end_datetime,
                        'duration_hours', p_duration_hours,
                        'asset_booking_purpose_or_use_case_type_id', p_asset_booking_purpose_or_use_case_type_id,
                        'custom_purpose_name', p_custom_purpose_name,
                        'custom_purpose_description', p_custom_purpose_description,
                        'attendees_count', p_attendees_count,
                        'is_multi_slot', p_is_multi_slot,
                        'time_slots', p_time_slots
                    );
                    v_log_data := jsonb_build_object(
                        'new_data', v_new_data
                    );

                    IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
                        BEGIN
                            PERFORM log_activity(
                                'asset_booking.created',
                                'Booking created by ' || p_user_name || ': ' || new_register_number,
                                'asset_booking',
                                new_booking_id,
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

                    -- Return booking info
                    RETURN QUERY SELECT
                        'SUCCESS'::TEXT,
                        'Booking created successfully'::TEXT,
                        new_booking_id,
                        new_register_number,
                        NULL::BIGINT,
                        NULL::TEXT,
                        NULL::JSONB;

                ELSE
                    -- UPDATE LOGIC
                    v_action_type := 'updated';

                    -- Fetch old data for log
                    SELECT
                        jsonb_build_object(
                            'booking_id', ab.id,
                            'booking_register_number', ab.booking_register_number,
                            'asset_id', ab.asset_id,
                            'organization_id', ab.organization_id,
                            'booked_by_user_id', ab.booked_by_user_id,
                            'booked_by_customer_id', ab.booked_by_customer_id,
                            'booking_created_by_user_id', ab.booking_created_by_user_id,
                            'start_datetime', ab.start_datetime,
                            'end_datetime', ab.end_datetime,
                            'duration_hours', ab.duration_hours,
                            'asset_booking_purpose_or_use_case_type_id', ab.asset_booking_purpose_or_use_case_type_id,
                            'custom_purpose_name', ab.custom_purpose_name,
                            'custom_purpose_description', ab.custom_purpose_description,
                            'attendees_count', ab.attendees_count,
                            'is_multi_slot', ab.is_multi_slot
                        )
                    INTO v_old_data
                    FROM asset_bookings ab WHERE ab.id = p_id;

                    -- Update booking
                    UPDATE asset_bookings
                    SET
                        parent_booking_id = p_parent_booking_id,
                        organization_id = p_organization_id,
                        asset_id = p_asset_id,
                        booking_status = p_booking_status,
                        booking_type_id = p_booking_type_id,
                        location_latitude = p_location_latitude,
                        location_longitude = p_location_longitude,
                        description = p_description,
                        start_datetime = p_start_datetime,
                        end_datetime = p_end_datetime,
                        duration_hours = p_duration_hours,
                        contact_email = p_contact_email,
                        contact_phone = p_contact_phone,
                        asset_booking_purpose_or_use_case_type_id = p_asset_booking_purpose_or_use_case_type_id,
                        custom_purpose_name = p_custom_purpose_name,
                        custom_purpose_description = p_custom_purpose_description,
                        attendees_count = p_attendees_count,
                        special_requirements = p_special_requirements,
                        rate_applied = p_rate_applied,
                        currency_code = p_currency_code,
                        total_cost = p_total_cost,
                        deposit_required = p_deposit_required,
                        deposit_amount = p_deposit_amount,
                        deposit_paid = p_deposit_paid,
                        deposit_paid_at = p_deposit_paid_at,
                        approval_required = p_approval_required,
                        approved_by = p_approved_by,
                        approved_at = p_approved_at,
                        approval_notes = p_approval_notes,
                        cancelled_at = p_cancelled_at,
                        cancelled_by = p_cancelled_by,
                        cancellation_reason = p_cancellation_reason,
                        cancellation_fee = p_cancellation_fee,
                        checked_in_at = p_checked_in_at,
                        checked_in_by = p_checked_in_by,
                        checked_out_at = p_checked_out_at,
                        checked_out_by = p_checked_out_by,
                        is_multi_slot = p_is_multi_slot,
                        slot_sequence = p_slot_sequence,
                        reminder_sent = p_reminder_sent,
                        reminder_sent_at = p_reminder_sent_at,
                        tenant_id = p_tenant_id,
                        deleted_at = p_deleted_at,
                        locale = p_locale,
                        partition_key = p_partition_key,
                        custom_attributes = p_custom_attributes,
                        updated_ip = p_updated_ip,
                        booked_by_user_id = p_booked_by_user_id,
                        booked_by_customer_id = p_booked_by_customer_id,
                        booking_created_by_user_id = p_booking_created_by_user_id,
                        updated_at = p_current_time
                    WHERE id = p_id
                    RETURNING id, booking_register_number INTO new_booking_id, new_register_number;

                    IF NOT FOUND THEN
                        RETURN QUERY SELECT
                            'FAILURE'::TEXT,
                            'Booking not found for update'::TEXT,
                            NULL::BIGINT,
                            NULL::VARCHAR,
                            NULL::BIGINT,
                            NULL::TEXT,
                            NULL::JSONB;
                        RETURN;
                    END IF;

                    -- Delete old time slots
                    DELETE FROM asset_booking_time_slots WHERE booking_id = new_booking_id;

                    -- Insert new time slots
                    idx := 0;
                    IF p_time_slots IS NOT NULL AND jsonb_typeof(p_time_slots) = 'array' THEN
                        slot_count := jsonb_array_length(p_time_slots);
                        WHILE idx < slot_count LOOP
                            slot_record := p_time_slots->idx;

                            approval_type := (slot_record->>'approvalTypeId')::bigint;
                            IF approval_type = 1 THEN
                                approval_status := 'APPROVED';
                            ELSIF approval_type = 2 THEN
                                approval_status := 'PENDING';
                            ELSE
                                approval_status := 'APPROVED';
                            END IF;

                            INSERT INTO asset_booking_time_slots (
                                booking_id,
                                asset_availability_schedule_occurrences_id,
                                start_datetime,
                                end_datetime,
                                duration_hours,
                                rate_applied,
                                slot_cost,
                                sequence_order,
                                currency_code,
                                approval_type_id,
                                approval_status,
                                agreement_documents,
                                insurance_documents,
                                license_documents,
                                id_proof_documents,
                                any_attachment_document,
                                tenant_id,
                                partition_key,
                                custom_attributes,
                                created_ip,
                                updated_ip,
                                created_at,
                                updated_at
                            ) VALUES (
                                new_booking_id,
                                (slot_record->>'assetAvailabilityScheduleOccurrencesId')::bigint,
                                (slot_record->>'start_datetime')::timestamptz,
                                (slot_record->>'end_datetime')::timestamptz,
                                (slot_record->>'duration_hours')::numeric,
                                (slot_record->>'rate_applied')::numeric,
                                (slot_record->>'slot_cost')::numeric,
                                (slot_record->>'sequence_order')::integer,
                                (slot_record->>'currency_code')::bigint,
                                approval_type,
                                approval_status,
                                (slot_record->>'agreement_documents')::jsonb,
                                (slot_record->>'insurance_documents')::jsonb,
                                (slot_record->>'license_documents')::jsonb,
                                (slot_record->>'id_proof_documents')::jsonb,
                                (slot_record->>'any_attachment_document')::jsonb,
                                CASE WHEN (slot_record->>'tenant_id') IS NULL THEN NULL ELSE (slot_record->>'tenant_id')::bigint END,
                                (slot_record->>'partition_key')::varchar,
                                (slot_record->>'custom_attributes')::jsonb,
                                (slot_record->>'created_ip')::inet,
                                (slot_record->>'updated_ip')::inet,
                                p_current_time,
                                p_current_time
                            );
                            idx := idx + 1;
                        END LOOP;
                    END IF;

                    -- Build log data
                    v_new_data := jsonb_build_object(
                        'booking_id', new_booking_id,
                        'booking_register_number', new_register_number,
                        'asset_id', p_asset_id,
                        'organization_id', p_organization_id,
                        'booked_by_user_id', p_booked_by_user_id,
                        'booked_by_customer_id', p_booked_by_customer_id,
                        'booking_created_by_user_id', p_booking_created_by_user_id,
                        'start_datetime', p_start_datetime,
                        'end_datetime', p_end_datetime,
                        'duration_hours', p_duration_hours,
                        'asset_booking_purpose_or_use_case_type_id', p_asset_booking_purpose_or_use_case_type_id,
                        'custom_purpose_name', p_custom_purpose_name,
                        'custom_purpose_description', p_custom_purpose_description,
                        'attendees_count', p_attendees_count,
                        'is_multi_slot', p_is_multi_slot,
                        'time_slots', p_time_slots
                    );
                    v_log_data := jsonb_build_object(
                        'old_data', v_old_data,
                        'new_data', v_new_data
                    );

                    IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
                        BEGIN
                            PERFORM log_activity(
                                'asset_booking.updated',
                                'Booking updated by ' || p_user_name || ': ' || new_register_number,
                                'asset_booking',
                                new_booking_id,
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

                    -- Return booking info
                    RETURN QUERY SELECT
                        'SUCCESS'::TEXT,
                        'Booking updated successfully'::TEXT,
                        new_booking_id,
                        new_register_number,
                        NULL::BIGINT,
                        NULL::TEXT,
                        NULL::JSONB;

                END IF;

                -- Return all time slots for this booking as JSONB (one per row)
                RETURN QUERY
                SELECT
                    'SLOT'::TEXT,
                    'Slot details'::TEXT,
                    t.booking_id,
                    NULL::VARCHAR,
                    t.id,
                    t.approval_status::TEXT,
                    to_jsonb(t)
                FROM asset_booking_time_slots t
                WHERE t.booking_id = new_booking_id
                AND t.approval_type_id = 2;

                RETURN;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS insert_or_update_asset_booking_with_time_slots(BIGINT, BIGINT, BIGINT, BIGINT, VARCHAR, BIGINT, VARCHAR, VARCHAR, VARCHAR, TEXT, TIMESTAMPTZ, TIMESTAMPTZ, NUMERIC, VARCHAR, VARCHAR, TEXT, INTEGER, TEXT, NUMERIC, VARCHAR, NUMERIC, BOOLEAN, NUMERIC, BOOLEAN, TIMESTAMPTZ, BOOLEAN, BIGINT, TIMESTAMPTZ, TEXT, TIMESTAMPTZ, BIGINT, TEXT, NUMERIC, TIMESTAMPTZ, BIGINT, TIMESTAMPTZ, BIGINT, BOOLEAN, INTEGER, BOOLEAN, TIMESTAMPTZ, BIGINT, TIMESTAMPTZ, VARCHAR, VARCHAR, JSONB, INET, INET, JSONB, BIGINT, VARCHAR);");
    }
};