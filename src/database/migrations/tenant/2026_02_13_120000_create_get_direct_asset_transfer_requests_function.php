<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- Drop existing function if exists
        DROP FUNCTION IF EXISTS get_direct_asset_transfer_requests(
            BIGINT, BIGINT, VARCHAR, VARCHAR, INT, INT, VARCHAR
        );

        -- Function to get direct asset transfer requests with filters
        CREATE OR REPLACE FUNCTION get_direct_asset_transfer_requests(
            p_tenant_id BIGINT,
            p_user_id BIGINT DEFAULT NULL,
            p_status_filter VARCHAR DEFAULT NULL,
            p_search_term VARCHAR DEFAULT NULL,
            p_page_number INT DEFAULT 1,
            p_page_size INT DEFAULT 20,
            p_sort_by VARCHAR DEFAULT 'newest'
        ) RETURNS TABLE (
            id BIGINT,
            transfer_request_number TEXT,
            transfer_type VARCHAR,
            transfer_status VARCHAR,
            work_flow_request BIGINT,
            transfer_reason TEXT,
            special_note TEXT,
            targeted_responsible_person BIGINT,
            targeted_person_name VARCHAR,
            targeted_person_email VARCHAR,
            targeted_person_profile_image VARCHAR,
            targeted_person_designation_id BIGINT,
            targeted_person_designation VARCHAR,
            requester_id BIGINT,
            requester_name VARCHAR,
            requester_email VARCHAR,
            requester_profile_image VARCHAR,
            assets_count BIGINT,
            requested_date TIMESTAMP,
            is_cancelled BOOLEAN,
            reason_for_cancellation TEXT,
            created_at TIMESTAMP,
            updated_at TIMESTAMP,
            total_count BIGINT
        ) LANGUAGE plpgsql AS $$
        DECLARE
            v_offset INT;
            v_total_count BIGINT;
        BEGIN
            -- Calculate offset
            v_offset := (p_page_number - 1) * p_page_size;

            -- Get total count
            SELECT COUNT(*)
            INTO v_total_count
            FROM direct_asset_transfer_requests datr
            WHERE datr.tenant_id = p_tenant_id
                AND datr.deleted_at IS NULL
                AND (p_user_id IS NULL OR datr.requester_id = p_user_id)
                AND (p_status_filter IS NULL OR datr.transfer_status = ANY(string_to_array(p_status_filter, ',')))
                AND (
                    p_search_term IS NULL 
                    OR datr.transfer_request_number ILIKE '%' || p_search_term || '%'
                    OR datr.transfer_reason ILIKE '%' || p_search_term || '%'
                );

            -- Return paginated results
            RETURN QUERY
            SELECT
                datr.id,
                datr.transfer_request_number::TEXT,
                datr.transfer_type,
                datr.transfer_status,
                datr.work_flow_request,
                datr.transfer_reason,
                datr.special_note,
                datr.targeted_responsible_person,
                tp.name AS targeted_person_name,
                tp.email AS targeted_person_email,
                tp.profile_image AS targeted_person_profile_image,
                des_target.id AS targeted_person_designation_id,
                des_target.designation AS targeted_person_designation,
                datr.requester_id,
                u.name AS requester_name,
                u.email AS requester_email,
                u.profile_image AS requester_profile_image,
                (
                    SELECT COUNT(*)
                    FROM direct_asset_transfer_request_items datri
                    WHERE datri.direct_asset_transfer_request_id = datr.id
                        AND datri.deleted_at IS NULL
                ) AS assets_count,
                datr.requested_date,
                datr.is_cancelled,
                datr.reason_for_cancellation,
                datr.created_at,
                datr.updated_at,
                v_total_count AS total_count
            FROM direct_asset_transfer_requests datr
            LEFT JOIN users u ON u.id = datr.requester_id
            LEFT JOIN users tp ON tp.id = datr.targeted_responsible_person
            LEFT JOIN designations des_target ON tp.designation_id = des_target.id
            WHERE datr.tenant_id = p_tenant_id
                AND datr.deleted_at IS NULL
                AND (p_user_id IS NULL OR datr.requester_id = p_user_id)
                AND (p_status_filter IS NULL OR datr.transfer_status = ANY(string_to_array(p_status_filter, ',')))
                AND (
                    p_search_term IS NULL 
                    OR datr.transfer_request_number ILIKE '%' || p_search_term || '%'
                    OR datr.transfer_reason ILIKE '%' || p_search_term || '%'
                )
            ORDER BY
                CASE WHEN p_sort_by = 'newest' THEN datr.created_at END DESC,
                CASE WHEN p_sort_by = 'oldest' THEN datr.created_at END ASC,
                CASE WHEN p_sort_by = 'status' THEN datr.transfer_status END ASC
            LIMIT p_page_size
            OFFSET v_offset;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DROP FUNCTION IF EXISTS get_direct_asset_transfer_requests(
            BIGINT, BIGINT, VARCHAR, VARCHAR, INT, INT, VARCHAR
        );
        SQL);
    }
};