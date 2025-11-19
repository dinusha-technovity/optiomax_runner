<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Normalizes all existing booking time slot timestamps to UTC.
     * This ensures consistent timezone handling for global application.
     * 
     * Issue: Some slots were stored with local timezone offsets (e.g., +05:30)
     * Fix: Convert all timestamps to pure UTC (+00:00) for consistent storage
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            -- Normalize all booking time slot timestamps to UTC
            UPDATE asset_booking_time_slots
            SET 
                start_datetime = start_datetime AT TIME ZONE 'UTC',
                end_datetime = end_datetime AT TIME ZONE 'UTC',
                timezone = 'UTC',
                slot_checkin_at = CASE 
                    WHEN slot_checkin_at IS NOT NULL 
                    THEN slot_checkin_at AT TIME ZONE 'UTC'
                    ELSE NULL
                END,
                slot_checkout_at = CASE 
                    WHEN slot_checkout_at IS NOT NULL 
                    THEN slot_checkout_at AT TIME ZONE 'UTC'
                    ELSE NULL
                END,
                setup_start_time = CASE 
                    WHEN setup_start_time IS NOT NULL 
                    THEN setup_start_time AT TIME ZONE 'UTC'
                    ELSE NULL
                END,
                setup_complete_time = CASE 
                    WHEN setup_complete_time IS NOT NULL 
                    THEN setup_complete_time AT TIME ZONE 'UTC'
                    ELSE NULL
                END,
                breakdown_start_time = CASE 
                    WHEN breakdown_start_time IS NOT NULL 
                    THEN breakdown_start_time AT TIME ZONE 'UTC'
                    ELSE NULL
                END,
                breakdown_complete_time = CASE 
                    WHEN breakdown_complete_time IS NOT NULL 
                    THEN breakdown_complete_time AT TIME ZONE 'UTC'
                    ELSE NULL
                END,
                reminder_sent_at = CASE 
                    WHEN reminder_sent_at IS NOT NULL 
                    THEN reminder_sent_at AT TIME ZONE 'UTC'
                    ELSE NULL
                END,
                feedback_submitted_at = CASE 
                    WHEN feedback_submitted_at IS NOT NULL 
                    THEN feedback_submitted_at AT TIME ZONE 'UTC'
                    ELSE NULL
                END,
                created_at = created_at AT TIME ZONE 'UTC',
                updated_at = updated_at AT TIME ZONE 'UTC'
            WHERE deleted_at IS NULL;
        SQL);

        // Also normalize booking items if they have timezone offsets
        DB::statement(<<<'SQL'
            UPDATE asset_booking_items
            SET 
                start_datetime = start_datetime AT TIME ZONE 'UTC',
                end_datetime = end_datetime AT TIME ZONE 'UTC',
                timezone = 'UTC',
                scheduled_checkin_at = CASE 
                    WHEN scheduled_checkin_at IS NOT NULL 
                    THEN scheduled_checkin_at AT TIME ZONE 'UTC'
                    ELSE NULL
                END,
                scheduled_checkout_at = CASE 
                    WHEN scheduled_checkout_at IS NOT NULL 
                    THEN scheduled_checkout_at AT TIME ZONE 'UTC'
                    ELSE NULL
                END,
                actual_checkin_at = CASE 
                    WHEN actual_checkin_at IS NOT NULL 
                    THEN actual_checkin_at AT TIME ZONE 'UTC'
                    ELSE NULL
                END,
                actual_checkout_at = CASE 
                    WHEN actual_checkout_at IS NOT NULL 
                    THEN actual_checkout_at AT TIME ZONE 'UTC'
                    ELSE NULL
                END,
                approved_at = CASE 
                    WHEN approved_at IS NOT NULL 
                    THEN approved_at AT TIME ZONE 'UTC'
                    ELSE NULL
                END,
                rejected_at = CASE 
                    WHEN rejected_at IS NOT NULL 
                    THEN rejected_at AT TIME ZONE 'UTC'
                    ELSE NULL
                END,
                cancelled_at = CASE 
                    WHEN cancelled_at IS NOT NULL 
                    THEN cancelled_at AT TIME ZONE 'UTC'
                    ELSE NULL
                END,
                deposit_paid_at = CASE 
                    WHEN deposit_paid_at IS NOT NULL 
                    THEN deposit_paid_at AT TIME ZONE 'UTC'
                    ELSE NULL
                END,
                created_at = created_at AT TIME ZONE 'UTC',
                updated_at = updated_at AT TIME ZONE 'UTC'
            WHERE deleted_at IS NULL;
        SQL);

        // Also normalize main bookings table
        DB::statement(<<<'SQL'
            UPDATE asset_bookings
            SET 
                created_at = created_at AT TIME ZONE 'UTC',
                updated_at = updated_at AT TIME ZONE 'UTC'
            WHERE deleted_at IS NULL;
        SQL);
    }

    /**
     * Reverse the migrations.
     * 
     * Note: This is a data normalization migration.
     * Rollback is not provided as it would require knowing the original timezone
     * of each record, which is not stored.
     */
    public function down(): void
    {
        // Cannot reverse timezone normalization without knowing original timezones
        // Data is already in UTC which is the correct format
    }
};
