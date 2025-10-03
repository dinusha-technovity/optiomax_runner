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
        // DB::unprepared(<<<SQL
        //     DO $$
        //     DECLARE
        //         r RECORD;
        //     BEGIN
        //         FOR r IN
        //             SELECT oid::regprocedure::text AS func_signature
        //             FROM pg_proc
        //             WHERE proname = 'get_auth_user_asset_bookings_details'
        //         LOOP
        //             EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
        //         END LOOP;
        //     END$$;

        //     CREATE OR REPLACE FUNCTION get_auth_user_asset_bookings_details(
        //         p_tenant_id BIGINT,
        //         p_asset_item_id BIGINT DEFAULT NULL,
        //         p_user_id BIGINT DEFAULT NULL,
        //         p_action_type TEXT DEFAULT 'authbooking'
        //     )
        //     RETURNS TABLE (
        //         status TEXT,
        //         message TEXT,
        //         id BIGINT,
        //         asset_booking_purpose_or_use_case_type_id BIGINT,
        //         custom_purpose_name TEXT,
        //         custom_purpose_description TEXT,
        //         booking_description TEXT,
        //         asset_id BIGINT,
        //         asset_name VARCHAR,
        //         model_number VARCHAR,
        //         serial_number VARCHAR,
        //         thumbnail_image JSONB,
        //         booking_register_number VARCHAR,
        //         booking_status VARCHAR,
        //         start_datetime TIMESTAMPTZ,
        //         end_datetime TIMESTAMPTZ,
        //         booked_by_user_id BIGINT,
        //         booked_by_user_name TEXT,
        //         booked_by_user_profile_image TEXT,      -- <-- NEW
        //         booked_by_customer_id BIGINT,
        //         booked_by_customer_name TEXT,
        //         booked_by_customer_thumbnail_image JSONB, -- <-- NEW
        //         booking_created_by_user_id BIGINT,
        //         duration_hours NUMERIC,
        //         total_cost NUMERIC,
        //         total_time_slots BIGINT,
        //         approved_time_slots BIGINT,
        //         pending_time_slots BIGINT
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     BEGIN
        //         -- tenant check
        //         IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
        //             RETURN QUERY SELECT 
        //                 'FAILURE','Invalid tenant ID provided',
        //                 NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL;
        //             RETURN;
        //         END IF;

        //         -- asset item check
        //         IF p_asset_item_id IS NOT NULL AND p_asset_item_id < 0 THEN
        //             RETURN QUERY SELECT 
        //                 'FAILURE','Invalid asset item ID provided',
        //                 NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL;
        //             RETURN;
        //         END IF;

        //         -- user check
        //         IF p_user_id IS NOT NULL AND p_user_id < 0 THEN
        //             RETURN QUERY SELECT 
        //                 'FAILURE','Invalid user ID provided',
        //                 NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL;
        //             RETURN;
        //         END IF;

        //         IF p_action_type = 'authbooking' THEN
        //             RETURN QUERY
        //                 SELECT
        //                     'SUCCESS'::text,
        //                     'Asset booking details retrieved successfully'::text,
        //                     ab.id::bigint,
        //                     ab.asset_booking_purpose_or_use_case_type_id::bigint,
        //                     ab.custom_purpose_name::text,
        //                     ab.custom_purpose_description::text,
        //                     ab.description::text AS booking_description,
        //                     ai.id::bigint AS asset_id,
        //                     a.name::varchar AS asset_name,
        //                     ai.model_number::varchar,
        //                     ai.serial_number::varchar,
        //                     ai.thumbnail_image::jsonb,
        //                     ab.booking_register_number::varchar,
        //                     ab.booking_status::varchar,
        //                     ab.start_datetime::timestamptz,
        //                     ab.end_datetime::timestamptz,
        //                     ab.booked_by_user_id::bigint,
        //                     u.name::text AS booked_by_user_name,
        //                     u.profile_image::text AS booked_by_user_profile_image,        -- <-- NEW
        //                     ab.booked_by_customer_id::bigint,
        //                     c.name::text AS booked_by_customer_name,
        //                     c.thumbnail_image::jsonb AS booked_by_customer_thumbnail_image, -- <-- NEW
        //                     ab.booking_created_by_user_id::bigint,
        //                     ab.duration_hours::numeric,
        //                     ab.total_cost::numeric,
        //                     COALESCE(t.total_time_slots, 0)::bigint AS total_time_slots,
        //                     COALESCE(t.approved_time_slots, 0)::bigint AS approved_time_slots,
        //                     COALESCE(t.pending_time_slots, 0)::bigint AS pending_time_slots
        //                 FROM asset_bookings ab
        //                 INNER JOIN asset_items ai ON ab.asset_id = ai.id
        //                 INNER JOIN assets a ON ai.asset_id = a.id
        //                 LEFT JOIN users u ON ab.booked_by_user_id = u.id
        //                 LEFT JOIN customers c ON ab.booked_by_customer_id = c.id
        //                 LEFT JOIN (
        //                     SELECT
        //                         asset_booking_time_slots.booking_id,
        //                         COUNT(*) AS total_time_slots,
        //                         COUNT(*) FILTER (WHERE asset_booking_time_slots.approval_status = 'APPROVED') AS approved_time_slots,
        //                         COUNT(*) FILTER (WHERE asset_booking_time_slots.approval_status = 'PENDING') AS pending_time_slots
        //                     FROM asset_booking_time_slots
        //                     WHERE asset_booking_time_slots.deleted_at IS NULL AND asset_booking_time_slots.isactive = TRUE
        //                     GROUP BY asset_booking_time_slots.booking_id
        //                 ) t ON t.booking_id = ab.id
        //                 WHERE ab.tenant_id = p_tenant_id
        //                 AND ab.deleted_at IS NULL
        //                 AND ab.isactive = TRUE
        //                 AND ai.deleted_at IS NULL
        //                 AND ai.isactive = TRUE
        //                 AND a.deleted_at IS NULL
        //                 AND a.isactive = TRUE
        //                 AND (ab.asset_id = p_asset_item_id OR p_asset_item_id IS NULL OR p_asset_item_id <= 0)
        //                 AND (
        //                     ab.booked_by_user_id = p_user_id
        //                     OR ab.booking_created_by_user_id = p_user_id
        //                 );
        //         ELSIF p_action_type = 'external' THEN
        //             RETURN QUERY
        //                 SELECT
        //                     'SUCCESS'::text,
        //                     'External asset bookings retrieved successfully'::text,
        //                     ab.id::bigint,
        //                     ab.asset_booking_purpose_or_use_case_type_id::bigint,
        //                     ab.custom_purpose_name::text,
        //                     ab.custom_purpose_description::text,
        //                     ab.description::text AS booking_description,
        //                     ai.id::bigint AS asset_id,
        //                     a.name::varchar AS asset_name,
        //                     ai.model_number::varchar,
        //                     ai.serial_number::varchar,
        //                     ai.thumbnail_image::jsonb,
        //                     ab.booking_register_number::varchar,
        //                     ab.booking_status::varchar,
        //                     ab.start_datetime::timestamptz,
        //                     ab.end_datetime::timestamptz,
        //                     ab.booked_by_user_id::bigint,
        //                     u.name::text AS booked_by_user_name,
        //                     u.profile_image::text AS booked_by_user_profile_image,        -- <-- NEW
        //                     ab.booked_by_customer_id::bigint,
        //                     c.name::text AS booked_by_customer_name,
        //                     c.thumbnail_image::jsonb AS booked_by_customer_thumbnail_image, -- <-- NEW
        //                     ab.booking_created_by_user_id::bigint,
        //                     ab.duration_hours::numeric,
        //                     ab.total_cost::numeric,
        //                     COALESCE(t.total_time_slots, 0)::bigint AS total_time_slots,
        //                     COALESCE(t.approved_time_slots, 0)::bigint AS approved_time_slots,
        //                     COALESCE(t.pending_time_slots, 0)::bigint AS pending_time_slots
        //                 FROM asset_bookings ab
        //                 INNER JOIN asset_items ai ON ab.asset_id = ai.id
        //                 INNER JOIN assets a ON ai.asset_id = a.id
        //                 LEFT JOIN users u ON ab.booked_by_user_id = u.id
        //                 LEFT JOIN customers c ON ab.booked_by_customer_id = c.id
        //                 LEFT JOIN (
        //                     SELECT
        //                         asset_booking_time_slots.booking_id,
        //                         COUNT(*) AS total_time_slots,
        //                         COUNT(*) FILTER (WHERE asset_booking_time_slots.approval_status = 'APPROVED') AS approved_time_slots,
        //                         COUNT(*) FILTER (WHERE asset_booking_time_slots.approval_status = 'PENDING') AS pending_time_slots
        //                     FROM asset_booking_time_slots
        //                     WHERE asset_booking_time_slots.deleted_at IS NULL AND asset_booking_time_slots.isactive = TRUE
        //                     GROUP BY asset_booking_time_slots.booking_id
        //                 ) t ON t.booking_id = ab.id
        //                 WHERE ab.tenant_id = p_tenant_id
        //                 AND ab.deleted_at IS NULL
        //                 AND ab.isactive = TRUE
        //                 AND ai.deleted_at IS NULL
        //                 AND ai.isactive = TRUE
        //                 AND a.deleted_at IS NULL
        //                 AND a.isactive = TRUE
        //                 AND (ab.asset_id = p_asset_item_id OR p_asset_item_id IS NULL OR p_asset_item_id <= 0)
        //                 AND (
        //                     ab.booked_by_user_id != p_user_id
        //                     AND ab.booking_created_by_user_id = p_user_id
        //                 );
        //         ELSE
        //             RETURN QUERY SELECT 
        //                 'FAILURE','Invalid action type provided',
        //                 NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL;
        //         END IF;
        //     END;
        //     $$;
        // SQL);
        DB::unprepared(<<<SQL
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

            CREATE OR REPLACE FUNCTION get_auth_user_asset_bookings_details(
                p_tenant_id BIGINT,
                p_asset_item_id BIGINT DEFAULT NULL,
                p_user_id BIGINT DEFAULT NULL,
                p_action_type TEXT DEFAULT 'authbooking'
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                asset_booking_purpose_or_use_case_type_id BIGINT,
                custom_purpose_name TEXT,
                custom_purpose_description TEXT,
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
                booked_by_user_id BIGINT,
                booked_by_user_name TEXT,
                booked_by_user_profile_image TEXT,
                booked_by_customer_id BIGINT,
                booked_by_customer_name TEXT,
                booked_by_customer_thumbnail_image JSONB,
                booking_created_by_user_id BIGINT,
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
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL;
                    RETURN;
                END IF;

                -- asset item check
                IF p_asset_item_id IS NOT NULL AND p_asset_item_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE','Invalid asset item ID provided',
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL;
                    RETURN;
                END IF;

                -- user check
                IF p_user_id IS NOT NULL AND p_user_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE','Invalid user ID provided',
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL;
                    RETURN;
                END IF;

                IF p_action_type = 'authbooking' THEN
                    RETURN QUERY
                        SELECT
                            'SUCCESS'::text,
                            'Asset booking details retrieved successfully'::text,
                            ab.id::bigint,
                            ab.asset_booking_purpose_or_use_case_type_id::bigint,
                            ab.custom_purpose_name::text,
                            ab.custom_purpose_description::text,
                            ab.description::text AS booking_description,
                            ai.id::bigint AS asset_id,
                            a.name::varchar AS asset_name,
                            ai.model_number::varchar,
                            ai.serial_number::varchar,
                            ai.thumbnail_image::jsonb,
                            ab.booking_register_number::varchar,
                            ab.booking_status::varchar,
                            ab.start_datetime::timestamptz,
                            ab.end_datetime::timestamptz,
                            ab.booked_by_user_id::bigint,
                            u.name::text AS booked_by_user_name,
                            u.profile_image::text AS booked_by_user_profile_image,
                            ab.booked_by_customer_id::bigint,
                            c.name::text AS booked_by_customer_name,
                            c.thumbnail_image::jsonb AS booked_by_customer_thumbnail_image,
                            ab.booking_created_by_user_id::bigint,
                            ab.duration_hours::numeric,
                            ab.total_cost::numeric,
                            COALESCE(t.total_time_slots, 0)::bigint AS total_time_slots,
                            COALESCE(t.approved_time_slots, 0)::bigint AS approved_time_slots,
                            COALESCE(t.pending_time_slots, 0)::bigint AS pending_time_slots
                        FROM asset_bookings ab
                        INNER JOIN asset_items ai ON ab.asset_id = ai.id
                        INNER JOIN assets a ON ai.asset_id = a.id
                        LEFT JOIN users u ON ab.booked_by_user_id = u.id
                        LEFT JOIN customers c ON ab.booked_by_customer_id = c.id
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
                        AND (
                            ab.booked_by_user_id = p_user_id
                            AND ab.booking_created_by_user_id = p_user_id
                        );
                ELSIF p_action_type = 'external' THEN
                    RETURN QUERY
                        SELECT
                            'SUCCESS'::text,
                            'External asset bookings retrieved successfully'::text,
                            ab.id::bigint,
                            ab.asset_booking_purpose_or_use_case_type_id::bigint,
                            ab.custom_purpose_name::text,
                            ab.custom_purpose_description::text,
                            ab.description::text AS booking_description,
                            ai.id::bigint AS asset_id,
                            a.name::varchar AS asset_name,
                            ai.model_number::varchar,
                            ai.serial_number::varchar,
                            ai.thumbnail_image::jsonb,
                            ab.booking_register_number::varchar,
                            ab.booking_status::varchar,
                            ab.start_datetime::timestamptz,
                            ab.end_datetime::timestamptz,
                            ab.booked_by_user_id::bigint,
                            u.name::text AS booked_by_user_name,
                            u.profile_image::text AS booked_by_user_profile_image,
                            ab.booked_by_customer_id::bigint,
                            c.name::text AS booked_by_customer_name,
                            c.thumbnail_image::jsonb AS booked_by_customer_thumbnail_image,
                            ab.booking_created_by_user_id::bigint,
                            ab.duration_hours::numeric,
                            ab.total_cost::numeric,
                            COALESCE(t.total_time_slots, 0)::bigint AS total_time_slots,
                            COALESCE(t.approved_time_slots, 0)::bigint AS approved_time_slots,
                            COALESCE(t.pending_time_slots, 0)::bigint AS pending_time_slots
                        FROM asset_bookings ab
                        INNER JOIN asset_items ai ON ab.asset_id = ai.id
                        INNER JOIN assets a ON ai.asset_id = a.id
                        LEFT JOIN users u ON ab.booked_by_user_id = u.id
                        LEFT JOIN customers c ON ab.booked_by_customer_id = c.id
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
                        AND (
                            ab.booking_created_by_user_id = p_user_id
                            AND (
                                ab.booked_by_user_id IS NULL
                                OR ab.booked_by_user_id != p_user_id
                            )
                        );
                ELSE
                    RETURN QUERY SELECT 
                        'FAILURE','Invalid action type provided',
                        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL;
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