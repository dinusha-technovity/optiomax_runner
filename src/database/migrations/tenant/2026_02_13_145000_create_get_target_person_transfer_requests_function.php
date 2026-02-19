<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Get transfer requests assigned to target person
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            DO $$
            DECLARE
                r RECORD;
            BEGIN 
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_target_person_transfer_requests'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

        CREATE OR REPLACE FUNCTION get_target_person_transfer_requests(
            p_target_person_id BIGINT DEFAULT NULL,
            p_tenant_id BIGINT DEFAULT NULL,
            p_received_filter VARCHAR DEFAULT NULL,     -- "RECEIVED", "NOT_RECEIVED", NULL (all)
            p_search_term VARCHAR DEFAULT NULL,
            p_page_number INT DEFAULT 1,
            p_page_size INT DEFAULT 20,
            p_sort_by VARCHAR DEFAULT 'newest'        -- newest, oldest, status
        ) RETURNS TABLE (
            id BIGINT,
            transfer_request_number TEXT,
            transfer_type VARCHAR,
            transfer_status VARCHAR,
            work_flow_request BIGINT,
            transfer_reason TEXT,
            special_note TEXT,
            is_received_by_target_person BOOLEAN,
            received_by_target_person_at TIMESTAMP,
            requester_id BIGINT,
            requester_name VARCHAR,
            requester_email VARCHAR,
            requester_profile_image VARCHAR,
            assets_count BIGINT,
            pending_items_count BIGINT,
            approved_items_count BIGINT,
            rejected_items_count BIGINT,
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
                AND datr.targeted_responsible_person = p_target_person_id
                AND datr.transfer_status = 'APPROVED'  -- Only show approved requests
                AND (
                    p_received_filter IS NULL 
                    OR (p_received_filter = 'RECEIVED' AND datr.is_received_by_target_person = true)
                    OR (p_received_filter = 'NOT_RECEIVED' AND datr.is_received_by_target_person = false)
                )
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
                datr.is_received_by_target_person,
                datr.received_by_target_person_at,
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
                (
                    SELECT COUNT(*)
                    FROM direct_asset_transfer_request_items datri
                    WHERE datri.direct_asset_transfer_request_id = datr.id
                        AND datri.deleted_at IS NULL
                        AND datri.target_person_approval_status = 'PENDING'
                ) AS pending_items_count,
                (
                    SELECT COUNT(*)
                    FROM direct_asset_transfer_request_items datri
                    WHERE datri.direct_asset_transfer_request_id = datr.id
                        AND datri.deleted_at IS NULL
                        AND datri.target_person_approval_status = 'APPROVED'
                ) AS approved_items_count,
                (
                    SELECT COUNT(*)
                    FROM direct_asset_transfer_request_items datri
                    WHERE datri.direct_asset_transfer_request_id = datr.id
                        AND datri.deleted_at IS NULL
                        AND datri.target_person_approval_status = 'REJECTED'
                ) AS rejected_items_count,
                datr.requested_date,
                datr.is_cancelled,
                datr.reason_for_cancellation,
                datr.created_at,
                datr.updated_at,
                v_total_count AS total_count
            FROM direct_asset_transfer_requests datr
            LEFT JOIN users u ON u.id = datr.requester_id
            WHERE datr.tenant_id = p_tenant_id
                AND datr.deleted_at IS NULL
                AND datr.targeted_responsible_person = p_target_person_id
                AND datr.transfer_status = 'APPROVED'
                AND (
                    p_received_filter IS NULL 
                    OR (p_received_filter = 'RECEIVED' AND datr.is_received_by_target_person = true)
                    OR (p_received_filter = 'NOT_RECEIVED' AND datr.is_received_by_target_person = false)
                )
                AND (
                    p_search_term IS NULL 
                    OR datr.transfer_request_number ILIKE '%' || p_search_term || '%'
                    OR datr.transfer_reason ILIKE '%' || p_search_term || '%'
                )
            ORDER BY
                CASE WHEN p_sort_by = 'newest' THEN datr.created_at END DESC,
                CASE WHEN p_sort_by = 'oldest' THEN datr.created_at END ASC,
                CASE WHEN p_sort_by = 'status' THEN datr.is_received_by_target_person::TEXT END ASC
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
        DB::unprepared('DROP FUNCTION IF EXISTS get_target_person_transfer_requests CASCADE;');
    }
};