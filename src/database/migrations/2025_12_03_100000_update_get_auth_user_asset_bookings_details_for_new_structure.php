<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Updates get_auth_user_asset_bookings_details function to work with new booking structure:
     * asset_bookings -> asset_booking_items -> asset_booking_time_slots
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            -- Drop existing function with all overloads
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_auth_user_asset_bookings_details'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
 
            -- Create updated function for new table structure
            CREATE OR REPLACE FUNCTION get_auth_user_asset_bookings_details(
                p_tenant_id BIGINT,
                p_asset_item_id BIGINT DEFAULT NULL,
                p_user_id BIGINT DEFAULT NULL,
                p_action_type TEXT DEFAULT 'authbooking'
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                booking_id BIGINT,
                booking_number VARCHAR,
                booking_reference VARCHAR,
                booking_status VARCHAR,
                is_self_booking BOOLEAN,
                booking_type_id BIGINT,
                optiomesh_customer_id BIGINT,
                optiomesh_customer_details JSONB,
                booked_by_user_id BIGINT,
                booked_by_user_name TEXT,
                booked_by_user_profile_image TEXT,
                attendees_count INTEGER,
                note TEXT,
                special_requirements TEXT,
                booking_created_by BIGINT,
                booking_created_at TIMESTAMPTZ,
                booking_items JSONB
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Tenant validation
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Invalid tenant ID provided'::TEXT,
                        NULL::BIGINT,NULL::VARCHAR,NULL::VARCHAR,NULL::VARCHAR,NULL::BOOLEAN,NULL::BIGINT,
                        NULL::BIGINT,NULL::JSONB,NULL::BIGINT,NULL::TEXT,NULL::TEXT,NULL::INTEGER,
                        NULL::TEXT,NULL::TEXT,NULL::BIGINT,NULL::TIMESTAMPTZ,NULL::JSONB;
                    RETURN;
                END IF;

                -- Asset item validation
                IF p_asset_item_id IS NOT NULL AND p_asset_item_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Invalid asset item ID provided'::TEXT,
                        NULL::BIGINT,NULL::VARCHAR,NULL::VARCHAR,NULL::VARCHAR,NULL::BOOLEAN,NULL::BIGINT,
                        NULL::BIGINT,NULL::JSONB,NULL::BIGINT,NULL::TEXT,NULL::TEXT,NULL::INTEGER,
                        NULL::TEXT,NULL::TEXT,NULL::BIGINT,NULL::TIMESTAMPTZ,NULL::JSONB;
                    RETURN;
                END IF;

                -- User validation
                IF p_user_id IS NOT NULL AND p_user_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Invalid user ID provided'::TEXT,
                        NULL::BIGINT,NULL::VARCHAR,NULL::VARCHAR,NULL::VARCHAR,NULL::BOOLEAN,NULL::BIGINT,
                        NULL::BIGINT,NULL::JSONB,NULL::BIGINT,NULL::TEXT,NULL::TEXT,NULL::INTEGER,
                        NULL::TEXT,NULL::TEXT,NULL::BIGINT,NULL::TIMESTAMPTZ,NULL::JSONB;
                    RETURN;
                END IF;

                -- Updated query for new table structure: asset_bookings -> asset_booking_items
                IF p_action_type = 'authbooking' THEN
                    RETURN QUERY
                        SELECT
                            'SUCCESS'::text AS status,
                            'Asset booking details retrieved successfully'::text AS message,
                            ab.id::bigint AS booking_id,
                            ab.booking_number::varchar,
                            ab.booking_reference::varchar,
                            ab.booking_status::varchar,
                            ab.is_self_booking::boolean,
                            ab.booking_type_id::bigint,
                            ab.optiomesh_customer_id::bigint,
                            ab.optiomesh_customer_details::jsonb,
                            ab.booked_by_user_id::bigint,
                            u.name::text AS booked_by_user_name,
                            u.profile_image::text AS booked_by_user_profile_image,
                            ab.attendees_count::integer,
                            ab.note::text,
                            ab.special_requirements::text,
                            ab.created_by::bigint AS booking_created_by,
                            ab.created_at::timestamptz AS booking_created_at,
                            COALESCE(
                                jsonb_agg(
                                    jsonb_build_object(
                                        'booking_item_id', abi.id,
                                        'asset_id', abi.asset_id,
                                        'asset_name', a.name,
                                        'model_number', ai.model_number,
                                        'serial_number', ai.serial_number,
                                        'thumbnail_image', ai.thumbnail_image,
                                        'organization_id', abi.organization_id,
                                        'organization_data', org.data,
                                        'start_datetime', abi.start_datetime,
                                        'end_datetime', abi.end_datetime,
                                        'duration_hours', abi.duration_hours,
                                        'timezone', abi.timezone,
                                        'booking_status', abi.booking_status,
                                        'purpose_type_id', abi.purpose_type_id,
                                        'custom_purpose_name', abi.custom_purpose_name,
                                        'custom_purpose_description', abi.custom_purpose_description,
                                        'unit_rate', abi.unit_rate,
                                        'subtotal', abi.subtotal,
                                        'tax_amount', abi.tax_amount,
                                        'discount_amount', abi.discount_amount,
                                        'total_cost', abi.total_cost,
                                        'location_latitude', abi.location_latitude,
                                        'location_longitude', abi.location_longitude,
                                        'location_description', abi.location_description,
                                        'booking_created_by_user_id', abi.booking_created_by_user_id
                                    ) ORDER BY abi.start_datetime
                                ) FILTER (WHERE abi.id IS NOT NULL),
                                '[]'::jsonb
                            ) AS booking_items
                        FROM asset_bookings ab
                        LEFT JOIN asset_booking_items abi ON ab.id = abi.asset_booking_id AND abi.deleted_at IS NULL
                        LEFT JOIN asset_items ai ON abi.asset_id = ai.id AND ai.deleted_at IS NULL AND ai.isactive = TRUE
                        LEFT JOIN assets a ON ai.asset_id = a.id AND a.deleted_at IS NULL AND a.isactive = TRUE
                        LEFT JOIN organization org ON abi.organization_id = org.id AND org.deleted_at IS NULL AND org.isactive = TRUE
                        LEFT JOIN users u ON ab.booked_by_user_id = u.id
                        WHERE ab.tenant_id = p_tenant_id
                        AND ab.deleted_at IS NULL
                        AND ab.created_by = p_user_id
                        AND ab.booked_by_user_id = p_user_id
                        AND ab.booking_type_id = 1
                        AND ab.is_self_booking = TRUE
                        AND (p_asset_item_id IS NULL OR p_asset_item_id <= 0 OR abi.asset_id = p_asset_item_id)
                        GROUP BY ab.id, u.name, u.profile_image
                        ORDER BY ab.created_at DESC;
                        
                ELSIF p_action_type = 'external' THEN
                    RETURN QUERY
                        SELECT
                            'SUCCESS'::text AS status,
                            'External asset bookings retrieved successfully'::text AS message,
                            ab.id::bigint AS booking_id,
                            ab.booking_number::varchar,
                            ab.booking_reference::varchar,
                            ab.booking_status::varchar,
                            ab.is_self_booking::boolean,
                            ab.booking_type_id::bigint,
                            ab.optiomesh_customer_id::bigint,
                            ab.optiomesh_customer_details::jsonb,
                            ab.booked_by_user_id::bigint,
                            u.name::text AS booked_by_user_name,
                            u.profile_image::text AS booked_by_user_profile_image,
                            ab.attendees_count::integer,
                            ab.note::text,
                            ab.special_requirements::text,
                            ab.created_by::bigint AS booking_created_by,
                            ab.created_at::timestamptz AS booking_created_at,
                            COALESCE(
                                jsonb_agg(
                                    jsonb_build_object(
                                        'booking_item_id', abi.id,
                                        'asset_id', abi.asset_id,
                                        'asset_name', a.name,
                                        'model_number', ai.model_number,
                                        'serial_number', ai.serial_number,
                                        'thumbnail_image', ai.thumbnail_image,
                                        'organization_id', abi.organization_id,
                                        'start_datetime', abi.start_datetime,
                                        'end_datetime', abi.end_datetime,
                                        'duration_hours', abi.duration_hours,
                                        'timezone', abi.timezone,
                                        'booking_status', abi.booking_status,
                                        'purpose_type_id', abi.purpose_type_id,
                                        'custom_purpose_name', abi.custom_purpose_name,
                                        'custom_purpose_description', abi.custom_purpose_description,
                                        'unit_rate', abi.unit_rate,
                                        'subtotal', abi.subtotal,
                                        'tax_amount', abi.tax_amount,
                                        'discount_amount', abi.discount_amount,
                                        'total_cost', abi.total_cost,
                                        'location_latitude', abi.location_latitude,
                                        'location_longitude', abi.location_longitude,
                                        'location_description', abi.location_description,
                                        'booking_created_by_user_id', abi.booking_created_by_user_id
                                    ) ORDER BY abi.start_datetime
                                ) FILTER (WHERE abi.id IS NOT NULL),
                                '[]'::jsonb
                            ) AS booking_items
                        FROM asset_bookings ab
                        LEFT JOIN asset_booking_items abi ON ab.id = abi.asset_booking_id AND abi.deleted_at IS NULL
                        LEFT JOIN asset_items ai ON abi.asset_id = ai.id AND ai.deleted_at IS NULL AND ai.isactive = TRUE
                        LEFT JOIN assets a ON ai.asset_id = a.id AND a.deleted_at IS NULL AND a.isactive = TRUE
                        LEFT JOIN users u ON ab.booked_by_user_id = u.id
                        WHERE ab.tenant_id = p_tenant_id
                        AND ab.deleted_at IS NULL
                        AND ab.created_by = p_user_id
                        AND ab.booking_type_id = 2
                        AND (p_asset_item_id IS NULL OR p_asset_item_id <= 0 OR abi.asset_id = p_asset_item_id)
                        GROUP BY ab.id, u.name, u.profile_image
                        ORDER BY ab.created_at DESC;
                        
                ELSE
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Invalid action type provided. Use authbooking or external'::TEXT,
                        NULL::BIGINT,NULL::VARCHAR,NULL::VARCHAR,NULL::VARCHAR,NULL::BOOLEAN,NULL::BIGINT,
                        NULL::BIGINT,NULL::JSONB,NULL::BIGINT,NULL::TEXT,NULL::TEXT,NULL::INTEGER,
                        NULL::TEXT,NULL::TEXT,NULL::BIGINT,NULL::TIMESTAMPTZ,NULL::JSONB;
                END IF;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_auth_user_asset_bookings_details(BIGINT, BIGINT, BIGINT, TEXT);");
    }
};
