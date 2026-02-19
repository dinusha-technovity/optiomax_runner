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
        DROP FUNCTION IF EXISTS update_direct_asset_transfer_status(
            BIGINT, VARCHAR, TEXT, BIGINT, BIGINT, VARCHAR, TIMESTAMPTZ
        );

        -- Function to update direct asset transfer request status
        -- NOTE: Workflow approvals are handled by the dynamic workflow system
        CREATE OR REPLACE FUNCTION update_direct_asset_transfer_status(
            p_transfer_request_id BIGINT,
            p_new_status VARCHAR,
            p_reason TEXT DEFAULT NULL,
            p_user_id BIGINT DEFAULT NULL,
            p_tenant_id BIGINT DEFAULT NULL,
            p_user_name VARCHAR DEFAULT NULL,
            p_current_time TIMESTAMPTZ DEFAULT now()
        ) RETURNS TABLE (
            status TEXT,
            message TEXT,
            transfer_data JSONB
        ) LANGUAGE plpgsql AS $$
        DECLARE
            v_current_status VARCHAR;
            v_transfer_request_number TEXT;
            v_result_data JSONB;
            v_transfer_type VARCHAR;
        BEGIN
            -- Validation
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 
                    'Invalid tenant ID'::TEXT,
                    NULL::JSONB;
                RETURN;
            END IF;

            IF p_user_id IS NULL OR p_user_id <= 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 
                    'Invalid user ID'::TEXT,
                    NULL::JSONB;
                RETURN;
            END IF;

            IF p_new_status IS NULL OR p_new_status = '' THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 
                    'New status is required'::TEXT,
                    NULL::JSONB;
                RETURN;
            END IF;

            -- Get current status
            SELECT transfer_status, transfer_request_number, transfer_type
            INTO v_current_status, v_transfer_request_number, v_transfer_type
            FROM direct_asset_transfer_requests
            WHERE id = p_transfer_request_id 
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

            IF v_current_status IS NULL THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 
                    'Transfer request not found'::TEXT,
                    NULL::JSONB;
                RETURN;
            END IF;

            -- Handle cancellation with reason
            IF p_new_status = 'CANCELLED' THEN
                IF p_reason IS NULL OR LENGTH(p_reason) < 5 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT, 
                        'Cancellation reason is required (min 5 characters)'::TEXT,
                        NULL::JSONB;
                    RETURN;
                END IF;

                UPDATE direct_asset_transfer_requests
                SET 
                    transfer_status = p_new_status,
                    is_cancelled = true,
                    reason_for_cancellation = p_reason,
                    updated_at = p_current_time
                WHERE id = p_transfer_request_id;

                -- Log cancellation activity
                IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
                    PERFORM log_activity(
                        'direct_asset_transfer.cancelled',
                        'Transfer request cancelled by ' || p_user_name || ': ' || v_transfer_request_number,
                        'direct_asset_transfer_request',
                        p_transfer_request_id,
                        'user',
                        p_user_id,
                        jsonb_build_object(
                            'transfer_request_id', p_transfer_request_id,
                            'transfer_request_number', v_transfer_request_number,
                            'previous_status', v_current_status,
                            'new_status', p_new_status,
                            'reason', p_reason
                        ),
                        p_tenant_id
                    );
                END IF;

            -- Handle completion (execute actual transfer)
            ELSIF p_new_status = 'COMPLETED' THEN
                -- Execute the actual transfer for all items
                UPDATE asset_items ai
                SET
                    responsible_person = COALESCE(datri.new_owner_id, ai.responsible_person),
                    department = COALESCE(datri.new_department_id, ai.department),
                    asset_location_latitude = COALESCE(datri.new_location_latitude::TEXT, ai.asset_location_latitude),
                    asset_location_longitude = COALESCE(datri.new_location_longitude::TEXT, ai.asset_location_longitude),
                    updated_at = p_current_time
                FROM direct_asset_transfer_request_items datri
                WHERE datri.direct_asset_transfer_request_id = p_transfer_request_id
                    AND datri.asset_item_id = ai.id
                    AND datri.is_transferred = false
                    AND datri.deleted_at IS NULL;

                -- Mark items as transferred
                UPDATE direct_asset_transfer_request_items
                SET 
                    is_transferred = true,
                    transferred_at = p_current_time,
                    updated_at = p_current_time
                WHERE direct_asset_transfer_request_id = p_transfer_request_id
                    AND is_transferred = false;

                -- Update main request status
                UPDATE direct_asset_transfer_requests
                SET 
                    transfer_status = p_new_status,
                    updated_at = p_current_time
                WHERE id = p_transfer_request_id;

                -- Log completion activity
                IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
                    PERFORM log_activity(
                        'direct_asset_transfer.completed',
                        'Transfer request completed by ' || p_user_name || ': ' || v_transfer_request_number,
                        'direct_asset_transfer_request',
                        p_transfer_request_id,
                        'user',
                        p_user_id,
                        jsonb_build_object(
                            'transfer_request_id', p_transfer_request_id,
                            'transfer_request_number', v_transfer_request_number,
                            'previous_status', v_current_status,
                            'new_status', p_new_status,
                            'transfer_type', v_transfer_type
                        ),
                        p_tenant_id
                    );
                END IF;

            -- Handle regular status updates (workflow-driven)
            ELSE
                UPDATE direct_asset_transfer_requests
                SET 
                    transfer_status = p_new_status,
                    updated_at = p_current_time
                WHERE id = p_transfer_request_id;

                -- Log status change activity
                IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
                    PERFORM log_activity(
                        'direct_asset_transfer.status_changed',
                        'Transfer request status changed by ' || p_user_name || ': ' || v_transfer_request_number || ' from ' || v_current_status || ' to ' || p_new_status,
                        'direct_asset_transfer_request',
                        p_transfer_request_id,
                        'user',
                        p_user_id,
                        jsonb_build_object(
                            'transfer_request_id', p_transfer_request_id,
                            'transfer_request_number', v_transfer_request_number,
                            'previous_status', v_current_status,
                            'new_status', p_new_status
                        ),
                        p_tenant_id
                    );
                END IF;
            END IF;

            -- Build result data
            SELECT jsonb_build_object(
                'id', p_transfer_request_id,
                'transfer_request_number', v_transfer_request_number,
                'previous_status', v_current_status,
                'new_status', p_new_status
            ) INTO v_result_data;

            RETURN QUERY SELECT 
                'SUCCESS'::TEXT,
                'Transfer request status updated successfully'::TEXT,
                v_result_data;
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
        DROP FUNCTION IF EXISTS update_direct_asset_transfer_status(
            BIGINT, VARCHAR, TEXT, BIGINT, BIGINT, VARCHAR, TIMESTAMPTZ
        );
        SQL);
    }
};