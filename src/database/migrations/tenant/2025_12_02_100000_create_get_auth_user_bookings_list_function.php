<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates function to get authenticated user's booking list (summary view)
     * Optimized for worldwide enterprise application with new table structure
     */
    public function up(): void
    {
        // Drop existing function if exists
        DB::unprepared("DROP FUNCTION IF EXISTS get_auth_user_bookings_list(BIGINT, TEXT, BIGINT, BIGINT, TIMESTAMPTZ, TIMESTAMPTZ, INTEGER, INTEGER);");
        
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION get_auth_user_bookings_list(
                p_tenant_id BIGINT,
                p_action_type TEXT DEFAULT 'my_bookings',
                p_user_id BIGINT DEFAULT NULL,
                p_customer_id BIGINT DEFAULT NULL,
                p_start_date TIMESTAMPTZ DEFAULT NULL,
                p_end_date TIMESTAMPTZ DEFAULT NULL,
                p_page INTEGER DEFAULT 1,
                p_limit INTEGER DEFAULT 20
            )
            RETURNS TABLE (
                -- Response metadata
                status TEXT,
                message TEXT,
                total_count BIGINT,
                page INTEGER,
                page_size INTEGER,
                
                -- Booking summary data
                booking_id BIGINT,
                booking_number VARCHAR,
                booking_reference VARCHAR,
                booking_status VARCHAR,
                booking_type_id BIGINT,
                is_self_booking BOOLEAN,
                is_optiomesh_booking BOOLEAN,
                
                -- Booking party information
                booked_by_user_id BIGINT,
                booked_by_user_name TEXT,
                booked_by_user_profile_image TEXT,
                booked_by_customer_id BIGINT,
                booked_by_customer_name TEXT,
                booked_by_customer_thumbnail_image JSONB,
                
                -- Booking counts and summary
                total_items INTEGER,
                total_time_slots INTEGER,
                approved_items INTEGER,
                pending_items INTEGER,
                rejected_items INTEGER,
                cancelled_items INTEGER,
                
                -- Financial summary
                total_cost NUMERIC,
                total_deposit_amount NUMERIC,
                deposit_paid_count INTEGER,
                
                -- Dates
                earliest_start_datetime TIMESTAMPTZ,
                latest_end_datetime TIMESTAMPTZ,
                
                -- Metadata
                created_at TIMESTAMPTZ,
                updated_at TIMESTAMPTZ,
                created_by BIGINT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_offset INTEGER;
                v_total_count BIGINT;
            BEGIN
                -- Validate tenant_id
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'error'::TEXT,
                        'Valid tenant_id is required'::TEXT,
                        0::BIGINT, 0::INTEGER, 0::INTEGER,
                        NULL::BIGINT, NULL::VARCHAR, NULL::VARCHAR, NULL::VARCHAR, NULL::BIGINT, 
                        NULL::BOOLEAN, NULL::BOOLEAN, NULL::BIGINT, NULL::TEXT, NULL::TEXT,
                        NULL::BIGINT, NULL::TEXT, NULL::JSONB, 0::INTEGER, 0::INTEGER, 0::INTEGER,
                        0::INTEGER, 0::INTEGER, 0::INTEGER, NULL::NUMERIC, NULL::NUMERIC, 0::INTEGER,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::BIGINT;
                    RETURN;
                END IF;

                -- Calculate offset for pagination
                v_offset := (p_page - 1) * p_limit;

                -- Get total count first
                SELECT COUNT(DISTINCT ab.id) INTO v_total_count
                FROM asset_bookings ab
                WHERE ab.tenant_id = p_tenant_id
                    AND ab.deleted_at IS NULL
                    AND ab.isactive = TRUE
                    AND (
                        (p_action_type = 'my_bookings' AND (ab.booked_by_user_id = p_user_id OR ab.created_by = p_user_id))
                        OR (p_action_type = 'customer_bookings' AND ab.booked_by_customer_id = p_customer_id)
                        OR (p_action_type = 'all' AND p_user_id IS NOT NULL)
                        OR (p_action_type = 'internal' AND ab.booking_type_id = 1)
                        OR (p_action_type = 'external' AND ab.booking_type_id = 2)
                    )
                    AND (p_start_date IS NULL OR ab.created_at >= p_start_date)
                    AND (p_end_date IS NULL OR ab.created_at <= p_end_date);

                -- Return booking list with aggregated data
                RETURN QUERY
                SELECT
                    'success'::TEXT AS status,
                    'Bookings retrieved successfully'::TEXT AS message,
                    v_total_count AS total_count,
                    p_page AS page,
                    p_limit AS page_size,
                    
                    -- Booking data
                    ab.id AS booking_id,
                    ab.booking_number,
                    ab.booking_reference,
                    ab.booking_status::VARCHAR,
                    ab.booking_type_id,
                    ab.is_self_booking,
                    ab.is_optiomesh_booking,
                    
                    -- Booking party
                    ab.booked_by_user_id,
                    u.user_name::TEXT AS booked_by_user_name,
                    u.profile_image::TEXT AS booked_by_user_profile_image,
                    ab.booked_by_customer_id,
                    (ab.optiomesh_customer_details->>'name')::TEXT AS booked_by_customer_name,
                    (ab.optiomesh_customer_details->'thumbnail_image')::JSONB AS booked_by_customer_thumbnail_image,
                    
                    -- Item counts
                    COALESCE(bi_stats.total_items, 0)::INTEGER AS total_items,
                    COALESCE(ts_stats.total_time_slots, 0)::INTEGER AS total_time_slots,
                    COALESCE(bi_stats.approved_items, 0)::INTEGER AS approved_items,
                    COALESCE(bi_stats.pending_items, 0)::INTEGER AS pending_items,
                    COALESCE(bi_stats.rejected_items, 0)::INTEGER AS rejected_items,
                    COALESCE(bi_stats.cancelled_items, 0)::INTEGER AS cancelled_items,
                    
                    -- Financial
                    COALESCE(bi_stats.total_cost, 0)::NUMERIC AS total_cost,
                    COALESCE(bi_stats.total_deposit, 0)::NUMERIC AS total_deposit_amount,
                    COALESCE(bi_stats.deposit_paid_count, 0)::INTEGER AS deposit_paid_count,
                    
                    -- Date range
                    bi_stats.earliest_start AS earliest_start_datetime,
                    bi_stats.latest_end AS latest_end_datetime,
                    
                    -- Metadata
                    ab.created_at,
                    ab.updated_at,
                    ab.created_by
                    
                FROM asset_bookings ab
                LEFT JOIN users u ON ab.booked_by_user_id = u.id
                LEFT JOIN (
                    -- Aggregate booking items statistics
                    SELECT 
                        bi.asset_booking_id,
                        COUNT(*)::INTEGER AS total_items,
                        COUNT(*) FILTER (WHERE bi.booking_status = 'APPROVED')::INTEGER AS approved_items,
                        COUNT(*) FILTER (WHERE bi.booking_status = 'PENDING')::INTEGER AS pending_items,
                        COUNT(*) FILTER (WHERE bi.booking_status = 'REJECTED')::INTEGER AS rejected_items,
                        COUNT(*) FILTER (WHERE bi.booking_status = 'CANCELLED')::INTEGER AS cancelled_items,
                        SUM(bi.total_cost) AS total_cost,
                        SUM(bi.deposit_amount) AS total_deposit,
                        COUNT(*) FILTER (WHERE bi.deposit_paid = TRUE)::INTEGER AS deposit_paid_count,
                        MIN(bi.start_datetime) AS earliest_start,
                        MAX(bi.end_datetime) AS latest_end
                    FROM asset_booking_items bi
                    WHERE bi.deleted_at IS NULL AND bi.isactive = TRUE
                    GROUP BY bi.asset_booking_id
                ) bi_stats ON bi_stats.asset_booking_id = ab.id
                LEFT JOIN (
                    -- Aggregate time slots statistics
                    SELECT 
                        bi.asset_booking_id,
                        COUNT(ts.id)::INTEGER AS total_time_slots
                    FROM asset_booking_items bi
                    INNER JOIN asset_booking_time_slots ts ON ts.asset_booking_item_id = bi.id
                    WHERE bi.deleted_at IS NULL 
                        AND bi.isactive = TRUE
                        AND ts.deleted_at IS NULL
                        AND ts.isactive = TRUE
                    GROUP BY bi.asset_booking_id
                ) ts_stats ON ts_stats.asset_booking_id = ab.id
                
                WHERE ab.tenant_id = p_tenant_id
                    AND ab.deleted_at IS NULL
                    AND ab.isactive = TRUE
                    AND (
                        (p_action_type = 'my_bookings' AND (ab.booked_by_user_id = p_user_id OR ab.created_by = p_user_id))
                        OR (p_action_type = 'customer_bookings' AND ab.booked_by_customer_id = p_customer_id)
                        OR (p_action_type = 'all' AND p_user_id IS NOT NULL)
                        OR (p_action_type = 'internal' AND ab.booking_type_id = 1)
                        OR (p_action_type = 'external' AND ab.booking_type_id = 2)
                    )
                    AND (p_start_date IS NULL OR ab.created_at >= p_start_date)
                    AND (p_end_date IS NULL OR ab.created_at <= p_end_date)
                    
                ORDER BY ab.created_at DESC
                LIMIT p_limit
                OFFSET v_offset;
            END;
            $$;

            -- Add function comment
            COMMENT ON FUNCTION get_auth_user_bookings_list IS 
            'Returns paginated list of user bookings with summary statistics.
            
            Enterprise-optimized for worldwide multi-tenant application.
            Uses new table structure: asset_bookings → asset_booking_items → asset_booking_time_slots
            
            Parameters:
            - p_tenant_id: Required tenant ID for multi-tenancy
            - p_action_type: Filter type (my_bookings, customer_bookings, all, internal, external)
            - p_user_id: User ID for filtering
            - p_customer_id: Customer ID for external bookings
            - p_start_date: Filter bookings created after this date
            - p_end_date: Filter bookings created before this date
            - p_page: Page number for pagination (default: 1)
            - p_limit: Results per page (default: 20)
            
            Returns:
            - Status and message
            - Pagination metadata
            - Booking summary with aggregated item and time slot counts
            - Financial totals
            - Date ranges';
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_auth_user_bookings_list(BIGINT, TEXT, BIGINT, BIGINT, TIMESTAMPTZ, TIMESTAMPTZ, INTEGER, INTEGER);");
    }
};
