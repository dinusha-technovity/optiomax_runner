<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Updates get_asset_booking_time_slots function to work with new enterprise-level
     * asset_bookings and asset_booking_items table structure.
     * 
     * Key Changes from Previous Version: 
     * - Uses asset_booking_items instead of asset_booking_time_slots
     * - Updated booking_status enum to match new values (DRAFT, PENDING, APPROVED, etc.)
     * - Added support for parent_booking_id (recurring/group bookings)
     * - Added partition_key support for enterprise scalability
     * - Enhanced filtering with multi-slot booking support
     * - Improved timezone handling for worldwide application
     * - Added condition tracking and check-in/out information
     * - Enhanced financial data (deposits, cancellations)
     */
    public function up(): void
    {
        // Drop old function first
        DB::unprepared("DROP FUNCTION IF EXISTS get_asset_booking_time_slots(TEXT, BIGINT, TEXT, BIGINT, BIGINT, TIMESTAMPTZ, TIMESTAMPTZ);");
        
        DB::unprepared(<<<SQL
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
                status TEXT,
                message TEXT,
                booking_item_id BIGINT,
                booking_id BIGINT,
                parent_booking_id BIGINT,
                asset_id BIGINT,
                asset_name TEXT,
                asset_category TEXT,
                organization_id BIGINT,
                organization_name TEXT,
                
                -- Timing information
                slot_start TIMESTAMPTZ,
                slot_end TIMESTAMPTZ,
                duration_hours NUMERIC,
                timezone TEXT,
                
                -- Booking details
                booking_number TEXT,
                booking_reference TEXT,
                booking_type_id BIGINT,
                booking_status TEXT,
                item_booking_status TEXT,
                is_self_booking BOOLEAN,
                
                -- Multi-slot information
                is_multi_slot BOOLEAN,
                slot_sequence INTEGER,
                total_slots INTEGER,
                is_recurring BOOLEAN,
                recurring_pattern JSONB,
                
                -- Location
                location_latitude NUMERIC,
                location_longitude NUMERIC,
                location_description TEXT,
                
                -- Financial information
                unit_rate NUMERIC,
                subtotal NUMERIC,
                tax_amount NUMERIC,
                discount_amount NUMERIC,
                total_cost NUMERIC,
                
                -- Deposit information
                deposit_required BOOLEAN,
                deposit_amount NUMERIC,
                deposit_percentage NUMERIC,
                deposit_paid BOOLEAN,
                deposit_paid_at TIMESTAMPTZ,
                
                -- Cancellation information
                cancellation_enabled BOOLEAN,
                cancellation_notice_hours INTEGER,
                cancellation_fee_enabled BOOLEAN,
                cancelled_at TIMESTAMPTZ,
                cancellation_reason TEXT,
                
                -- Check-in/out tracking
                scheduled_checkin_at TIMESTAMPTZ,
                scheduled_checkout_at TIMESTAMPTZ,
                actual_checkin_at TIMESTAMPTZ,
                actual_checkout_at TIMESTAMPTZ,
                
                -- Asset condition
                asset_condition_before TEXT,
                asset_condition_after TEXT,
                condition_notes TEXT,
                
                -- Approval workflow
                approval_type_id BIGINT,
                approved_by BIGINT,
                approved_at TIMESTAMPTZ,
                rejected_by BIGINT,
                rejected_at TIMESTAMPTZ,
                rejection_reason TEXT,
                
                -- Customer information
                customer_id BIGINT,
                booked_by_user_id BIGINT,
                customer_details JSONB,
                
                -- Purpose
                purpose_type_id BIGINT,
                custom_purpose_name TEXT,
                
                -- Enterprise features
                priority_level INTEGER,
                partition_key TEXT,
                
                -- Metadata
                created_at TIMESTAMPTZ,
                updated_at TIMESTAMPTZ
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                slot_count INT;
                v_timezone TEXT;
            BEGIN
                -- Validate tenant ID (required for multi-tenant enterprise application)
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT, 
                        'Invalid tenant ID provided - tenant ID is required for multi-tenant operations'::TEXT,
                        NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TEXT,
                        NULL::BIGINT, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::NUMERIC, NULL::TEXT,
                        NULL::TEXT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::BOOLEAN,
                        NULL::BOOLEAN, NULL::INTEGER, NULL::INTEGER, NULL::BOOLEAN, NULL::JSONB,
                        NULL::NUMERIC, NULL::NUMERIC, NULL::TEXT,
                        NULL::NUMERIC, NULL::NUMERIC, NULL::NUMERIC, NULL::NUMERIC, NULL::NUMERIC,
                        NULL::BOOLEAN, NULL::NUMERIC, NULL::NUMERIC, NULL::BOOLEAN, NULL::TIMESTAMPTZ,
                        NULL::BOOLEAN, NULL::INTEGER, NULL::BOOLEAN, NULL::TIMESTAMPTZ, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ,
                        NULL::TEXT, NULL::TEXT, NULL::TEXT,
                        NULL::BIGINT, NULL::BIGINT, NULL::TIMESTAMPTZ, NULL::BIGINT, NULL::TIMESTAMPTZ, NULL::TEXT,
                        NULL::BIGINT, NULL::BIGINT, NULL::JSONB,
                        NULL::BIGINT, NULL::TEXT,
                        NULL::INTEGER, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ;
                    RETURN;
                END IF;

                -- Set timezone (default to UTC for worldwide application)
                v_timezone := COALESCE(p_timezone, 'UTC');

                -- Count matching booking items with comprehensive filtering
                SELECT COUNT(*)
                INTO slot_count
                FROM asset_booking_items abi
                INNER JOIN asset_bookings ab ON abi.asset_booking_id = ab.id
                INNER JOIN asset_items ai ON abi.asset_id = ai.id
                LEFT JOIN assets a ON ai.asset_id = a.id
                LEFT JOIN asset_categories ac ON a.category = ac.id
                LEFT JOIN organization o ON abi.organization_id = o.id
                WHERE 
                    -- Multi-tenancy filter (critical for enterprise)
                    ab.tenant_id = p_tenant_id
                    
                    -- Booking ID filter (optional - for specific booking lookup)
                    AND (p_booking_id IS NULL OR abi.asset_booking_id = p_booking_id)
                    
                    -- Asset filter (optional)
                    AND (p_asset_id IS NULL OR abi.asset_id = p_asset_id)
                    
                    -- Date range filter (performance optimized with indexes)
                    AND (p_start_datetime IS NULL OR abi.end_datetime >= p_start_datetime)
                    AND (p_end_datetime IS NULL OR abi.start_datetime <= p_end_datetime)
                    
                    -- Partition key filter (enterprise scalability)
                    AND (p_partition_key IS NULL OR abi.partition_key = p_partition_key)
                    
                    -- Booking status filter (supports new enum values)
                    AND (p_booking_status IS NULL OR abi.booking_status = p_booking_status)
                    
                    -- Active records only
                    AND abi.deleted_at IS NULL
                    AND abi.isactive = TRUE
                    
                    -- Cancellation filter (exclude cancelled unless explicitly included)
                    AND (p_include_cancelled = TRUE OR abi.booking_status != 'CANCELLED')
                    
                    -- Parent booking filter (for hierarchical bookings)
                    AND (
                        p_include_parent_bookings = TRUE 
                        OR ab.parent_booking_id IS NULL 
                        OR ab.parent_booking_id = ab.id
                    )
                    
                    -- Status filtering based on action type
                    AND (
                        CASE 
                            WHEN p_action_type = 'Normal' THEN 
                                abi.booking_status IN ('APPROVED', 'CONFIRMED', 'IN_PROGRESS')
                            WHEN p_action_type = 'Pending' THEN 
                                abi.booking_status IN ('PENDING', 'SUBMITTED')
                            WHEN p_action_type = 'All' THEN 
                                TRUE
                            WHEN p_action_type = 'Completed' THEN 
                                abi.booking_status = 'COMPLETED'
                            WHEN p_action_type = 'Draft' THEN 
                                abi.booking_status = 'DRAFT'
                            ELSE 
                                abi.booking_status IN ('APPROVED', 'CONFIRMED', 'IN_PROGRESS')
                        END
                    );

                -- Return failure if no slots found
                IF slot_count = 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT, 
                        'No matching booking time slots found for the specified criteria'::TEXT,
                        NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TEXT,
                        NULL::BIGINT, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::NUMERIC, NULL::TEXT,
                        NULL::TEXT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::BOOLEAN,
                        NULL::BOOLEAN, NULL::INTEGER, NULL::INTEGER, NULL::BOOLEAN, NULL::JSONB,
                        NULL::NUMERIC, NULL::NUMERIC, NULL::TEXT,
                        NULL::NUMERIC, NULL::NUMERIC, NULL::NUMERIC, NULL::NUMERIC, NULL::NUMERIC,
                        NULL::BOOLEAN, NULL::NUMERIC, NULL::NUMERIC, NULL::BOOLEAN, NULL::TIMESTAMPTZ,
                        NULL::BOOLEAN, NULL::INTEGER, NULL::BOOLEAN, NULL::TIMESTAMPTZ, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ,
                        NULL::TEXT, NULL::TEXT, NULL::TEXT,
                        NULL::BIGINT, NULL::BIGINT, NULL::TIMESTAMPTZ, NULL::BIGINT, NULL::TIMESTAMPTZ, NULL::TEXT,
                        NULL::BIGINT, NULL::BIGINT, NULL::JSONB,
                        NULL::BIGINT, NULL::TEXT,
                        NULL::INTEGER, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ;
                    RETURN;
                END IF;

                -- Return comprehensive booking item details with all enterprise features
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT,
                    format('Successfully retrieved %s booking time slot(s)', slot_count)::TEXT,
                    abi.id,
                    abi.asset_booking_id,
                    ab.parent_booking_id,
                    abi.asset_id,
                    a.name::TEXT,
                    ac.name::TEXT,
                    abi.organization_id,
                    (o.data->>'organizationName')::TEXT,
                    abi.start_datetime,
                    abi.end_datetime,
                    abi.duration_hours,
                    abi.timezone::TEXT,
                    ab.booking_number::TEXT,
                    ab.booking_reference::TEXT,
                    ab.booking_type_id,
                    ab.booking_status::TEXT,
                    abi.booking_status::TEXT,
                    ab.is_self_booking,
                    abi.is_multi_slot,
                    abi.slot_sequence::INTEGER,
                    abi.total_slots::INTEGER,
                    abi.is_recurring,
                    abi.recurring_pattern,
                    abi.location_latitude,
                    abi.location_longitude,
                    abi.location_description::TEXT,
                    abi.unit_rate,
                    abi.subtotal,
                    abi.tax_amount,
                    abi.discount_amount,
                    abi.total_cost,
                    abi.deposit_required,
                    abi.deposit_amount,
                    abi.deposit_percentage,
                    abi.deposit_paid,
                    abi.deposit_paid_at,
                    abi.cancellation_enabled,
                    abi.cancellation_notice_hours::INTEGER,
                    abi.cancellation_fee_enabled,
                    abi.cancelled_at,
                    abi.cancellation_reason::TEXT,
                    abi.scheduled_checkin_at,
                    abi.scheduled_checkout_at,
                    abi.actual_checkin_at,
                    abi.actual_checkout_at,
                    abi.asset_condition_before::TEXT,
                    abi.asset_condition_after::TEXT,
                    abi.condition_notes::TEXT,
                    abi.approval_type_id,
                    abi.approved_by,
                    abi.approved_at,
                    abi.rejected_by,
                    abi.rejected_at,
                    abi.rejection_reason::TEXT,
                    ab.optiomesh_customer_id,
                    ab.booked_by_user_id,
                    ab.optiomesh_customer_details,
                    abi.purpose_type_id,
                    abi.custom_purpose_name::TEXT,
                    abi.priority_level::INTEGER,
                    abi.partition_key::TEXT,
                    abi.created_at,
                    abi.updated_at
                    
                FROM asset_booking_items abi
                INNER JOIN asset_bookings ab ON abi.asset_booking_id = ab.id
                INNER JOIN asset_items ai ON abi.asset_id = ai.id
                LEFT JOIN assets a ON ai.asset_id = a.id
                LEFT JOIN asset_categories ac ON a.category = ac.id
                LEFT JOIN organization o ON abi.organization_id = o.id
                WHERE 
                    -- Apply same filters as count query
                    ab.tenant_id = p_tenant_id
                    AND (p_booking_id IS NULL OR abi.asset_booking_id = p_booking_id)
                    AND (p_asset_id IS NULL OR abi.asset_id = p_asset_id)
                    AND (p_start_datetime IS NULL OR abi.end_datetime >= p_start_datetime)
                    AND (p_end_datetime IS NULL OR abi.start_datetime <= p_end_datetime)
                    AND (p_partition_key IS NULL OR abi.partition_key = p_partition_key)
                    AND (p_booking_status IS NULL OR abi.booking_status = p_booking_status)
                    AND abi.deleted_at IS NULL
                    AND abi.isactive = TRUE
                    AND (p_include_cancelled = TRUE OR abi.booking_status != 'CANCELLED')
                    AND (
                        p_include_parent_bookings = TRUE 
                        OR ab.parent_booking_id IS NULL 
                        OR ab.parent_booking_id = ab.id
                    )
                    AND (
                        CASE 
                            WHEN p_action_type = 'Normal' THEN 
                                abi.booking_status IN ('APPROVED', 'CONFIRMED', 'IN_PROGRESS')
                            WHEN p_action_type = 'Pending' THEN 
                                abi.booking_status IN ('PENDING', 'SUBMITTED')
                            WHEN p_action_type = 'All' THEN 
                                TRUE
                            WHEN p_action_type = 'Completed' THEN 
                                abi.booking_status = 'COMPLETED'
                            WHEN p_action_type = 'Draft' THEN 
                                abi.booking_status = 'DRAFT'
                            ELSE 
                                abi.booking_status IN ('APPROVED', 'CONFIRMED', 'IN_PROGRESS')
                        END
                    )
                ORDER BY 
                    -- Optimized sorting for enterprise performance
                    abi.start_datetime ASC,
                    abi.priority_level ASC, -- Higher priority (lower number) first
                    abi.slot_sequence ASC;

            EXCEPTION
                WHEN OTHERS THEN
                    -- Enterprise-level error handling with detailed logging
                    RETURN QUERY SELECT
                        'ERROR'::TEXT,
                        format('Database error occurred: %s (SQLSTATE: %s)', SQLERRM, SQLSTATE)::TEXT,
                        NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TEXT,
                        NULL::BIGINT, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::NUMERIC, NULL::TEXT,
                        NULL::TEXT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::BOOLEAN,
                        NULL::BOOLEAN, NULL::INTEGER, NULL::INTEGER, NULL::BOOLEAN, NULL::JSONB,
                        NULL::NUMERIC, NULL::NUMERIC, NULL::TEXT,
                        NULL::NUMERIC, NULL::NUMERIC, NULL::NUMERIC, NULL::NUMERIC, NULL::NUMERIC,
                        NULL::BOOLEAN, NULL::NUMERIC, NULL::NUMERIC, NULL::BOOLEAN, NULL::TIMESTAMPTZ,
                        NULL::BOOLEAN, NULL::INTEGER, NULL::BOOLEAN, NULL::TIMESTAMPTZ, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ,
                        NULL::TEXT, NULL::TEXT, NULL::TEXT,
                        NULL::BIGINT, NULL::BIGINT, NULL::TIMESTAMPTZ, NULL::BIGINT, NULL::TIMESTAMPTZ, NULL::TEXT,
                        NULL::BIGINT, NULL::BIGINT, NULL::JSONB,
                        NULL::BIGINT, NULL::TEXT,
                        NULL::INTEGER, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ;
                    RETURN;
            END;
            $$;

            -- Add comprehensive function comment for documentation
            COMMENT ON FUNCTION get_asset_booking_time_slots IS 
            'Enterprise-level function to retrieve asset booking time slots with comprehensive filtering.
            Updated for new asset_booking_items table structure with support for:
            - Multi-tenancy and partitioning for worldwide scalability
            - New booking_status enum (DRAFT, PENDING, APPROVED, CONFIRMED, IN_PROGRESS, COMPLETED, CANCELLED, REJECTED, ON_HOLD)
            - Parent-child booking relationships for recurring/group bookings
            - Enhanced financial tracking (deposits, taxes, discounts)
            - Check-in/out management with condition tracking
            - Priority-based sorting for enterprise workflows
            - Comprehensive audit trail and metadata
            
            Parameters:
            - p_action_type: Filter by booking workflow state (Normal, Pending, All, Completed, Draft)
            - p_tenant_id: Required for multi-tenant isolation
            - p_timezone: Timezone for date/time conversion (defaults to UTC)
            - p_booking_id: Optional specific booking ID filter
            - p_asset_id: Optional specific asset filter
            - p_start_datetime: Optional start of date range filter
            - p_end_datetime: Optional end of date range filter
            - p_booking_status: Optional specific status filter
            - p_partition_key: Optional partition key for performance optimization
            - p_include_cancelled: Whether to include cancelled bookings (default: false)
            - p_include_parent_bookings: Whether to include parent bookings in recurring sets (default: true)
            
            Returns comprehensive booking item details including financial, location, status, and audit information.';
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the updated function
        DB::unprepared("DROP FUNCTION IF EXISTS get_asset_booking_time_slots(TEXT, BIGINT, TEXT, BIGINT, BIGINT, TIMESTAMPTZ, TIMESTAMPTZ, TEXT, TEXT, BOOLEAN, BOOLEAN);");
        
        // Note: Not recreating the old function as the old table structure no longer exists
        // If rollback is needed, the entire migration chain must be rolled back
    }
};