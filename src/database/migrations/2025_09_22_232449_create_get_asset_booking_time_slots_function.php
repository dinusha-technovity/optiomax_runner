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
            CREATE OR REPLACE FUNCTION get_asset_booking_time_slots(
                p_action_type TEXT DEFAULT 'Normal',
                p_tenant_id BIGINT DEFAULT NULL,
                p_timezone TEXT DEFAULT NULL,
                p_booking_id BIGINT DEFAULT NULL,
                p_asset_id BIGINT DEFAULT NULL,
                p_start_datetime TIMESTAMPTZ DEFAULT NULL,
                p_end_datetime TIMESTAMPTZ DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                time_slot_id BIGINT,
                booking_id BIGINT,
                asset_id BIGINT,
                asset_name TEXT,
                schedule_occurrence_id BIGINT,
                slot_start TIMESTAMPTZ,
                slot_end TIMESTAMPTZ,
                duration_hours NUMERIC
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                slot_count INT;
            BEGIN
                -- Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT, 'Invalid tenant ID provided',
                        NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::BIGINT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::NUMERIC;
                    RETURN;
                END IF;

                -- Count matching slots
                SELECT COUNT(*)
                INTO slot_count
                FROM asset_booking_time_slots t
                JOIN asset_bookings b ON t.booking_id = b.id
                JOIN asset_items ai ON b.asset_id = ai.id
                LEFT JOIN assets a ON ai.asset_id = a.id
                WHERE (p_booking_id IS NULL OR t.booking_id = p_booking_id)
                AND b.asset_id = COALESCE(p_asset_id, b.asset_id)
                AND b.tenant_id = p_tenant_id
                AND (p_start_datetime IS NULL OR t.end_datetime >= p_start_datetime)
                AND (p_end_datetime IS NULL OR t.start_datetime <= p_end_datetime)
                AND t.deleted_at IS NULL
                AND t.isactive = TRUE
                AND t.approval_status IN ('APPROVED', 'PENDING');

                IF slot_count = 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT, 'No matching time slots found',
                        NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::BIGINT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::NUMERIC;
                    RETURN;
                END IF;

                -- Return matching slots
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Booking time slots fetched successfully'::TEXT AS message,
                    t.id AS time_slot_id,
                    t.booking_id,
                    b.asset_id,
                    a.name::TEXT AS asset_name,
                    t.asset_availability_schedule_occurrences_id,
                    t.start_datetime AS slot_start,
                    t.end_datetime AS slot_end,
                    t.duration_hours
                FROM asset_booking_time_slots t
                JOIN asset_bookings b ON t.booking_id = b.id
                JOIN asset_items ai ON b.asset_id = ai.id
                LEFT JOIN assets a ON ai.asset_id = a.id
                WHERE (p_booking_id IS NULL OR t.booking_id = p_booking_id)
                AND b.asset_id = COALESCE(p_asset_id, b.asset_id)
                AND b.tenant_id = p_tenant_id
                AND (p_start_datetime IS NULL OR t.end_datetime >= p_start_datetime)
                AND (p_end_datetime IS NULL OR t.start_datetime <= p_end_datetime)
                AND t.deleted_at IS NULL
                AND t.isactive = TRUE
                AND t.approval_status IN ('APPROVED', 'PENDING')
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
        DB::unprepared("DROP FUNCTION IF EXISTS get_asset_booking_time_slots(TEXT, BIGINT, TEXT, BIGINT, BIGINT, TIMESTAMPTZ, TIMESTAMPTZ);");
    }
};