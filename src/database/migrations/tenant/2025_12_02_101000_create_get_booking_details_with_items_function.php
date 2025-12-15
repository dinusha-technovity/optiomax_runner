<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates function to get detailed booking information with all items and assets
     * Enterprise-level worldwide application support
     */
    public function up(): void
    {
        // Drop existing function if exists
        DB::unprepared("DROP FUNCTION IF EXISTS get_booking_details_with_items(BIGINT, BIGINT, TEXT);");
        
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION get_booking_details_with_items(
                p_tenant_id BIGINT,
                p_booking_id BIGINT,
                p_timezone TEXT DEFAULT 'UTC'
            )
            RETURNS TABLE (
                -- Response status
                status TEXT,
                message TEXT,
                
                -- Booking header information
                booking_id BIGINT,
                booking_number VARCHAR,
                booking_reference VARCHAR,
                booking_status VARCHAR,
                booking_type_id BIGINT,
                is_self_booking BOOLEAN,
                is_optiomesh_booking BOOLEAN,
                parent_booking_id BIGINT,
                
                -- Booking party details
                booked_by_user_id BIGINT,
                booked_by_user_name TEXT,
                booked_by_user_email TEXT,
                booked_by_user_profile_image TEXT,
                booked_by_customer_id BIGINT,
                booked_by_customer_name TEXT,
                booked_by_customer_email TEXT,
                booked_by_customer_phone TEXT,
                booked_by_customer_thumbnail_image JSONB,
                optiomesh_customer_details JSONB,
                
                -- Booking metadata
                attendees_count INTEGER,
                note TEXT,
                special_requirements TEXT,
                
                -- Dates and times (UTC for frontend conversion)
                created_at TIMESTAMPTZ,
                updated_at TIMESTAMPTZ,
                created_by BIGINT,
                updated_by BIGINT,
                
                -- Booking items array (JSONB for nested structure)
                booking_items JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_booking_items JSONB;
            BEGIN
                -- Validate tenant_id
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'error'::TEXT,
                        'Valid tenant_id is required'::TEXT,
                        NULL::BIGINT, NULL::VARCHAR, NULL::VARCHAR, NULL::VARCHAR, NULL::BIGINT,
                        NULL::BOOLEAN, NULL::BOOLEAN, NULL::BIGINT, NULL::BIGINT, NULL::TEXT, 
                        NULL::TEXT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT,
                        NULL::JSONB, NULL::JSONB, NULL::INTEGER, NULL::TEXT, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::BIGINT, NULL::BIGINT, NULL::JSONB;
                    RETURN;
                END IF;

                -- Validate booking_id
                IF p_booking_id IS NULL OR p_booking_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'error'::TEXT,
                        'Valid booking_id is required'::TEXT,
                        NULL::BIGINT, NULL::VARCHAR, NULL::VARCHAR, NULL::VARCHAR, NULL::BIGINT,
                        NULL::BOOLEAN, NULL::BOOLEAN, NULL::BIGINT, NULL::BIGINT, NULL::TEXT,
                        NULL::TEXT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT,
                        NULL::JSONB, NULL::JSONB, NULL::INTEGER, NULL::TEXT, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::BIGINT, NULL::BIGINT, NULL::JSONB;
                    RETURN;
                END IF;

                -- Build booking items with all details
                SELECT jsonb_agg(
                    jsonb_build_object(
                        'booking_item_id', bi.id,
                        'asset_id', ai.id,
                        'asset_name', a.name,
                        'asset_code', ai.asset_tag,
                        'asset_category', ac.name,
                        'asset_sub_category', ascat.name,
                        'asset_thumbnail', ai.thumbnail_image,
                        'asset_description', a.description,
                        'organization_id', bi.organization_id,
                        'organization_name', org.name,
                        
                        -- Timing
                        'start_datetime', bi.start_datetime,
                        'end_datetime', bi.end_datetime,
                        'duration_hours', bi.duration_hours,
                        'timezone', bi.timezone,
                        'is_recurring', bi.is_recurring,
                        'recurring_pattern', bi.recurring_pattern,
                        
                        -- Location
                        'location_latitude', bi.location_latitude,
                        'location_longitude', bi.location_longitude,
                        'location_description', bi.location_description,
                        'logistics_note', bi.logistics_note,
                        
                        -- Priority and status
                        'priority_level', bi.priority_level,
                        'booking_status', bi.booking_status,
                        
                        -- Financial details
                        'unit_rate', bi.unit_rate,
                        'subtotal', bi.subtotal,
                        'tax_amount', bi.tax_amount,
                        'discount_amount', bi.discount_amount,
                        'total_cost', bi.total_cost,
                        'rate_currency_id', bi.rate_currency_id,
                        'total_cost_currency_id', bi.total_cost_currency_id,
                        
                        -- Deposit information
                        'deposit_required', bi.deposit_required,
                        'deposit_amount', bi.deposit_amount,
                        'deposit_percentage', bi.deposit_percentage,
                        'deposit_paid', bi.deposit_paid,
                        'deposit_paid_at', bi.deposit_paid_at,
                        'deposit_payment_reference', bi.deposit_payment_reference,
                        
                        -- Approval workflow
                        'approval_type_id', bi.approval_type_id,
                        'approved_by', bi.approved_by,
                        'approved_at', bi.approved_at,
                        'approval_notes', bi.approval_notes,
                        'rejected_by', bi.rejected_by,
                        'rejected_at', bi.rejected_at,
                        'rejection_reason', bi.rejection_reason,
                        'workflow_request_queues_id', bi.workflow_request_queues_id,
                        
                        -- Cancellation
                        'cancellation_enabled', bi.cancellation_enabled,
                        'cancellation_notice_hours', bi.cancellation_notice_hours,
                        'cancellation_fee_enabled', bi.cancellation_fee_enabled,
                        'cancellation_fee_amount', bi.cancellation_fee_amount,
                        'cancellation_fee_percentage', bi.cancellation_fee_percentage,
                        'cancelled_at', bi.cancelled_at,
                        'cancelled_by', bi.cancelled_by,
                        'cancellation_reason', bi.cancellation_reason,
                        
                        -- Time slots count
                        'total_time_slots', (
                            SELECT COUNT(*)
                            FROM asset_booking_time_slots ts
                            WHERE ts.asset_booking_item_id = bi.id
                                AND ts.deleted_at IS NULL
                                AND ts.isactive = TRUE
                        ),
                        
                        -- Metadata
                        'created_at', bi.created_at,
                        'updated_at', bi.updated_at
                    )
                ) INTO v_booking_items
                FROM asset_booking_items bi
                INNER JOIN asset_items ai ON bi.asset_id = ai.id
                INNER JOIN assets a ON ai.asset_id = a.id
                LEFT JOIN asset_categories ac ON a.category = ac.id
                LEFT JOIN asset_sub_categories ascat ON a.sub_category = ascat.id
                LEFT JOIN organization org ON bi.organization_id = org.id
                WHERE bi.asset_booking_id = p_booking_id
                    AND bi.deleted_at IS NULL
                    AND bi.isactive = TRUE
                ORDER BY bi.start_datetime ASC;

                -- Return booking details with items
                RETURN QUERY
                SELECT
                    'success'::TEXT AS status,
                    'Booking details retrieved successfully'::TEXT AS message,
                    
                    -- Booking header
                    ab.id AS booking_id,
                    ab.booking_number,
                    ab.booking_reference,
                    ab.booking_status::VARCHAR,
                    ab.booking_type_id,
                    ab.is_self_booking,
                    ab.is_optiomesh_booking,
                    ab.parent_booking_id,
                    
                    -- Booking party
                    ab.booked_by_user_id,
                    u.user_name::TEXT AS booked_by_user_name,
                    u.email::TEXT AS booked_by_user_email,
                    u.profile_image::TEXT AS booked_by_user_profile_image,
                    ab.booked_by_customer_id,
                    (ab.optiomesh_customer_details->>'name')::TEXT AS booked_by_customer_name,
                    (ab.optiomesh_customer_details->>'email')::TEXT AS booked_by_customer_email,
                    (ab.optiomesh_customer_details->>'phone')::TEXT AS booked_by_customer_phone,
                    (ab.optiomesh_customer_details->'thumbnail_image')::JSONB AS booked_by_customer_thumbnail_image,
                    ab.optiomesh_customer_details,
                    
                    -- Booking metadata
                    ab.attendees_count::INTEGER,
                    ab.note,
                    ab.special_requirements,
                    
                    -- Dates (return UTC for frontend conversion)
                    ab.created_at,
                    ab.updated_at,
                    ab.created_by,
                    ab.updated_by,
                    
                    -- Booking items array
                    COALESCE(v_booking_items, '[]'::JSONB) AS booking_items
                    
                FROM asset_bookings ab
                LEFT JOIN users u ON ab.booked_by_user_id = u.id
                WHERE ab.id = p_booking_id
                    AND ab.tenant_id = p_tenant_id
                    AND ab.deleted_at IS NULL
                    AND ab.isactive = TRUE;
            END;
            $$;

            -- Add function comment
            COMMENT ON FUNCTION get_booking_details_with_items IS 
            'Returns complete booking details with all associated items and assets.
            
            Enterprise-optimized for worldwide multi-tenant application.
            Uses new table structure with nested JSONB for efficient data retrieval.
            
            Parameters:
            - p_tenant_id: Required tenant ID for multi-tenancy
            - p_booking_id: Specific booking ID to retrieve
            - p_timezone: Timezone parameter (for future use, returns UTC)
            
            Returns:
            - Complete booking header information
            - Detailed booked party information (user or customer)
            - Array of booking items with:
              * Full asset details
              * Timing and location
              * Financial breakdown
              * Approval workflow status
              * Time slots count
            
            Frontend should use parseUTCToLocal() for timestamp display.';
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_booking_details_with_items(BIGINT, BIGINT, TEXT);");
    }
};
