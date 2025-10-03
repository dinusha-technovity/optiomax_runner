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
            CREATE OR REPLACE FUNCTION get_auth_user_asset_bookings_details(
                p_tenant_id BIGINT,
                p_asset_item_id BIGINT DEFAULT NULL,
                p_user_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                booking_purpose TEXT,
                booking_description TEXT,
                asset_id BIGINT,
                asset_name VARCHAR,
                model_number VARCHAR,
                serial_number VARCHAR,
                thumbnail_image JSONB,
                booking_register_number VARCHAR,
                booking_status VARCHAR,
                start_datetime TIMESTAMPTZ,
                end_datetime TIMESTAMPTZ,
                booked_by BIGINT,
                duration_hours NUMERIC,
                total_cost NUMERIC,
                total_time_slots BIGINT,
                approved_time_slots BIGINT,
                pending_time_slots BIGINT
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- tenant check
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE','Invalid tenant ID provided',
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL;
                    RETURN;
                END IF;

                -- asset item check
                IF p_asset_item_id IS NOT NULL AND p_asset_item_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE','Invalid asset item ID provided',
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL;
                    RETURN;
                END IF;

                -- user check
                IF p_user_id IS NOT NULL AND p_user_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE','Invalid user ID provided',
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL;
                    RETURN;
                END IF;

                RETURN QUERY
                    SELECT
                        'SUCCESS',
                        'Asset booking details retrieved successfully',
                        ab.id,
                        ab.purpose AS booking_purpose,
                        ab.description AS booking_description,
                        ai.id AS asset_id,
                        a.name AS asset_name,
                        ai.model_number,
                        ai.serial_number,
                        ai.thumbnail_image,
                        ab.booking_register_number,
                        ab.booking_status,
                        ab.start_datetime,
                        ab.end_datetime,
                        ab.booked_by,
                        ab.duration_hours,
                        ab.total_cost,
                        COALESCE(t.total_time_slots, 0) AS total_time_slots,
                        COALESCE(t.approved_time_slots, 0) AS approved_time_slots,
                        COALESCE(t.pending_time_slots, 0) AS pending_time_slots
                    FROM asset_bookings ab
                    INNER JOIN asset_items ai ON ab.asset_id = ai.id
                    INNER JOIN assets a ON ai.asset_id = a.id
                    LEFT JOIN (
                        SELECT
                            asset_booking_time_slots.booking_id,
                            COUNT(*) AS total_time_slots,
                            COUNT(*) FILTER (WHERE asset_booking_time_slots.approval_status = 'APPROVED') AS approved_time_slots,
                            COUNT(*) FILTER (WHERE asset_booking_time_slots.approval_status = 'PENDING') AS pending_time_slots
                        FROM asset_booking_time_slots
                        WHERE asset_booking_time_slots.deleted_at IS NULL AND asset_booking_time_slots.isactive = TRUE
                        GROUP BY asset_booking_time_slots.booking_id
                    ) t ON t.booking_id = ab.id
                    WHERE ab.tenant_id = p_tenant_id
                    AND ab.deleted_at IS NULL
                    AND ab.isactive = TRUE
                    AND ai.deleted_at IS NULL
                    AND ai.isactive = TRUE
                    AND a.deleted_at IS NULL
                    AND a.isactive = TRUE
                    AND (ab.asset_id = p_asset_item_id OR p_asset_item_id IS NULL OR p_asset_item_id <= 0)
                    AND (ab.booked_by = p_user_id OR p_user_id IS NULL OR p_user_id <= 0);
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_auth_user_asset_bookings_details(
            BIGINT,
            BIGINT,
            BIGINT
        );");
    }
};
