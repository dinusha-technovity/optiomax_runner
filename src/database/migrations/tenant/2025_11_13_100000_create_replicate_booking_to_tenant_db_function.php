<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates PostgreSQL function to replicate booking data to tenant database
     * This is called AFTER successful insertion in main database
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION replicate_booking_to_tenant_db(
                -- Booking IDs from main database
                IN p_booking_id BIGINT,
                IN p_tenant_id BIGINT,
                
                -- Tenant database connection details
                IN p_tenant_db_host VARCHAR,
                IN p_tenant_db_name VARCHAR,
                IN p_tenant_db_user VARCHAR,
                IN p_tenant_db_password VARCHAR,
                IN p_tenant_db_port INT DEFAULT 5432
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                tenant_booking_id BIGINT,
                replicated_items_count INT,
                replicated_slots_count INT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_connection_string TEXT;
                v_booking_record RECORD;
                v_item_record RECORD;
                v_slot_record RECORD;
                v_tenant_booking_id BIGINT;
                v_tenant_item_id BIGINT;
                v_items_count INT := 0;
                v_slots_count INT := 0;
                v_fdw_server_name TEXT;
                v_fdw_user_mapping TEXT;
            BEGIN
                -- Validate inputs
                IF p_booking_id IS NULL OR p_booking_id <= 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT,
                        'Invalid booking ID'::TEXT,
                        NULL::BIGINT,
                        0::INT,
                        0::INT;
                    RETURN;
                END IF;

                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT,
                        'Invalid tenant ID'::TEXT,
                        NULL::BIGINT,
                        0::INT,
                        0::INT;
                    RETURN;
                END IF;

                -- Create unique FDW server name for this tenant
                v_fdw_server_name := 'tenant_' || p_tenant_id || '_server';
                v_fdw_user_mapping := 'tenant_' || p_tenant_id || '_user';

                -- Build connection string
                v_connection_string := format(
                    'host=%s port=%s dbname=%s',
                    p_tenant_db_host,
                    p_tenant_db_port,
                    p_tenant_db_name
                );

                -- Drop existing FDW server if exists (for reconnection)
                EXECUTE format('DROP SERVER IF EXISTS %I CASCADE', v_fdw_server_name);

                -- Create foreign data wrapper server
                EXECUTE format(
                    'CREATE SERVER %I FOREIGN DATA WRAPPER postgres_fdw OPTIONS (host %L, port %L, dbname %L)',
                    v_fdw_server_name,
                    p_tenant_db_host,
                    p_tenant_db_port::TEXT,
                    p_tenant_db_name
                );

                -- Create user mapping
                EXECUTE format(
                    'CREATE USER MAPPING FOR CURRENT_USER SERVER %I OPTIONS (user %L, password %L)',
                    v_fdw_server_name,
                    p_tenant_db_user,
                    p_tenant_db_password
                );

                -- Create foreign table for tenant's asset_bookings
                EXECUTE format('
                    CREATE FOREIGN TABLE IF NOT EXISTS %I (
                        id BIGINT,
                        booking_number VARCHAR(50),
                        booking_reference VARCHAR(100),
                        is_self_booking BOOLEAN,
                        booking_type_id BIGINT,
                        parent_booking_id BIGINT,
                        optiomesh_customer_id BIGINT,
                        booked_by_user_id BIGINT,
                        optiomesh_customer_details JSONB,
                        attendees_count INT,
                        booking_status VARCHAR(50),
                        note TEXT,
                        special_requirements TEXT,
                        tenant_id BIGINT,
                        partition_key VARCHAR(50),
                        isactive BOOLEAN,
                        created_by BIGINT,
                        updated_by BIGINT,
                        created_ip INET,
                        updated_ip INET,
                        deleted_at TIMESTAMPTZ,
                        created_at TIMESTAMPTZ,
                        updated_at TIMESTAMPTZ
                    ) SERVER %I OPTIONS (schema_name %L, table_name %L)',
                    'tenant_bookings_fdw_' || p_tenant_id,
                    v_fdw_server_name,
                    'public',
                    'asset_bookings'
                );

                -- Create foreign table for tenant's asset_booking_items
                EXECUTE format('
                    CREATE FOREIGN TABLE IF NOT EXISTS %I (
                        id BIGINT,
                        asset_booking_id BIGINT,
                        asset_id BIGINT,
                        organization_id BIGINT,
                        location_latitude DECIMAL(10,8),
                        location_longitude DECIMAL(11,8),
                        location_description VARCHAR,
                        logistics_note TEXT,
                        start_datetime TIMESTAMPTZ,
                        end_datetime TIMESTAMPTZ,
                        duration_hours DECIMAL(8,2),
                        timezone VARCHAR(50),
                        priority_level INT,
                        is_recurring BOOLEAN,
                        recurring_pattern JSONB,
                        unit_rate DECIMAL(15,4),
                        rate_currency_id BIGINT,
                        subtotal DECIMAL(15,2),
                        tax_amount DECIMAL(15,2),
                        discount_amount DECIMAL(15,2),
                        total_cost DECIMAL(15,2),
                        total_cost_currency_id BIGINT,
                        rate_period_type_id BIGINT,
                        deposit_required BOOLEAN,
                        deposit_amount DECIMAL(15,2),
                        deposit_percentage DECIMAL(5,2),
                        deposit_currency_id BIGINT,
                        deposit_paid BOOLEAN,
                        deposit_paid_at TIMESTAMPTZ,
                        deposit_payment_reference VARCHAR,
                        approval_type_id BIGINT,
                        booking_status VARCHAR(50),
                        approved_by BIGINT,
                        approved_at TIMESTAMPTZ,
                        approval_notes TEXT,
                        rejected_by BIGINT,
                        rejected_at TIMESTAMPTZ,
                        rejection_reason TEXT,
                        workflow_request_queues_id BIGINT,
                        cancellation_enabled BOOLEAN,
                        cancellation_notice_hours INT,
                        cancellation_fee_enabled BOOLEAN,
                        cancellation_fee_type VARCHAR(50),
                        cancellation_fee_amount DECIMAL(15,2),
                        cancellation_fee_percentage DECIMAL(5,2),
                        cancelled_at TIMESTAMPTZ,
                        cancelled_by BIGINT,
                        cancellation_reason TEXT,
                        cancellation_fee_applied BOOLEAN,
                        is_multi_slot BOOLEAN,
                        slot_sequence INT,
                        total_slots INT,
                        scheduled_checkin_at TIMESTAMPTZ,
                        scheduled_checkout_at TIMESTAMPTZ,
                        actual_checkin_at TIMESTAMPTZ,
                        actual_checkout_at TIMESTAMPTZ,
                        checked_in_by BIGINT,
                        checked_out_by BIGINT,
                        checkin_notes TEXT,
                        checkout_notes TEXT,
                        asset_condition_before VARCHAR(50),
                        asset_condition_after VARCHAR(50),
                        condition_notes TEXT,
                        reminder_enabled BOOLEAN,
                        reminder_schedule JSONB,
                        last_reminder_sent_at TIMESTAMPTZ,
                        reminder_count INT,
                        purpose_type_id BIGINT,
                        custom_purpose_name VARCHAR,
                        custom_purpose_description TEXT,
                        usage_requirements JSONB,
                        tenant_id BIGINT,
                        partition_key VARCHAR(50),
                        booking_created_by_user_id BIGINT,
                        attachments JSONB,
                        isactive BOOLEAN,
                        created_by BIGINT,
                        updated_by BIGINT,
                        created_ip INET,
                        updated_ip INET,
                        deleted_at TIMESTAMPTZ,
                        created_at TIMESTAMPTZ,
                        updated_at TIMESTAMPTZ
                    ) SERVER %I OPTIONS (schema_name %L, table_name %L)',
                    'tenant_booking_items_fdw_' || p_tenant_id,
                    v_fdw_server_name,
                    'public',
                    'asset_booking_items'
                );

                -- Create foreign table for tenant's asset_booking_time_slots
                EXECUTE format('
                    CREATE FOREIGN TABLE IF NOT EXISTS %I (
                        id BIGINT,
                        asset_booking_item_id BIGINT,
                        availability_schedule_id BIGINT,
                        start_datetime TIMESTAMPTZ,
                        end_datetime TIMESTAMPTZ,
                        duration_hours DECIMAL(8,2),
                        timezone VARCHAR(50),
                        sequence_order INT,
                        total_slots_in_booking INT,
                        is_break_between_slots BOOLEAN,
                        break_duration_minutes INT,
                        slot_status VARCHAR(50),
                        slot_rate DECIMAL(15,4),
                        slot_cost DECIMAL(15,2),
                        slot_currency_id BIGINT,
                        slot_checkin_at TIMESTAMPTZ,
                        slot_checkout_at TIMESTAMPTZ,
                        slot_checkin_by BIGINT,
                        slot_checkout_by BIGINT,
                        slot_notes TEXT,
                        asset_condition_start VARCHAR(50),
                        asset_condition_end VARCHAR(50),
                        condition_photos JSONB,
                        condition_notes TEXT,
                        expected_attendees INT,
                        actual_attendees INT,
                        attendee_list JSONB,
                        actual_usage_hours DECIMAL(8,2),
                        usage_efficiency VARCHAR(50),
                        required_equipment JSONB,
                        setup_requirements JSONB,
                        setup_start_time TIMESTAMPTZ,
                        setup_complete_time TIMESTAMPTZ,
                        breakdown_start_time TIMESTAMPTZ,
                        breakdown_complete_time TIMESTAMPTZ,
                        reminder_sent BOOLEAN,
                        reminder_sent_at TIMESTAMPTZ,
                        notification_settings JSONB,
                        all_documents_verified BOOLEAN,
                        documents_verified_by BIGINT,
                        documents_verified_at TIMESTAMPTZ,
                        quality_rating INT,
                        feedback TEXT,
                        feedback_submitted_at TIMESTAMPTZ,
                        tenant_id BIGINT,
                        partition_key VARCHAR(50),
                        custom_attributes JSONB,
                        compliance_checklist JSONB,
                        isactive BOOLEAN,
                        created_by BIGINT,
                        updated_by BIGINT,
                        created_ip INET,
                        updated_ip INET,
                        deleted_at TIMESTAMPTZ,
                        created_at TIMESTAMPTZ,
                        updated_at TIMESTAMPTZ
                    ) SERVER %I OPTIONS (schema_name %L, table_name %L)',
                    'tenant_booking_slots_fdw_' || p_tenant_id,
                    v_fdw_server_name,
                    'public',
                    'asset_booking_time_slots'
                );

                -- Fetch main booking record
                SELECT * INTO v_booking_record
                FROM asset_bookings
                WHERE id = p_booking_id AND tenant_id = p_tenant_id;

                IF NOT FOUND THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT,
                        'Booking not found in main database'::TEXT,
                        NULL::BIGINT,
                        0::INT,
                        0::INT;
                    RETURN;
                END IF;

                -- Insert booking into tenant database
                EXECUTE format('
                    INSERT INTO %I (
                        booking_number, booking_reference, is_self_booking, booking_type_id,
                        parent_booking_id, optiomesh_customer_id, booked_by_user_id,
                        optiomesh_customer_details, attendees_count, booking_status,
                        note, special_requirements, tenant_id, partition_key,
                        isactive, created_by, updated_by, created_ip, updated_ip,
                        created_at, updated_at
                    ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16, $17, $18, $19, $20, $21)
                    RETURNING id',
                    'tenant_bookings_fdw_' || p_tenant_id
                )
                USING
                    v_booking_record.booking_number,
                    v_booking_record.booking_reference,
                    v_booking_record.is_self_booking,
                    v_booking_record.booking_type_id,
                    v_booking_record.parent_booking_id,
                    v_booking_record.optiomesh_customer_id,
                    v_booking_record.booked_by_user_id,
                    v_booking_record.optiomesh_customer_details,
                    v_booking_record.attendees_count,
                    v_booking_record.booking_status,
                    v_booking_record.note,
                    v_booking_record.special_requirements,
                    v_booking_record.tenant_id,
                    v_booking_record.partition_key,
                    v_booking_record.isactive,
                    v_booking_record.created_by,
                    v_booking_record.updated_by,
                    v_booking_record.created_ip,
                    v_booking_record.updated_ip,
                    v_booking_record.created_at,
                    v_booking_record.updated_at
                INTO v_tenant_booking_id;

                -- Replicate booking items
                FOR v_item_record IN
                    SELECT * FROM asset_booking_items
                    WHERE asset_booking_id = p_booking_id
                    ORDER BY id
                LOOP
                    -- Insert item into tenant database
                    EXECUTE format('
                        INSERT INTO %I (
                            asset_booking_id, asset_id, organization_id,
                            location_latitude, location_longitude, location_description,
                            logistics_note, start_datetime, end_datetime, duration_hours,
                            timezone, priority_level, is_recurring, recurring_pattern,
                            unit_rate, rate_currency_id, subtotal, tax_amount,
                            discount_amount, total_cost, total_cost_currency_id,
                            rate_period_type_id, deposit_required, deposit_amount,
                            deposit_percentage, deposit_currency_id, deposit_paid,
                            deposit_paid_at, deposit_payment_reference, approval_type_id,
                            booking_status, approved_by, approved_at, approval_notes,
                            rejected_by, rejected_at, rejection_reason,
                            workflow_request_queues_id, cancellation_enabled,
                            cancellation_notice_hours, cancellation_fee_enabled,
                            cancellation_fee_type, cancellation_fee_amount,
                            cancellation_fee_percentage, cancelled_at, cancelled_by,
                            cancellation_reason, cancellation_fee_applied,
                            is_multi_slot, slot_sequence, total_slots,
                            scheduled_checkin_at, scheduled_checkout_at,
                            actual_checkin_at, actual_checkout_at, checked_in_by,
                            checked_out_by, checkin_notes, checkout_notes,
                            asset_condition_before, asset_condition_after,
                            condition_notes, reminder_enabled, reminder_schedule,
                            last_reminder_sent_at, reminder_count, purpose_type_id,
                            custom_purpose_name, custom_purpose_description,
                            usage_requirements, tenant_id, partition_key,
                            booking_created_by_user_id, attachments, isactive,
                            created_by, updated_by, created_ip, updated_ip,
                            created_at, updated_at
                        ) VALUES (
                            $1, $2, $3, $4, $5, $6, $7, $8, $9, $10,
                            $11, $12, $13, $14, $15, $16, $17, $18, $19, $20,
                            $21, $22, $23, $24, $25, $26, $27, $28, $29, $30,
                            $31, $32, $33, $34, $35, $36, $37, $38, $39, $40,
                            $41, $42, $43, $44, $45, $46, $47, $48, $49, $50,
                            $51, $52, $53, $54, $55, $56, $57, $58, $59, $60,
                            $61, $62, $63, $64, $65, $66, $67, $68, $69, $70,
                            $71, $72, $73, $74, $75, $76, $77, $78, $79, $80
                        ) RETURNING id',
                        'tenant_booking_items_fdw_' || p_tenant_id
                    )
                    USING
                        v_tenant_booking_id, v_item_record.asset_id, v_item_record.organization_id,
                        v_item_record.location_latitude, v_item_record.location_longitude,
                        v_item_record.location_description, v_item_record.logistics_note,
                        v_item_record.start_datetime, v_item_record.end_datetime,
                        v_item_record.duration_hours, v_item_record.timezone,
                        v_item_record.priority_level, v_item_record.is_recurring,
                        v_item_record.recurring_pattern, v_item_record.unit_rate,
                        v_item_record.rate_currency_id, v_item_record.subtotal,
                        v_item_record.tax_amount, v_item_record.discount_amount,
                        v_item_record.total_cost, v_item_record.total_cost_currency_id,
                        v_item_record.rate_period_type_id, v_item_record.deposit_required,
                        v_item_record.deposit_amount, v_item_record.deposit_percentage,
                        v_item_record.deposit_currency_id, v_item_record.deposit_paid,
                        v_item_record.deposit_paid_at, v_item_record.deposit_payment_reference,
                        v_item_record.approval_type_id, v_item_record.booking_status,
                        v_item_record.approved_by, v_item_record.approved_at,
                        v_item_record.approval_notes, v_item_record.rejected_by,
                        v_item_record.rejected_at, v_item_record.rejection_reason,
                        v_item_record.workflow_request_queues_id,
                        v_item_record.cancellation_enabled,
                        v_item_record.cancellation_notice_hours,
                        v_item_record.cancellation_fee_enabled,
                        v_item_record.cancellation_fee_type,
                        v_item_record.cancellation_fee_amount,
                        v_item_record.cancellation_fee_percentage,
                        v_item_record.cancelled_at, v_item_record.cancelled_by,
                        v_item_record.cancellation_reason,
                        v_item_record.cancellation_fee_applied,
                        v_item_record.is_multi_slot, v_item_record.slot_sequence,
                        v_item_record.total_slots, v_item_record.scheduled_checkin_at,
                        v_item_record.scheduled_checkout_at,
                        v_item_record.actual_checkin_at, v_item_record.actual_checkout_at,
                        v_item_record.checked_in_by, v_item_record.checked_out_by,
                        v_item_record.checkin_notes, v_item_record.checkout_notes,
                        v_item_record.asset_condition_before,
                        v_item_record.asset_condition_after,
                        v_item_record.condition_notes, v_item_record.reminder_enabled,
                        v_item_record.reminder_schedule,
                        v_item_record.last_reminder_sent_at,
                        v_item_record.reminder_count, v_item_record.purpose_type_id,
                        v_item_record.custom_purpose_name,
                        v_item_record.custom_purpose_description,
                        v_item_record.usage_requirements, v_item_record.tenant_id,
                        v_item_record.partition_key,
                        v_item_record.booking_created_by_user_id,
                        v_item_record.attachments, v_item_record.isactive,
                        v_item_record.created_by, v_item_record.updated_by,
                        v_item_record.created_ip, v_item_record.updated_ip,
                        v_item_record.created_at, v_item_record.updated_at
                    INTO v_tenant_item_id;

                    v_items_count := v_items_count + 1;

                    -- Replicate time slots for this item
                    FOR v_slot_record IN
                        SELECT * FROM asset_booking_time_slots
                        WHERE asset_booking_item_id = v_item_record.id
                        ORDER BY sequence_order
                    LOOP
                        EXECUTE format('
                            INSERT INTO %I (
                                asset_booking_item_id, availability_schedule_id,
                                start_datetime, end_datetime, duration_hours, timezone,
                                sequence_order, total_slots_in_booking,
                                is_break_between_slots, break_duration_minutes,
                                slot_status, slot_rate, slot_cost, slot_currency_id,
                                slot_checkin_at, slot_checkout_at, slot_checkin_by,
                                slot_checkout_by, slot_notes, asset_condition_start,
                                asset_condition_end, condition_photos, condition_notes,
                                expected_attendees, actual_attendees, attendee_list,
                                actual_usage_hours, usage_efficiency, required_equipment,
                                setup_requirements, setup_start_time, setup_complete_time,
                                breakdown_start_time, breakdown_complete_time,
                                reminder_sent, reminder_sent_at, notification_settings,
                                all_documents_verified, documents_verified_by,
                                documents_verified_at, quality_rating, feedback,
                                feedback_submitted_at, tenant_id, partition_key,
                                custom_attributes, compliance_checklist, isactive,
                                created_by, updated_by, created_ip, updated_ip,
                                created_at, updated_at
                            ) VALUES (
                                $1, $2, $3, $4, $5, $6, $7, $8, $9, $10,
                                $11, $12, $13, $14, $15, $16, $17, $18, $19, $20,
                                $21, $22, $23, $24, $25, $26, $27, $28, $29, $30,
                                $31, $32, $33, $34, $35, $36, $37, $38, $39, $40,
                                $41, $42, $43, $44, $45, $46, $47, $48, $49, $50,
                                $51, $52, $53
                            )',
                            'tenant_booking_slots_fdw_' || p_tenant_id
                        )
                        USING
                            v_tenant_item_id, v_slot_record.availability_schedule_id,
                            v_slot_record.start_datetime, v_slot_record.end_datetime,
                            v_slot_record.duration_hours, v_slot_record.timezone,
                            v_slot_record.sequence_order, v_slot_record.total_slots_in_booking,
                            v_slot_record.is_break_between_slots,
                            v_slot_record.break_duration_minutes,
                            v_slot_record.slot_status, v_slot_record.slot_rate,
                            v_slot_record.slot_cost, v_slot_record.slot_currency_id,
                            v_slot_record.slot_checkin_at, v_slot_record.slot_checkout_at,
                            v_slot_record.slot_checkin_by, v_slot_record.slot_checkout_by,
                            v_slot_record.slot_notes, v_slot_record.asset_condition_start,
                            v_slot_record.asset_condition_end, v_slot_record.condition_photos,
                            v_slot_record.condition_notes, v_slot_record.expected_attendees,
                            v_slot_record.actual_attendees, v_slot_record.attendee_list,
                            v_slot_record.actual_usage_hours, v_slot_record.usage_efficiency,
                            v_slot_record.required_equipment, v_slot_record.setup_requirements,
                            v_slot_record.setup_start_time, v_slot_record.setup_complete_time,
                            v_slot_record.breakdown_start_time,
                            v_slot_record.breakdown_complete_time,
                            v_slot_record.reminder_sent, v_slot_record.reminder_sent_at,
                            v_slot_record.notification_settings,
                            v_slot_record.all_documents_verified,
                            v_slot_record.documents_verified_by,
                            v_slot_record.documents_verified_at,
                            v_slot_record.quality_rating, v_slot_record.feedback,
                            v_slot_record.feedback_submitted_at,
                            v_slot_record.tenant_id, v_slot_record.partition_key,
                            v_slot_record.custom_attributes, v_slot_record.compliance_checklist,
                            v_slot_record.isactive, v_slot_record.created_by,
                            v_slot_record.updated_by, v_slot_record.created_ip,
                            v_slot_record.updated_ip, v_slot_record.created_at,
                            v_slot_record.updated_at;

                        v_slots_count := v_slots_count + 1;
                    END LOOP;
                END LOOP;

                -- Cleanup: Drop foreign tables and server
                EXECUTE format('DROP FOREIGN TABLE IF EXISTS %I CASCADE', 'tenant_bookings_fdw_' || p_tenant_id);
                EXECUTE format('DROP FOREIGN TABLE IF EXISTS %I CASCADE', 'tenant_booking_items_fdw_' || p_tenant_id);
                EXECUTE format('DROP FOREIGN TABLE IF EXISTS %I CASCADE', 'tenant_booking_slots_fdw_' || p_tenant_id);
                EXECUTE format('DROP SERVER IF EXISTS %I CASCADE', v_fdw_server_name);

                -- Return success
                RETURN QUERY SELECT
                    'SUCCESS'::TEXT,
                    format('Successfully replicated booking to tenant database: %s items, %s slots', v_items_count, v_slots_count)::TEXT,
                    v_tenant_booking_id,
                    v_items_count,
                    v_slots_count;

            EXCEPTION
                WHEN OTHERS THEN
                    -- Cleanup on error
                    BEGIN
                        EXECUTE format('DROP FOREIGN TABLE IF EXISTS %I CASCADE', 'tenant_bookings_fdw_' || p_tenant_id);
                        EXECUTE format('DROP FOREIGN TABLE IF EXISTS %I CASCADE', 'tenant_booking_items_fdw_' || p_tenant_id);
                        EXECUTE format('DROP FOREIGN TABLE IF EXISTS %I CASCADE', 'tenant_booking_slots_fdw_' || p_tenant_id);
                        EXECUTE format('DROP SERVER IF EXISTS %I CASCADE', v_fdw_server_name);
                    EXCEPTION
                        WHEN OTHERS THEN NULL;
                    END;

                    -- Return error
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT,
                        format('Error replicating to tenant database: %s', SQLERRM)::TEXT,
                        NULL::BIGINT,
                        0::INT,
                        0::INT;
            END;
            $$;
        SQL);

        // Add comment
        DB::statement("COMMENT ON FUNCTION replicate_booking_to_tenant_db IS 'Replicates booking data from main database to tenant-specific database using postgres_fdw'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS replicate_booking_to_tenant_db CASCADE');
    }
};
