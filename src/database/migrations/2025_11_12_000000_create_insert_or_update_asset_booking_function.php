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
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION insert_or_update_asset_booking(
                -- Main Booking Parameters
                IN p_id BIGINT DEFAULT NULL,
                IN p_booking_number VARCHAR DEFAULT NULL,
                IN p_booking_reference VARCHAR DEFAULT NULL,
                IN p_is_self_booking BOOLEAN DEFAULT TRUE,
                IN p_booking_type_id BIGINT DEFAULT NULL,
                IN p_parent_booking_id BIGINT DEFAULT NULL,
                IN p_optiomesh_customer_id BIGINT DEFAULT NULL,
                IN p_booked_by_user_id BIGINT DEFAULT NULL,
                IN p_optiomesh_customer_details JSONB DEFAULT NULL,
                IN p_attendees_count INT DEFAULT 1,
                IN p_booking_status VARCHAR DEFAULT 'PENDING',
                IN p_note TEXT DEFAULT NULL,
                IN p_special_requirements TEXT DEFAULT NULL,
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_partition_key VARCHAR DEFAULT NULL,
                IN p_created_by BIGINT DEFAULT NULL,
                IN p_updated_by BIGINT DEFAULT NULL,
                IN p_created_ip INET DEFAULT NULL,
                IN p_updated_ip INET DEFAULT NULL,
                
                -- Recurring Parameters
                IN p_recurring_enabled BOOLEAN DEFAULT FALSE,
                IN p_recurring_pattern VARCHAR DEFAULT NULL,
                IN p_recurring_config JSONB DEFAULT NULL,
                
                -- Booking Items Array (JSONB)
                IN p_booking_items JSONB DEFAULT '[]'::JSONB,
                
                -- Meta
                IN p_current_time TIMESTAMPTZ DEFAULT NOW()
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                booking_id BIGINT,
                booking_number VARCHAR,
                booking_items JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_booking_id BIGINT;
                v_booking_number VARCHAR(50);
                v_sequence_val BIGINT;
                v_item JSONB;
                v_item_id BIGINT;
                v_slot JSONB;
                v_slot_id BIGINT;
                v_result_items JSONB := '[]'::JSONB;
                v_item_result JSONB;
                v_slot_result JSONB;
                v_slot_results JSONB;
                v_recurring_dates TIMESTAMPTZ[];
                v_recurring_date TIMESTAMPTZ;
                v_idx INT;
                v_original_start TIMESTAMPTZ;
                v_original_end TIMESTAMPTZ;
                v_duration INTERVAL;
                v_slot_original_start TIMESTAMPTZ;
                v_slot_original_end TIMESTAMPTZ;
                v_slot_duration INTERVAL;
                v_slot_sequence_counter INT := 0; -- Track unique slot sequence per booking
                v_time_slot_sequence_counter INT; -- Track unique time slot sequence per booking_item
                v_matching_occurrence_id BIGINT; -- For availability schedule occurrence matching
                v_slot_start TIMESTAMPTZ; -- For slot time comparison
                v_slot_end TIMESTAMPTZ; -- For slot time comparison
                v_slot_status TEXT; -- Computed slot status (CONFIRMED or PENDING)
            BEGIN
                -- Validation
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT,
                        'Invalid tenant ID provided'::TEXT,
                        NULL::BIGINT,
                        NULL::VARCHAR,
                        NULL::JSONB;
                    RETURN;
                END IF;

                IF p_booking_items IS NULL OR jsonb_array_length(p_booking_items) = 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT,
                        'At least one booking item is required'::TEXT,
                        NULL::BIGINT,
                        NULL::VARCHAR,
                        NULL::JSONB;
                    RETURN;
                END IF;

                -- Handle recurring bookings - generate multiple date instances
                IF p_recurring_enabled AND p_recurring_config IS NOT NULL THEN
                    -- Call a helper function to generate recurring dates based on config
                    -- This would expand the booking into multiple date ranges
                    v_recurring_dates := generate_recurring_dates(
                        (p_booking_items->0->>'start_datetime')::TIMESTAMPTZ,
                        (p_booking_items->0->>'end_datetime')::TIMESTAMPTZ,
                        p_recurring_config
                    );
                ELSE
                    -- Single booking - no recurrence
                    v_recurring_dates := ARRAY[(p_booking_items->0->>'start_datetime')::TIMESTAMPTZ];
                END IF;

                -- CREATE OR UPDATE MAIN BOOKING (only once, not per recurring date)
                IF p_id IS NULL OR p_id = 0 THEN
                    -- Generate booking number
                    SELECT nextval('asset_booking_number_seq') INTO v_sequence_val;
                    v_booking_number := 'ASBK-' || LPAD(v_sequence_val::TEXT, 8, '0');

                    -- Insert main booking
                    INSERT INTO asset_bookings (
                        booking_number,
                        booking_reference,
                        is_self_booking,
                        booking_type_id,
                        parent_booking_id,
                        is_optiomesh_booking,
                        optiomesh_customer_id,
                        booked_by_user_id,
                        optiomesh_customer_details,
                        attendees_count,
                        booking_status,
                        note,
                        special_requirements,
                        tenant_id,
                        partition_key,
                        isactive,
                        created_by,
                        updated_by,
                        created_ip,
                        updated_ip,
                        created_at,
                        updated_at
                    ) VALUES (
                        v_booking_number,
                        p_booking_reference,
                        p_is_self_booking,
                        p_booking_type_id,
                        p_parent_booking_id,
                        FALSE, -- External booking, not optiomesh internal booking
                        p_optiomesh_customer_id,
                        NULL, -- External customers don't have user IDs
                        p_optiomesh_customer_details,
                        p_attendees_count,
                        p_booking_status,
                        p_note,
                        p_special_requirements,
                        p_tenant_id,
                        COALESCE(p_partition_key, TO_CHAR(p_current_time, 'YYYY-MM')),
                        TRUE,
                        p_created_by,
                        p_updated_by,
                        p_created_ip,
                        p_updated_ip,
                        p_current_time,
                        p_current_time
                    ) RETURNING id INTO v_booking_id;
                ELSE
                    -- Update existing booking
                    UPDATE asset_bookings SET
                        booking_reference = COALESCE(p_booking_reference, booking_reference),
                        booking_status = COALESCE(p_booking_status, booking_status),
                        note = COALESCE(p_note, note),
                        special_requirements = COALESCE(p_special_requirements, special_requirements),
                        updated_by = p_updated_by,
                        updated_ip = p_updated_ip,
                        updated_at = p_current_time
                    WHERE id = p_id AND tenant_id = p_tenant_id
                    RETURNING id, booking_number INTO v_booking_id, v_booking_number;

                    IF v_booking_id IS NULL THEN
                        RETURN QUERY SELECT
                            'FAILURE'::TEXT,
                            'Booking not found or unauthorized'::TEXT,
                            NULL::BIGINT,
                            NULL::VARCHAR,
                            NULL::JSONB;
                        RETURN;
                    END IF;
                END IF;

                -- PROCESS BOOKING ITEMS - Create ONE item per cart asset, with MULTIPLE time slots
                FOR v_idx IN 0..jsonb_array_length(p_booking_items) - 1 LOOP
                    v_item := p_booking_items->v_idx;
                    v_slot_results := '[]'::JSONB;
                    v_time_slot_sequence_counter := 0; -- Reset time slot counter for each booking_item

                    -- Store original dates (use first recurring date for the item itself)
                    -- UTC timezone is preserved from ISO 8601 'Z' suffix in JSON
                    v_original_start := (p_booking_items->v_idx->>'start_datetime')::TIMESTAMPTZ;
                    v_original_end := (p_booking_items->v_idx->>'end_datetime')::TIMESTAMPTZ;
                    v_duration := v_original_end - v_original_start;

                    -- Use first recurring date for the booking_item start/end
                    v_item := jsonb_set(v_item, '{start_datetime}', 
                        to_jsonb(v_recurring_dates[1])::TEXT::JSONB);
                    v_item := jsonb_set(v_item, '{end_datetime}', 
                        to_jsonb(v_recurring_dates[1] + v_duration)::TEXT::JSONB);

                    -- Increment slot sequence counter for unique constraint
                    v_slot_sequence_counter := v_slot_sequence_counter + 1;

                    -- Insert ONE booking item per cart asset
                    INSERT INTO asset_booking_items (
                        asset_booking_id,
                        asset_id,
                            organization_id,
                            location_latitude,
                            location_longitude,
                            start_datetime,
                            end_datetime,
                            duration_hours,
                            timezone,
                            priority_level,
                            is_recurring,
                            recurring_pattern,
                            unit_rate,
                            rate_currency_id,
                            subtotal,
                            tax_amount,
                            discount_amount,
                            total_cost,
                            total_cost_currency_id,
                            rate_period_type_id,
                            deposit_required,
                            deposit_amount,
                            deposit_percentage,
                            deposit_currency_id,
                            deposit_paid,
                            approval_type_id,
                            booking_status,
                            cancellation_enabled,
                            cancellation_notice_hours,
                            cancellation_fee_enabled,
                            cancellation_fee_type,
                            cancellation_fee_amount,
                            cancellation_fee_percentage,
                            is_multi_slot,
                            slot_sequence,
                            total_slots,
                            logistics_note,
                            tenant_id,
                            partition_key,
                            isactive,
                            created_by,
                            updated_by,
                            created_ip,
                            updated_ip,
                            created_at,
                            updated_at
                        ) VALUES (
                            v_booking_id,
                            (v_item->>'asset_id')::BIGINT,
                            (v_item->>'organization_id')::BIGINT,
                            (v_item->>'location_latitude')::DECIMAL,
                            (v_item->>'location_longitude')::DECIMAL,
                            (v_item->>'start_datetime')::TIMESTAMPTZ,
                            (v_item->>'end_datetime')::TIMESTAMPTZ,
                            (v_item->>'duration_hours')::DECIMAL,
                            COALESCE(v_item->>'timezone', 'UTC'),
                            COALESCE((v_item->>'priority_level')::INT, 3),
                            p_recurring_enabled,
                            p_recurring_config,
                            (v_item->>'unit_rate')::DECIMAL,
                            (v_item->>'rate_currency_id')::BIGINT,
                            (v_item->>'subtotal')::DECIMAL,
                            COALESCE((v_item->>'tax_amount')::DECIMAL, 0),
                            COALESCE((v_item->>'discount_amount')::DECIMAL, 0),
                            (v_item->>'total_cost')::DECIMAL,
                            (v_item->>'total_cost_currency_id')::BIGINT,
                            (v_item->>'rate_period_type_id')::BIGINT,
                            COALESCE((v_item->>'deposit_required')::BOOLEAN, FALSE),
                            (v_item->>'deposit_amount')::DECIMAL,
                            (v_item->>'deposit_percentage')::DECIMAL,
                            (v_item->>'deposit_currency_id')::BIGINT,
                            FALSE,
                            (v_item->>'approval_type_id')::BIGINT,
                            CASE 
                                WHEN (v_item->>'approval_type_id')::BIGINT = 1 THEN 'APPROVED'
                                WHEN (v_item->>'approval_type_id')::BIGINT = 2 THEN 'PENDING'
                                ELSE COALESCE(v_item->>'booking_status', 'PENDING')
                            END, -- Auto-approve if approval_type_id = 1, otherwise PENDING
                            COALESCE((v_item->>'cancellation_enabled')::BOOLEAN, TRUE),
                            (v_item->>'cancellation_notice_hours')::INT,
                            COALESCE((v_item->>'cancellation_fee_enabled')::BOOLEAN, FALSE),
                            (v_item->>'cancellation_fee_type')::INT,
                            (v_item->>'cancellation_fee_amount')::DECIMAL,
                            COALESCE((v_item->>'cancellation_fee_percentage')::DECIMAL, 0),
                            COALESCE((v_item->>'is_multi_slot')::BOOLEAN, FALSE),
                            v_slot_sequence_counter, -- Use counter instead of JSON value for unique constraint
                            COALESCE((v_item->>'total_slots')::INT, 1),
                            v_item->>'logistics_note',
                            p_tenant_id,
                            COALESCE(p_partition_key, TO_CHAR(p_current_time, 'YYYY-MM')),
                            TRUE,
                            p_created_by,
                            p_updated_by,
                            p_created_ip,
                            p_updated_ip,
                            p_current_time,
                            p_current_time
                        ) RETURNING id INTO v_item_id;

                        -- PROCESS TIME SLOTS - Create MULTIPLE slots for recurring dates
                        -- Loop through ALL recurring dates to create time slots
                        FOREACH v_recurring_date IN ARRAY v_recurring_dates LOOP
                            IF v_item->'time_slots' IS NOT NULL THEN
                                FOR v_slot IN SELECT * FROM jsonb_array_elements(v_item->'time_slots') LOOP
                                    -- Increment time slot sequence counter for unique constraint
                                    v_time_slot_sequence_counter := v_time_slot_sequence_counter + 1;
                                    
                                    -- Store original slot dates - UTC is preserved from ISO 8601 'Z' suffix
                                    v_slot_original_start := (v_slot->>'start_datetime')::TIMESTAMPTZ;
                                    v_slot_original_end := (v_slot->>'end_datetime')::TIMESTAMPTZ;
                                    v_slot_duration := v_slot_original_end - v_slot_original_start;
                                    
                                    -- ONLY adjust slot dates for recurring bookings
                                    -- For non-recurring bookings, preserve the original slot dates
                                    IF p_recurring_enabled THEN
                                        -- Adjust slot dates to the current recurring date
                                        v_slot := jsonb_set(v_slot, '{start_datetime}', 
                                            to_jsonb(v_recurring_date)::TEXT::JSONB);
                                        -- Calculate new end date by adding the original duration
                                        v_slot := jsonb_set(v_slot, '{end_datetime}', 
                                            to_jsonb(v_recurring_date + v_slot_duration)::TEXT::JSONB);
                                    END IF;
                                    -- If not recurring, v_slot keeps its original start_datetime and end_datetime

                                    -- Check if matching availability schedule occurrence exists - UTC preserved from JSON
                                    v_slot_start := (v_slot->>'start_datetime')::TIMESTAMPTZ;
                                    v_slot_end := (v_slot->>'end_datetime')::TIMESTAMPTZ;
                                    v_matching_occurrence_id := NULL;
                                    v_slot_status := 'PENDING'; -- Default to PENDING (availability not found)
                                    
                                    -- Find matching occurrence that covers the entire slot time range
                                    SELECT id INTO v_matching_occurrence_id
                                    FROM asset_availability_schedule_occurrences
                                    WHERE asset_id = (v_item->>'asset_id')::BIGINT
                                      AND tenant_id = p_tenant_id
                                      AND isactive = TRUE
                                      AND deleted_at IS NULL
                                      AND is_cancelled = FALSE
                                      -- Occurrence must fully contain the booking slot
                                      AND occurrence_start <= v_slot_start
                                      AND occurrence_end >= v_slot_end
                                    LIMIT 1;
                                    
                                    -- Set slot status based on whether matching occurrence was found
                                    IF v_matching_occurrence_id IS NOT NULL THEN
                                        v_slot_status := 'CONFIRMED'; -- Availability found, confirmed
                                    END IF;

                                    INSERT INTO asset_booking_time_slots (
                                    asset_booking_item_id,
                                    asset_availability_schedule_occurrences_id,
                                    start_datetime,
                                    end_datetime,
                                    duration_hours,
                                    timezone,
                                    sequence_order,
                                    total_slots_in_booking,
                                    slot_rate,
                                    slot_cost,
                                    slot_currency_id,
                                    slot_status,
                                    expected_attendees,
                                    required_equipment,
                                    setup_requirements,
                                    reminder_sent,
                                    tenant_id,
                                    partition_key,
                                    isactive,
                                    created_by,
                                    updated_by,
                                    created_ip,
                                    updated_ip,
                                    created_at,
                                    updated_at
                                ) VALUES (
                                    v_item_id,
                                    v_matching_occurrence_id, -- Use matched occurrence_id (NULL if not found)
                                    (v_slot->>'start_datetime')::TIMESTAMPTZ,
                                    (v_slot->>'end_datetime')::TIMESTAMPTZ,
                                    (v_slot->>'duration_hours')::DECIMAL,
                                    'UTC',
                                    v_time_slot_sequence_counter, -- Use counter instead of JSON value for unique constraint
                                    COALESCE((v_slot->>'total_slots_in_booking')::INT, 1),
                                    (v_slot->>'slot_rate')::DECIMAL,
                                    (v_slot->>'slot_cost')::DECIMAL,
                                    (v_slot->>'slot_currency_id')::BIGINT,
                                    v_slot_status, -- Use computed status (CONFIRMED or PENDING)
                                    (v_slot->>'expected_attendees')::INT,
                                    v_slot->'required_equipment',
                                    v_slot->'setup_requirements',
                                    FALSE,
                                    p_tenant_id,
                                    COALESCE(p_partition_key, TO_CHAR(p_current_time, 'YYYY-MM')),
                                    TRUE,
                                    p_created_by,
                                    p_updated_by,
                                    p_created_ip,
                                    p_updated_ip,
                                    p_current_time,
                                    p_current_time
                                ) RETURNING id INTO v_slot_id;

                                -- Build slot result with actual status
                                v_slot_result := jsonb_build_object(
                                    'slot_id', v_slot_id,
                                    'availability_schedule_id', v_matching_occurrence_id,
                                    'start_datetime', v_slot->>'start_datetime',
                                    'end_datetime', v_slot->>'end_datetime',
                                    'sequence_order', v_time_slot_sequence_counter,
                                    'slot_status', v_slot_status
                                );
                                v_slot_results := v_slot_results || v_slot_result;
                                END LOOP; -- End time slots loop
                            END IF;
                        END LOOP; -- End recurring dates loop

                        -- Build item result (after processing all recurring dates)
                        v_item_result := jsonb_build_object(
                            'item_id', v_item_id,
                            'asset_id', (v_item->>'asset_id')::BIGINT,
                            'booking_status', COALESCE(v_item->>'booking_status', 'PENDING'),
                            'time_slots', v_slot_results
                        );
                        v_result_items := v_result_items || v_item_result;
                END LOOP; -- End cart items loop

                -- Return success
                RETURN QUERY SELECT
                    'SUCCESS'::TEXT,
                    CASE 
                        WHEN p_recurring_enabled THEN 'Recurring booking created successfully with ' || array_length(v_recurring_dates, 1)::TEXT || ' instances'
                        ELSE 'Booking created successfully'
                    END::TEXT,
                    v_booking_id,
                    v_booking_number,
                    v_result_items;
            END;
            $$;

            -- Helper function to generate recurring dates
            CREATE OR REPLACE FUNCTION generate_recurring_dates(
                p_start_date TIMESTAMPTZ,
                p_end_date TIMESTAMPTZ,
                p_config JSONB
            )
            RETURNS TIMESTAMPTZ[]
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_dates TIMESTAMPTZ[] := ARRAY[]::TIMESTAMPTZ[];
                v_frequency VARCHAR := p_config->>'frequency';
                v_interval INT := COALESCE((p_config->>'interval')::INT, 1);
                v_end_type VARCHAR := p_config->>'endType';
                v_end_after INT := (p_config->>'endAfter')::INT;
                v_end_until TIMESTAMPTZ := (p_config->>'endUntil')::TIMESTAMPTZ;
                v_selected_days JSONB := p_config->'selectedDays';
                v_current_date TIMESTAMPTZ := p_start_date;
                v_count INT := 0;
                v_iterations INT := 0;
                v_max_iterations INT := 10000; -- Safety limit
                v_max_future_years INT := 10;
                v_future_limit TIMESTAMPTZ := p_start_date + (v_max_future_years || ' years')::INTERVAL;
                v_week_count INT := 0;
                v_start_dow INT;
                i INT; -- Loop variable
            BEGIN
                -- If no recurrence config, return single date
                IF p_config IS NULL THEN
                    RETURN ARRAY[p_start_date];
                END IF;

                -- For weekly with selected days, find first matching day if start date doesn't match
                IF v_frequency = 'weekly' AND v_selected_days IS NOT NULL THEN
                    v_start_dow := EXTRACT(DOW FROM v_current_date)::INT;
                    
                    -- If start day is not in selected days, advance to first matching day
                    IF NOT (v_selected_days @> v_start_dow::TEXT::JSONB) THEN
                        -- Search for next matching day in the week (max 7 days)
                        FOR i IN 1..7 LOOP
                            v_current_date := v_current_date + '1 day'::INTERVAL;
                            IF v_selected_days @> EXTRACT(DOW FROM v_current_date)::TEXT::JSONB THEN
                                EXIT;
                            END IF;
                        END LOOP;
                    END IF;
                END IF;

                -- Main loop with dual safety: count AND iterations
                WHILE v_count < COALESCE(v_end_after, v_max_iterations) AND v_iterations < v_max_iterations LOOP
                    v_iterations := v_iterations + 1;
                    
                    -- Safety check: prevent dates beyond 10 years
                    IF v_current_date > v_future_limit THEN
                        EXIT;
                    END IF;
                    
                    -- Check end conditions
                    IF v_end_type = 'until' AND v_current_date > v_end_until THEN
                        EXIT;
                    END IF;

                    -- Add current date if it matches criteria
                    IF v_frequency = 'weekly' AND v_selected_days IS NOT NULL THEN
                        -- Check if day of week matches selected days
                        IF v_selected_days @> EXTRACT(DOW FROM v_current_date)::TEXT::JSONB THEN
                            v_dates := array_append(v_dates, v_current_date);
                            v_count := v_count + 1;
                        END IF;
                        
                        -- Increment by 1 day to check next day
                        v_current_date := v_current_date + '1 day'::INTERVAL;
                        
                        -- After completing a week (reaching Sunday), skip weeks if interval > 1
                        IF EXTRACT(DOW FROM v_current_date) = 0 THEN
                            v_week_count := v_week_count + 1;
                            IF v_interval > 1 THEN
                                v_current_date := v_current_date + ((v_interval - 1) || ' weeks')::INTERVAL;
                            END IF;
                        END IF;
                    ELSE
                        -- For non-weekly or weekly without selectedDays
                        v_dates := array_append(v_dates, v_current_date);
                        v_count := v_count + 1;
                        
                        -- Increment date based on frequency
                        IF v_frequency = 'daily' THEN
                            v_current_date := v_current_date + (v_interval || ' days')::INTERVAL;
                        ELSIF v_frequency = 'weekly' THEN
                            v_current_date := v_current_date + (v_interval || ' weeks')::INTERVAL;
                        ELSIF v_frequency = 'monthly' THEN
                            v_current_date := v_current_date + (v_interval || ' months')::INTERVAL;
                        ELSIF v_frequency = 'yearly' THEN
                            v_current_date := v_current_date + (v_interval || ' years')::INTERVAL;
                        ELSE
                            EXIT; -- Unknown frequency
                        END IF;
                    END IF;
                END LOOP;

                RETURN v_dates;
            END;
            $$;

            -- Create sequence for booking numbers
            CREATE SEQUENCE IF NOT EXISTS asset_booking_number_seq START WITH 1;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS insert_or_update_asset_booking CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS generate_recurring_dates CASCADE');
        DB::unprepared('DROP SEQUENCE IF EXISTS asset_booking_number_seq CASCADE');
    }
};