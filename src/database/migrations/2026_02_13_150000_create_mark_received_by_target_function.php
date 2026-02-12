<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Mark transfer request as received by target person
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION mark_transfer_request_received_by_target(
            p_transfer_request_id BIGINT,
            p_target_person_id BIGINT,
            p_tenant_id BIGINT,
            p_target_person_name VARCHAR DEFAULT NULL,
            p_current_time TIMESTAMPTZ DEFAULT now()
        ) RETURNS JSONB LANGUAGE plpgsql AS $$
        DECLARE
            v_request_exists BOOLEAN;
            v_targeted_person_id BIGINT;
            v_transfer_status VARCHAR;
            v_transfer_request_number VARCHAR;
        BEGIN
            -- Validate request exists and belongs to target person
            SELECT 
                EXISTS(SELECT 1 FROM direct_asset_transfer_requests 
                       WHERE id = p_transfer_request_id 
                       AND tenant_id = p_tenant_id 
                       AND deleted_at IS NULL),
                targeted_responsible_person,
                transfer_status,
                transfer_request_number
            INTO v_request_exists, v_targeted_person_id, v_transfer_status, v_transfer_request_number
            FROM direct_asset_transfer_requests
            WHERE id = p_transfer_request_id AND tenant_id = p_tenant_id AND deleted_at IS NULL;

            IF NOT v_request_exists THEN
                RETURN jsonb_build_object(
                    'status', 'ERROR',
                    'message', 'Transfer request not found or has been deleted'
                );
            END IF;

            -- Check if user is the targeted person
            IF v_targeted_person_id IS NULL OR v_targeted_person_id != p_target_person_id THEN
                RETURN jsonb_build_object(
                    'status', 'ERROR',
                    'message', 'You are not the targeted person for this transfer request'
                );
            END IF;

            -- Check if request is approved
            IF v_transfer_status != 'APPROVED' THEN
                RETURN jsonb_build_object(
                    'status', 'ERROR',
                    'message', 'Transfer request must be approved before marking as received',
                    'current_status', v_transfer_status
                );
            END IF;

            -- Mark as received
            UPDATE direct_asset_transfer_requests
            SET 
                is_received_by_target_person = true,
                received_by_target_person_at = p_current_time,
                updated_at = p_current_time
            WHERE id = p_transfer_request_id
                AND tenant_id = p_tenant_id;

            -- Log activity with correct parameter order
            BEGIN
                PERFORM log_activity(
                    'direct_transfer.mark_received',                                    -- p_log_name
                    'Transfer request marked as received by target person: ' || p_target_person_name,  -- p_description
                    'direct_asset_transfer_requests',                                   -- p_subject_type
                    p_transfer_request_id,                                              -- p_subject_id
                    'user',                                                             -- p_causer_type
                    p_target_person_id,                                                 -- p_causer_id
                    jsonb_build_object(
                        'transfer_request_number', v_transfer_request_number,
                        'received_at', p_current_time,
                        'target_person_name', p_target_person_name
                    ),                                                                  -- p_properties
                    p_tenant_id                                                         -- p_tenant_id
                );
            EXCEPTION WHEN OTHERS THEN
                -- Log activity failure shouldn't prevent the main operation
                RAISE NOTICE 'Log activity failed: %', SQLERRM;
            END;

            RETURN jsonb_build_object(
                'status', 'SUCCESS',
                'message', 'Transfer request marked as received successfully',
                'transfer_request_id', p_transfer_request_id,
                'transfer_request_number', v_transfer_request_number,
                'received_at', p_current_time
            );

        EXCEPTION
            WHEN OTHERS THEN
                RETURN jsonb_build_object(
                    'status', 'ERROR',
                    'message', 'An error occurred: ' || SQLERRM
                );
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS mark_transfer_request_received_by_target CASCADE;');
    }
};