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
        //     CREATE OR REPLACE FUNCTION get_auth_asset_booking_time_slots(
        //         p_action_type TEXT DEFAULT 'Normal',
        //         p_tenant_id BIGINT DEFAULT NULL,
        //         p_timezone TEXT DEFAULT NULL,
        //         p_booking_id BIGINT DEFAULT NULL,
        //         p_asset_id BIGINT DEFAULT NULL,
        //         p_start_datetime TIMESTAMPTZ DEFAULT NULL,
        //         p_end_datetime TIMESTAMPTZ DEFAULT NULL,
        //         p_user_id BIGINT DEFAULT NULL
        //     )
        //     RETURNS TABLE (
        //         -- Slot fields
        //         time_slot_id BIGINT,
        //         sequence_order INTEGER,
        //         slot_start TIMESTAMPTZ,
        //         slot_end TIMESTAMPTZ,
        //         duration_hours NUMERIC,
        //         rate_applied NUMERIC,
        //         slot_cost NUMERIC,
        //         approval_status TEXT,
        //         agreement_documents JSONB,
        //         insurance_documents JSONB,
        //         license_documents JSONB,
        //         id_proof_documents JSONB,
        //         any_attachment_document JSONB,
        //         currency_code BIGINT,
        //         approval_type_id BIGINT,
        //         asset_availability_schedule_occurrences_id BIGINT,
        //         partition_key VARCHAR,
        //         custom_attributes JSONB,
        //         created_ip INET,
        //         updated_ip INET,
        //         slot_created_at TIMESTAMPTZ,
        //         slot_updated_at TIMESTAMPTZ,
        //         slot_deleted_at TIMESTAMPTZ,
        //         slot_isactive BOOLEAN,
        //         slot_tenant_id BIGINT,

        //         -- Booking fields
        //         booking_id BIGINT,
        //         parent_booking_id BIGINT,
        //         organization_id BIGINT,
        //         asset_id BIGINT,
        //         booking_register_number TEXT,
        //         booking_status TEXT,
        //         booking_type_id BIGINT,
        //         location_latitude TEXT,
        //         location_longitude TEXT,
        //         description TEXT,
        //         start_datetime TIMESTAMPTZ,
        //         end_datetime TIMESTAMPTZ,
        //         booking_duration_hours NUMERIC,
        //         contact_email TEXT,
        //         contact_phone TEXT,
        //         attendees_count INTEGER,
        //         special_requirements TEXT,
        //         booking_rate_applied NUMERIC,
        //         booking_currency_code BIGINT,
        //         total_cost NUMERIC,
        //         deposit_required BOOLEAN,
        //         deposit_amount NUMERIC,
        //         deposit_paid BOOLEAN,
        //         deposit_paid_at TIMESTAMPTZ,
        //         approval_required BOOLEAN,
        //         approved_by BIGINT,
        //         approved_at TIMESTAMPTZ,
        //         approval_notes TEXT,
        //         cancelled_at TIMESTAMPTZ,
        //         cancelled_by BIGINT,
        //         cancellation_reason TEXT,
        //         cancellation_fee NUMERIC,
        //         checked_in_at TIMESTAMPTZ,
        //         checked_in_by BIGINT,
        //         checked_out_at TIMESTAMPTZ,
        //         checked_out_by BIGINT,
        //         is_multi_slot BOOLEAN,
        //         slot_sequence INTEGER,
        //         reminder_sent BOOLEAN,
        //         reminder_sent_at TIMESTAMPTZ,
        //         locale TEXT,
        //         booking_partition_key VARCHAR,
        //         booking_custom_attributes JSONB,
        //         booking_created_ip INET,
        //         booking_updated_ip INET,
        //         booking_tenant_id BIGINT,
        //         booking_isactive BOOLEAN,
        //         booking_deleted_at TIMESTAMPTZ,
        //         booking_created_at TIMESTAMPTZ,
        //         booking_updated_at TIMESTAMPTZ,
        //         -- New booking fields
        //         asset_booking_purpose_or_use_case_type_id BIGINT,
        //         custom_purpose_name TEXT,
        //         custom_purpose_description TEXT,
        //         booked_by_user_id BIGINT,
        //         booked_by_customer_id BIGINT,
        //         booking_created_by_user_id BIGINT
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     BEGIN
        //         RETURN QUERY
        //         SELECT
        //             t.id::bigint AS time_slot_id,
        //             t.sequence_order::integer,
        //             t.start_datetime::timestamptz AS slot_start,
        //             t.end_datetime::timestamptz AS slot_end,
        //             t.duration_hours::numeric,
        //             t.rate_applied::numeric,
        //             t.slot_cost::numeric,
        //             t.approval_status::text,
        //             t.agreement_documents::jsonb,
        //             t.insurance_documents::jsonb,
        //             t.license_documents::jsonb,
        //             t.id_proof_documents::jsonb,
        //             t.any_attachment_document::jsonb,
        //             t.currency_code::bigint,
        //             t.approval_type_id::bigint,
        //             t.asset_availability_schedule_occurrences_id::bigint,
        //             t.partition_key::varchar,
        //             t.custom_attributes::jsonb,
        //             t.created_ip::inet,
        //             t.updated_ip::inet,
        //             t.created_at::timestamptz AS slot_created_at,
        //             t.updated_at::timestamptz AS slot_updated_at,
        //             t.deleted_at::timestamptz AS slot_deleted_at,
        //             t.isactive::boolean AS slot_isactive,
        //             t.tenant_id::bigint AS slot_tenant_id,

        //             b.id::bigint AS booking_id,
        //             b.parent_booking_id::bigint,
        //             b.organization_id::bigint,
        //             b.asset_id::bigint,
        //             b.booking_register_number::text,
        //             b.booking_status::text,
        //             b.booking_type_id::bigint,
        //             b.location_latitude::text,
        //             b.location_longitude::text,
        //             b.description::text,
        //             b.start_datetime::timestamptz,
        //             b.end_datetime::timestamptz,
        //             b.duration_hours::numeric AS booking_duration_hours,
        //             b.contact_email::text,
        //             b.contact_phone::text,
        //             b.attendees_count::integer,
        //             b.special_requirements::text,
        //             b.rate_applied::numeric AS booking_rate_applied,
        //             b.currency_code::bigint AS booking_currency_code,
        //             b.total_cost::numeric,
        //             b.deposit_required::boolean,
        //             b.deposit_amount::numeric,
        //             b.deposit_paid::boolean,
        //             b.deposit_paid_at::timestamptz,
        //             b.approval_required::boolean,
        //             b.approved_by::bigint,
        //             b.approved_at::timestamptz,
        //             b.approval_notes::text,
        //             b.cancelled_at::timestamptz,
        //             b.cancelled_by::bigint,
        //             b.cancellation_reason::text,
        //             b.cancellation_fee::numeric,
        //             b.checked_in_at::timestamptz,
        //             b.checked_in_by::bigint,
        //             b.checked_out_at::timestamptz,
        //             b.checked_out_by::bigint,
        //             b.is_multi_slot::boolean,
        //             b.slot_sequence::integer,
        //             b.reminder_sent::boolean,
        //             b.reminder_sent_at::timestamptz,
        //             b.locale::text,
        //             b.partition_key::varchar AS booking_partition_key,
        //             b.custom_attributes::jsonb AS booking_custom_attributes,
        //             b.created_ip::inet AS booking_created_ip,
        //             b.updated_ip::inet AS booking_updated_ip,
        //             b.tenant_id::bigint AS booking_tenant_id,
        //             b.isactive::boolean AS booking_isactive,
        //             b.deleted_at::timestamptz AS booking_deleted_at,
        //             b.created_at::timestamptz AS booking_created_at,
        //             b.updated_at::timestamptz AS booking_updated_at,
        //             b.asset_booking_purpose_or_use_case_type_id::bigint,
        //             b.custom_purpose_name::text,
        //             b.custom_purpose_description::text,
        //             b.booked_by_user_id::bigint,
        //             b.booked_by_customer_id::bigint,
        //             b.booking_created_by_user_id::bigint
        //         FROM asset_booking_time_slots t
        //         JOIN asset_bookings b ON t.booking_id = b.id
        //         WHERE
        //             (p_booking_id IS NULL OR t.booking_id = p_booking_id)
        //             AND (p_asset_id IS NULL OR b.asset_id = p_asset_id)
        //             AND (p_tenant_id IS NULL OR b.tenant_id = p_tenant_id)
        //             AND (p_user_id IS NULL OR b.booking_created_by_user_id = p_user_id)
        //             AND (p_start_datetime IS NULL OR t.end_datetime >= p_start_datetime)
        //             AND (p_end_datetime IS NULL OR t.start_datetime <= p_end_datetime)
        //             AND t.deleted_at IS NULL
        //             AND t.isactive = TRUE
        //         ORDER BY t.start_datetime, t.sequence_order;
        //     END;
        //     $$;
        // SQL);
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_auth_asset_booking_time_slots(
                p_action_type TEXT DEFAULT 'Normal',
                p_tenant_id BIGINT DEFAULT NULL,
                p_timezone TEXT DEFAULT NULL,
                p_booking_id BIGINT DEFAULT NULL,
                p_asset_id BIGINT DEFAULT NULL,
                p_start_datetime TIMESTAMPTZ DEFAULT NULL,
                p_end_datetime TIMESTAMPTZ DEFAULT NULL,
                p_user_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,

                -- Slot fields
                time_slot_id BIGINT,
                sequence_order INTEGER,
                slot_start TIMESTAMPTZ,
                slot_end TIMESTAMPTZ,
                duration_hours NUMERIC,
                rate_applied NUMERIC,
                slot_cost NUMERIC,
                approval_status TEXT,
                agreement_documents JSONB,
                insurance_documents JSONB,
                license_documents JSONB,
                id_proof_documents JSONB,
                any_attachment_document JSONB,
                currency_code BIGINT,
                approval_type_id BIGINT,
                asset_availability_schedule_occurrences_id BIGINT,
                partition_key VARCHAR,
                custom_attributes JSONB,
                created_ip INET,
                updated_ip INET,

                -- Booking fields
                booking_id BIGINT,
                parent_booking_id BIGINT,
                organization_id BIGINT,
                asset_id BIGINT,
                booking_register_number TEXT,
                booking_status TEXT,
                booking_type_id BIGINT,
                location_latitude TEXT,
                location_longitude TEXT,
                description TEXT,
                start_datetime TIMESTAMPTZ,
                end_datetime TIMESTAMPTZ,
                booking_duration_hours NUMERIC,
                contact_email TEXT,
                contact_phone TEXT,
                attendees_count INTEGER,
                special_requirements TEXT,
                booking_rate_applied NUMERIC,
                booking_currency_code BIGINT,
                total_cost NUMERIC,
                deposit_required BOOLEAN,
                deposit_amount NUMERIC,
                deposit_paid BOOLEAN,
                deposit_paid_at TIMESTAMPTZ,
                approval_required BOOLEAN,
                approved_by BIGINT,
                approved_at TIMESTAMPTZ,
                approval_notes TEXT,
                cancelled_at TIMESTAMPTZ,
                cancelled_by BIGINT,
                cancellation_reason TEXT,
                cancellation_fee NUMERIC,
                checked_in_at TIMESTAMPTZ,
                checked_in_by BIGINT,
                checked_out_at TIMESTAMPTZ,
                checked_out_by BIGINT,
                is_multi_slot BOOLEAN,
                slot_sequence INTEGER,
                reminder_sent BOOLEAN,
                reminder_sent_at TIMESTAMPTZ,
                locale TEXT,
                booking_partition_key VARCHAR,
                booking_custom_attributes JSONB,
                booking_created_ip INET,
                booking_updated_ip INET,
                asset_booking_purpose_or_use_case_type_id BIGINT,
                custom_purpose_name TEXT,
                custom_purpose_description TEXT,
                booked_by_user_id BIGINT,
                booked_by_customer_id BIGINT,
                booking_created_by_user_id BIGINT
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Tenant ID validation
                IF p_tenant_id IS NOT NULL AND p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT, 
                        'Invalid tenant ID provided'::TEXT,
                        NULL::BIGINT, NULL::INTEGER, NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::NUMERIC, NULL::NUMERIC, NULL::NUMERIC, NULL::TEXT, NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::VARCHAR, NULL::JSONB, NULL::INET, NULL::INET,
                        NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::NUMERIC, NULL::TEXT, NULL::TEXT, NULL::INTEGER, NULL::TEXT, NULL::NUMERIC, NULL::BIGINT, NULL::NUMERIC, NULL::BOOLEAN, NULL::NUMERIC, NULL::BOOLEAN, NULL::TIMESTAMPTZ, NULL::BOOLEAN, NULL::BIGINT, NULL::TIMESTAMPTZ, NULL::TEXT, NULL::TIMESTAMPTZ, NULL::BIGINT, NULL::TEXT, NULL::NUMERIC, NULL::TIMESTAMPTZ, NULL::BIGINT, NULL::TIMESTAMPTZ, NULL::BIGINT, NULL::BOOLEAN, NULL::INTEGER, NULL::BOOLEAN, NULL::TIMESTAMPTZ, NULL::TEXT, NULL::VARCHAR, NULL::JSONB, NULL::INET, NULL::INET, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::BIGINT, NULL::BIGINT, NULL::BIGINT;
                    RETURN;
                END IF;

                -- Booking ID validation
                IF p_booking_id IS NOT NULL AND p_booking_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT, 
                        'Invalid booking ID provided'::TEXT,
                        NULL::BIGINT, NULL::INTEGER, NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::NUMERIC, NULL::NUMERIC, NULL::NUMERIC, NULL::TEXT, NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::VARCHAR, NULL::JSONB, NULL::INET, NULL::INET,
                        NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::NUMERIC, NULL::TEXT, NULL::TEXT, NULL::INTEGER, NULL::TEXT, NULL::NUMERIC, NULL::BIGINT, NULL::NUMERIC, NULL::BOOLEAN, NULL::NUMERIC, NULL::BOOLEAN, NULL::TIMESTAMPTZ, NULL::BOOLEAN, NULL::BIGINT, NULL::TIMESTAMPTZ, NULL::TEXT, NULL::TIMESTAMPTZ, NULL::BIGINT, NULL::TEXT, NULL::NUMERIC, NULL::TIMESTAMPTZ, NULL::BIGINT, NULL::TIMESTAMPTZ, NULL::BIGINT, NULL::BOOLEAN, NULL::INTEGER, NULL::BOOLEAN, NULL::TIMESTAMPTZ, NULL::TEXT, NULL::VARCHAR, NULL::JSONB, NULL::INET, NULL::INET, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::BIGINT, NULL::BIGINT, NULL::BIGINT;
                    RETURN;
                END IF;

                -- Asset ID validation
                IF p_asset_id IS NOT NULL AND p_asset_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT, 
                        'Invalid asset ID provided'::TEXT,
                        NULL::BIGINT, NULL::INTEGER, NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::NUMERIC, NULL::NUMERIC, NULL::NUMERIC, NULL::TEXT, NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::VARCHAR, NULL::JSONB, NULL::INET, NULL::INET,
                        NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::NUMERIC, NULL::TEXT, NULL::TEXT, NULL::INTEGER, NULL::TEXT, NULL::NUMERIC, NULL::BIGINT, NULL::NUMERIC, NULL::BOOLEAN, NULL::NUMERIC, NULL::BOOLEAN, NULL::TIMESTAMPTZ, NULL::BOOLEAN, NULL::BIGINT, NULL::TIMESTAMPTZ, NULL::TEXT, NULL::TIMESTAMPTZ, NULL::BIGINT, NULL::TEXT, NULL::NUMERIC, NULL::TIMESTAMPTZ, NULL::BIGINT, NULL::TIMESTAMPTZ, NULL::BIGINT, NULL::BOOLEAN, NULL::INTEGER, NULL::BOOLEAN, NULL::TIMESTAMPTZ, NULL::TEXT, NULL::VARCHAR, NULL::JSONB, NULL::INET, NULL::INET, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::BIGINT, NULL::BIGINT, NULL::BIGINT;
                    RETURN;
                END IF;

                -- User ID validation
                IF p_user_id IS NOT NULL AND p_user_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT, 
                        'Invalid user ID provided'::TEXT,
                        NULL::BIGINT, NULL::INTEGER, NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::NUMERIC, NULL::NUMERIC, NULL::NUMERIC, NULL::TEXT, NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::VARCHAR, NULL::JSONB, NULL::INET, NULL::INET,
                        NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::NUMERIC, NULL::TEXT, NULL::TEXT, NULL::INTEGER, NULL::TEXT, NULL::NUMERIC, NULL::BIGINT, NULL::NUMERIC, NULL::BOOLEAN, NULL::NUMERIC, NULL::BOOLEAN, NULL::TIMESTAMPTZ, NULL::BOOLEAN, NULL::BIGINT, NULL::TIMESTAMPTZ, NULL::TEXT, NULL::TIMESTAMPTZ, NULL::BIGINT, NULL::TEXT, NULL::NUMERIC, NULL::TIMESTAMPTZ, NULL::BIGINT, NULL::TIMESTAMPTZ, NULL::BIGINT, NULL::BOOLEAN, NULL::INTEGER, NULL::BOOLEAN, NULL::TIMESTAMPTZ, NULL::TEXT, NULL::VARCHAR, NULL::JSONB, NULL::INET, NULL::INET, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::BIGINT, NULL::BIGINT, NULL::BIGINT;
                    RETURN;
                END IF;

                -- No matching bookings/time slots check
                IF NOT EXISTS (
                    SELECT 1
                    FROM asset_booking_time_slots t
                    JOIN asset_bookings b ON t.booking_id = b.id
                    WHERE
                        (p_booking_id IS NULL OR t.booking_id = p_booking_id)
                        AND (p_asset_id IS NULL OR b.asset_id = p_asset_id)
                        AND (p_tenant_id IS NULL OR b.tenant_id = p_tenant_id)
                        AND (p_start_datetime IS NULL OR t.end_datetime >= p_start_datetime)
                        AND (p_end_datetime IS NULL OR t.start_datetime <= p_end_datetime)
                        AND t.deleted_at IS NULL
                        AND t.isactive = TRUE
                        AND (
                            CASE
                                WHEN p_action_type = 'authbooking' THEN
                                    (b.booked_by_user_id = p_user_id AND b.booking_created_by_user_id = p_user_id)
                                WHEN p_action_type = 'external' THEN
                                    (
                                        b.booking_created_by_user_id = p_user_id
                                        AND (
                                            b.booked_by_user_id IS NULL
                                            OR b.booked_by_user_id != p_user_id
                                        )
                                    )
                                ELSE TRUE
                            END
                        )
                ) THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT, 
                        'No matching time slots/bookings found'::TEXT,
                        NULL::BIGINT, NULL::INTEGER, NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::NUMERIC, NULL::NUMERIC, NULL::NUMERIC, NULL::TEXT, NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::JSONB, NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::VARCHAR, NULL::JSONB, NULL::INET, NULL::INET,
                        NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::NUMERIC, NULL::TEXT, NULL::TEXT, NULL::INTEGER, NULL::TEXT, NULL::NUMERIC, NULL::BIGINT, NULL::NUMERIC, NULL::BOOLEAN, NULL::NUMERIC, NULL::BOOLEAN, NULL::TIMESTAMPTZ, NULL::BOOLEAN, NULL::BIGINT, NULL::TIMESTAMPTZ, NULL::TEXT, NULL::TIMESTAMPTZ, NULL::BIGINT, NULL::TEXT, NULL::NUMERIC, NULL::TIMESTAMPTZ, NULL::BIGINT, NULL::TIMESTAMPTZ, NULL::BIGINT, NULL::BOOLEAN, NULL::INTEGER, NULL::BOOLEAN, NULL::TIMESTAMPTZ, NULL::TEXT, NULL::VARCHAR, NULL::JSONB, NULL::INET, NULL::INET, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::BIGINT, NULL::BIGINT, NULL::BIGINT;
                    RETURN;
                END IF;

                -- Return matching slots and bookings
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Time slots fetched successfully'::TEXT AS message,
                    t.id::bigint AS time_slot_id,
                    t.sequence_order::integer,
                    t.start_datetime::timestamptz AS slot_start,
                    t.end_datetime::timestamptz AS slot_end,
                    t.duration_hours::numeric,
                    t.rate_applied::numeric,
                    t.slot_cost::numeric,
                    t.approval_status::text,
                    t.agreement_documents::jsonb,
                    t.insurance_documents::jsonb,
                    t.license_documents::jsonb,
                    t.id_proof_documents::jsonb,
                    t.any_attachment_document::jsonb,
                    t.currency_code::bigint,
                    t.approval_type_id::bigint,
                    t.asset_availability_schedule_occurrences_id::bigint,
                    t.partition_key::varchar,
                    t.custom_attributes::jsonb,
                    t.created_ip::inet,
                    t.updated_ip::inet,

                    b.id::bigint AS booking_id,
                    b.parent_booking_id::bigint,
                    b.organization_id::bigint,
                    b.asset_id::bigint,
                    b.booking_register_number::text,
                    b.booking_status::text,
                    b.booking_type_id::bigint,
                    b.location_latitude::text,
                    b.location_longitude::text,
                    b.description::text,
                    b.start_datetime::timestamptz,
                    b.end_datetime::timestamptz,
                    b.duration_hours::numeric AS booking_duration_hours,
                    b.contact_email::text,
                    b.contact_phone::text,
                    b.attendees_count::integer,
                    b.special_requirements::text,
                    b.rate_applied::numeric AS booking_rate_applied,
                    b.currency_code::bigint AS booking_currency_code,
                    b.total_cost::numeric,
                    b.deposit_required::boolean,
                    b.deposit_amount::numeric,
                    b.deposit_paid::boolean,
                    b.deposit_paid_at::timestamptz,
                    b.approval_required::boolean,
                    b.approved_by::bigint,
                    b.approved_at::timestamptz,
                    b.approval_notes::text,
                    b.cancelled_at::timestamptz,
                    b.cancelled_by::bigint,
                    b.cancellation_reason::text,
                    b.cancellation_fee::numeric,
                    b.checked_in_at::timestamptz,
                    b.checked_in_by::bigint,
                    b.checked_out_at::timestamptz,
                    b.checked_out_by::bigint,
                    b.is_multi_slot::boolean,
                    b.slot_sequence::integer,
                    b.reminder_sent::boolean,
                    b.reminder_sent_at::timestamptz,
                    b.locale::text,
                    b.partition_key::varchar AS booking_partition_key,
                    b.custom_attributes::jsonb AS booking_custom_attributes,
                    b.created_ip::inet AS booking_created_ip,
                    b.updated_ip::inet AS booking_updated_ip,
                    b.asset_booking_purpose_or_use_case_type_id::bigint,
                    b.custom_purpose_name::text,
                    b.custom_purpose_description::text,
                    b.booked_by_user_id::bigint,
                    b.booked_by_customer_id::bigint,
                    b.booking_created_by_user_id::bigint
                FROM asset_booking_time_slots t
                JOIN asset_bookings b ON t.booking_id = b.id
                WHERE
                    (p_booking_id IS NULL OR t.booking_id = p_booking_id)
                    AND (p_asset_id IS NULL OR b.asset_id = p_asset_id)
                    AND (p_tenant_id IS NULL OR b.tenant_id = p_tenant_id)
                    AND (p_start_datetime IS NULL OR t.end_datetime >= p_start_datetime)
                    AND (p_end_datetime IS NULL OR t.start_datetime <= p_end_datetime)
                    AND t.deleted_at IS NULL
                    AND t.isactive = TRUE
                    AND (
                        CASE
                            WHEN p_action_type = 'authbooking' THEN
                                (b.booked_by_user_id = p_user_id AND b.booking_created_by_user_id = p_user_id)
                            WHEN p_action_type = 'external' THEN
                                (
                                    b.booking_created_by_user_id = p_user_id
                                    AND (
                                        b.booked_by_user_id IS NULL
                                        OR b.booked_by_user_id != p_user_id
                                    )
                                )
                            ELSE TRUE
                        END
                    )
                ORDER BY t.start_datetime, t.sequence_order;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_auth_asset_booking_time_slots(TEXT, BIGINT, TEXT, BIGINT, BIGINT, TIMESTAMPTZ, TIMESTAMPTZ, BIGINT);");
    }
};