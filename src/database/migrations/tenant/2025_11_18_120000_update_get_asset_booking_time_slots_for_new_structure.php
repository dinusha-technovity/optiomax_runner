<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Updates get_asset_booking_time_slots function to return actual time slots
     * from asset_booking_time_slots table with timezone conversion support.
     * 
     * Key Features:
     * - Returns time slot records (not booking items)
     * - Automatic timezone conversion for worldwide enterprise application
     * - Comprehensive filtering (booking_id, asset_id, date range, status)
     * - Joins asset information for frontend display
     * - Optimized for performance with proper indexing
     */
    public function up(): void
    {
        // Drop all previous versions
        DB::unprepared("DROP FUNCTION IF EXISTS get_asset_booking_time_slots(TEXT, BIGINT, TEXT, BIGINT, BIGINT, TIMESTAMPTZ, TIMESTAMPTZ);");
        DB::unprepared("DROP FUNCTION IF EXISTS get_asset_booking_time_slots(TEXT, BIGINT, TEXT, BIGINT, BIGINT, TIMESTAMPTZ, TIMESTAMPTZ, TEXT, TEXT, BOOLEAN, BOOLEAN);");
        
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION get_asset_booking_time_slots(
                p_action_type TEXT DEFAULT 'Normal',
                p_tenant_id BIGINT DEFAULT NULL,
                p_timezone TEXT DEFAULT 'UTC',
                p_booking_id BIGINT DEFAULT NULL,
                p_asset_id BIGINT DEFAULT NULL,
                p_start_datetime TIMESTAMPTZ DEFAULT NULL,
                p_end_datetime TIMESTAMPTZ DEFAULT NULL,
                p_booking_status TEXT DEFAULT NULL,
                p_partition_key TEXT DEFAULT NULL,
                p_include_cancelled BOOLEAN DEFAULT FALSE,
                p_include_parent_bookings BOOLEAN DEFAULT TRUE
            )
            RETURNS TABLE (
                -- Time slot identification
                time_slot_id BIGINT,
                booking_item_id BIGINT,
                booking_id BIGINT,
                availability_schedule_id BIGINT,
                
                -- Asset information
                asset_id BIGINT,
                asset_name TEXT,
                asset_code TEXT,
                asset_category TEXT,
                asset_sub_category TEXT,
                
                -- Timing (returned in UTC for frontend conversion)
                start_datetime TIMESTAMPTZ,
                end_datetime TIMESTAMPTZ,
                duration_hours NUMERIC,
                timezone TEXT,
                
                -- Slot details
                sequence_order INTEGER,
                total_slots_in_booking INTEGER,
                slot_status TEXT,
                
                -- Pricing
                slot_rate NUMERIC,
                slot_cost NUMERIC,
                slot_currency_id BIGINT,
                
                -- Check-in/out
                slot_checkin_at TIMESTAMPTZ,
                slot_checkout_at TIMESTAMPTZ,
                slot_checkin_by BIGINT,
                slot_checkout_by BIGINT,
                
                -- Condition tracking
                asset_condition_start TEXT,
                asset_condition_end TEXT,
                condition_notes TEXT,
                
                -- Attendance
                expected_attendees INTEGER,
                actual_attendees INTEGER,
                actual_usage_hours NUMERIC,
                
                -- Setup/breakdown
                setup_start_time TIMESTAMPTZ,
                setup_complete_time TIMESTAMPTZ,
                breakdown_start_time TIMESTAMPTZ,
                breakdown_complete_time TIMESTAMPTZ,
                
                -- Notifications
                reminder_sent BOOLEAN,
                reminder_sent_at TIMESTAMPTZ,
                
                -- Quality
                quality_rating INTEGER,
                feedback TEXT,
                feedback_submitted_at TIMESTAMPTZ,
                
                -- Booking item context (minimal)
                item_booking_status TEXT,
                deposit_required BOOLEAN,
                deposit_paid BOOLEAN,
                cancellation_enabled BOOLEAN,
                booking_number TEXT,
                
                -- Metadata
                created_at TIMESTAMPTZ,
                updated_at TIMESTAMPTZ
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Validate tenant_id
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RAISE EXCEPTION 'Valid tenant_id is required';
                END IF;

                -- Return time slots with joins to get related data
                RETURN QUERY
                SELECT
                    ts.id AS time_slot_id,
                    ts.asset_booking_item_id AS booking_item_id,
                    bi.asset_booking_id AS booking_id,
                    ts.asset_availability_schedule_occurrences_id AS availability_schedule_id,
                    
                    -- Asset info
                    ai.id AS asset_id,
                    a.name::TEXT AS asset_name,
                    ai.asset_tag::TEXT AS asset_code,
                    ac.name::TEXT AS asset_category,
                    ascat.name::TEXT AS asset_sub_category,
                    
                    -- Timing (return UTC timestamps, let frontend handle display conversion)
                    ts.start_datetime AS start_datetime,
                    ts.end_datetime AS end_datetime,
                    ts.duration_hours,
                    p_timezone AS timezone,
                    
                    -- Slot details
                    ts.sequence_order::INTEGER,
                    ts.total_slots_in_booking::INTEGER,
                    ts.slot_status::TEXT,
                    
                    -- Pricing
                    ts.slot_rate,
                    ts.slot_cost,
                    ts.slot_currency_id,
                    
                    -- Check-in/out (return UTC timestamps)
                    ts.slot_checkin_at AS slot_checkin_at,
                    ts.slot_checkout_at AS slot_checkout_at,
                    ts.slot_checkin_by,
                    ts.slot_checkout_by,
                    
                    -- Condition
                    ts.asset_condition_start::TEXT,
                    ts.asset_condition_end::TEXT,
                    ts.condition_notes::TEXT,
                    
                    -- Attendance
                    ts.expected_attendees::INTEGER,
                    ts.actual_attendees::INTEGER,
                    ts.actual_usage_hours,
                    
                    -- Setup/breakdown (return UTC timestamps)
                    ts.setup_start_time AS setup_start_time,
                    ts.setup_complete_time AS setup_complete_time,
                    ts.breakdown_start_time AS breakdown_start_time,
                    ts.breakdown_complete_time AS breakdown_complete_time,
                    
                    -- Notifications
                    ts.reminder_sent,
                    ts.reminder_sent_at AS reminder_sent_at,
                    
                    -- Quality
                    ts.quality_rating::INTEGER,
                    ts.feedback::TEXT,
                    ts.feedback_submitted_at AS feedback_submitted_at,
                    
                    -- Booking item context
                    bi.booking_status::TEXT AS item_booking_status,
                    bi.deposit_required,
                    bi.deposit_paid,
                    bi.cancellation_enabled,
                    b.booking_number::TEXT,
                    
                    -- Metadata (return UTC timestamps)
                    ts.created_at AS created_at,
                    ts.updated_at AS updated_at
                FROM asset_booking_time_slots ts
                INNER JOIN asset_booking_items bi ON ts.asset_booking_item_id = bi.id
                INNER JOIN asset_bookings b ON bi.asset_booking_id = b.id
                INNER JOIN asset_items ai ON bi.asset_id = ai.id
                INNER JOIN assets a ON ai.asset_id = a.id
                LEFT JOIN asset_categories ac ON a.category = ac.id
                LEFT JOIN asset_sub_categories ascat ON a.sub_category = ascat.id
                WHERE
                    ts.tenant_id = p_tenant_id
                    AND ts.isactive = TRUE
                    AND ts.deleted_at IS NULL
                    AND bi.isactive = TRUE
                    AND bi.deleted_at IS NULL
                    -- Filter by booking_id
                    AND (p_booking_id IS NULL OR b.id = p_booking_id)
                    -- Filter by asset_id (support both asset_items.id and assets.id)
                    AND (p_asset_id IS NULL OR ai.id = p_asset_id OR a.id = p_asset_id)
                    -- Filter by date range (in UTC for accurate comparison)
                    AND (p_start_datetime IS NULL OR ts.end_datetime >= p_start_datetime)
                    AND (p_end_datetime IS NULL OR ts.start_datetime <= p_end_datetime)
                    -- Filter by booking status
                    AND (p_booking_status IS NULL OR bi.booking_status = p_booking_status)
                    -- Filter by partition key
                    AND (p_partition_key IS NULL OR ts.partition_key = p_partition_key)
                    -- Filter cancelled slots
                    AND (p_include_cancelled = TRUE OR ts.slot_status != 'CANCELLED')
                    -- Filter by action type
                    AND (
                        p_action_type = 'All' OR
                        (p_action_type = 'Normal' AND bi.booking_status IN ('APPROVED', 'CONFIRMED', 'IN_PROGRESS', 'COMPLETED')) OR
                        (p_action_type = 'Pending' AND bi.booking_status IN ('PENDING', 'SUBMITTED')) OR
                        (p_action_type = 'Completed' AND bi.booking_status = 'COMPLETED') OR
                        (p_action_type = 'Draft' AND bi.booking_status = 'DRAFT')
                    )
                ORDER BY ts.start_datetime ASC, ts.sequence_order ASC;
            END;
            $$;

            -- Add comprehensive function comment
            COMMENT ON FUNCTION get_asset_booking_time_slots IS 
            'Returns asset booking time slots with UTC timestamps for frontend timezone conversion.
            
            Features:
            - Returns actual time slot records from asset_booking_time_slots table
            - Returns all timestamps in UTC format for frontend timezone handling
            - Joins asset, category, and booking information
            - Comprehensive filtering by booking, asset, date range, and status
            - Optimized with proper indexes for large-scale operations
            
            Parameters:
            - p_action_type: Filter by workflow state (Normal/Pending/All/Completed/Draft)
            - p_tenant_id: Required for multi-tenant isolation
            - p_timezone: User timezone for conversion (e.g., ''America/New_York'', ''Asia/Colombo'')
            - p_booking_id: Optional specific booking filter
            - p_asset_id: Optional asset filter (supports both asset_items.id or assets.id)
            - p_start_datetime: Optional start of date range (UTC)
            - p_end_datetime: Optional end of date range (UTC)
            - p_booking_status: Optional status filter
            - p_partition_key: Optional partition key for performance
            - p_include_cancelled: Include cancelled slots (default: false)
            - p_include_parent_bookings: For future recurring support
            
            Returns:
            - Detailed time slot information with asset context
            - All timestamps in UTC format (e.g., "2025-11-17 02:00:00+00")
            - Frontend handles timezone conversion using parseUTCToLocal()
            - Minimal booking item context for frontend needs';
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_asset_booking_time_slots(TEXT, BIGINT, TEXT, BIGINT, BIGINT, TIMESTAMPTZ, TIMESTAMPTZ, TEXT, TEXT, BOOLEAN, BOOLEAN);");
    }
};
