<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Update target_person_review_item to update asset items on approval
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION target_person_review_item(
            p_item_id BIGINT,
            p_target_person_id BIGINT,
            p_approval_status VARCHAR,              -- "APPROVED" or "REJECTED"
            p_note TEXT DEFAULT NULL,
            p_tenant_id BIGINT DEFAULT NULL,
            p_target_person_name VARCHAR DEFAULT NULL,
            p_current_time TIMESTAMPTZ DEFAULT now()
        ) RETURNS JSONB LANGUAGE plpgsql AS $$
        DECLARE
            v_item_exists BOOLEAN;
            v_transfer_request_id BIGINT;
            v_targeted_person_id BIGINT;
            v_is_received BOOLEAN;
            v_asset_tag VARCHAR;
            v_transfer_request_number VARCHAR;
            v_asset_update_result JSONB;
        BEGIN
            -- Check if approval status is valid
            IF p_approval_status NOT IN ('APPROVED', 'REJECTED') THEN
                RETURN jsonb_build_object(
                    'status', 'ERROR',
                    'message', 'Invalid approval status. Must be APPROVED or REJECTED'
                );
            END IF;

            -- Get item details and verify access
            SELECT 
                EXISTS(SELECT 1 FROM direct_asset_transfer_request_items WHERE id = p_item_id AND deleted_at IS NULL),
                datri.direct_asset_transfer_request_id,
                datr.targeted_responsible_person,
                datr.is_received_by_target_person,
                ai.asset_tag,
                datr.transfer_request_number
            INTO 
                v_item_exists, 
                v_transfer_request_id, 
                v_targeted_person_id, 
                v_is_received,
                v_asset_tag,
                v_transfer_request_number
            FROM direct_asset_transfer_request_items datri
            INNER JOIN direct_asset_transfer_requests datr ON datr.id = datri.direct_asset_transfer_request_id
            LEFT JOIN asset_items ai ON ai.id = datri.asset_item_id
            WHERE datri.id = p_item_id 
                AND datri.tenant_id = p_tenant_id
                AND datri.deleted_at IS NULL;

            -- Validate item exists
            IF NOT v_item_exists THEN
                RETURN jsonb_build_object(
                    'status', 'ERROR',
                    'message', 'Transfer item not found or has been deleted'
                );
            END IF;

            -- Check if user is the targeted person
            IF v_targeted_person_id IS NULL OR v_targeted_person_id != p_target_person_id THEN
                RETURN jsonb_build_object(
                    'status', 'ERROR',
                    'message', 'You are not authorized to review this item'
                );
            END IF;

            -- Check if request has been received
            IF NOT v_is_received THEN
                RETURN jsonb_build_object(
                    'status', 'ERROR',
                    'message', 'Transfer request must be marked as received before reviewing items'
                );
            END IF;

            -- Update item approval status
            UPDATE direct_asset_transfer_request_items
            SET 
                target_person_approval_status = p_approval_status,
                target_person_note = p_note,
                target_person_action_date = p_current_time,
                updated_at = p_current_time
            WHERE id = p_item_id
                AND tenant_id = p_tenant_id;

            -- If approved, update the asset item and create transfer log
            IF p_approval_status = 'APPROVED' THEN
                v_asset_update_result := update_asset_item_on_target_approval(
                    p_item_id,
                    p_target_person_id,
                    p_tenant_id,
                    p_current_time
                );
                
                -- Check if asset update was successful
                IF v_asset_update_result->>'status' != 'SUCCESS' THEN
                    RAISE WARNING 'Asset item update failed: %', v_asset_update_result->>'message';
                    -- Continue anyway - the approval was successful even if asset update failed
                END IF;
            END IF;

            -- Log activity with correct parameter order
            BEGIN
                PERFORM log_activity(
                    CASE 
                        WHEN p_approval_status = 'APPROVED' THEN 'direct_transfer.target_approve_item'
                        ELSE 'direct_transfer.target_reject_item'
                    END,                                                                -- p_log_name
                    'Target person ' || p_approval_status || ' item (Asset: ' || COALESCE(v_asset_tag, 'N/A') || ')' || 
                    CASE WHEN p_note IS NOT NULL THEN ': ' || p_note ELSE '' END,     -- p_description
                    'direct_asset_transfer_request_items',                             -- p_subject_type
                    p_item_id,                                                         -- p_subject_id
                    'user',                                                            -- p_causer_type
                    p_target_person_id,                                                -- p_causer_id
                    jsonb_build_object(
                        'approval_status', p_approval_status,
                        'note', p_note,
                        'asset_tag', v_asset_tag,
                        'transfer_request_number', v_transfer_request_number,
                        'target_person_name', p_target_person_name,
                        'action_time', p_current_time,
                        'asset_updated', CASE WHEN p_approval_status = 'APPROVED' THEN 
                            (v_asset_update_result->>'status' = 'SUCCESS') ELSE FALSE END
                    ),                                                                 -- p_properties
                    p_tenant_id                                                        -- p_tenant_id
                );
            EXCEPTION WHEN OTHERS THEN
                -- Log activity failure shouldn't prevent the main operation
                RAISE NOTICE 'Log activity failed: %', SQLERRM;
            END;

            RETURN jsonb_build_object(
                'status', 'SUCCESS',
                'message', 'Item ' || p_approval_status || ' successfully',
                'item_id', p_item_id,
                'transfer_request_id', v_transfer_request_id,
                'transfer_request_number', v_transfer_request_number,
                'approval_status', p_approval_status,
                'action_date', p_current_time,
                'asset_updated', CASE WHEN p_approval_status = 'APPROVED' THEN 
                    v_asset_update_result ELSE NULL END
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
        // Revert to previous version without asset update
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION target_person_review_item(
            p_item_id BIGINT,
            p_target_person_id BIGINT,
            p_approval_status VARCHAR,
            p_note TEXT DEFAULT NULL,
            p_tenant_id BIGINT DEFAULT NULL,
            p_target_person_name VARCHAR DEFAULT NULL,
            p_current_time TIMESTAMPTZ DEFAULT now()
        ) RETURNS JSONB LANGUAGE plpgsql AS $$
        DECLARE
            v_item_exists BOOLEAN;
            v_transfer_request_id BIGINT;
            v_targeted_person_id BIGINT;
            v_is_received BOOLEAN;
            v_asset_tag VARCHAR;
            v_transfer_request_number VARCHAR;
        BEGIN
            IF p_approval_status NOT IN ('APPROVED', 'REJECTED') THEN
                RETURN jsonb_build_object('status', 'ERROR', 'message', 'Invalid approval status. Must be APPROVED or REJECTED');
            END IF;

            SELECT 
                EXISTS(SELECT 1 FROM direct_asset_transfer_request_items WHERE id = p_item_id AND deleted_at IS NULL),
                datri.direct_asset_transfer_request_id,
                datr.targeted_responsible_person,
                datr.is_received_by_target_person,
                ai.asset_tag,
                datr.transfer_request_number
            INTO 
                v_item_exists, v_transfer_request_id, v_targeted_person_id, v_is_received, v_asset_tag, v_transfer_request_number
            FROM direct_asset_transfer_request_items datri
            INNER JOIN direct_asset_transfer_requests datr ON datr.id = datri.direct_asset_transfer_request_id
            LEFT JOIN asset_items ai ON ai.id = datri.asset_item_id
            WHERE datri.id = p_item_id AND datri.tenant_id = p_tenant_id AND datri.deleted_at IS NULL;

            IF NOT v_item_exists THEN
                RETURN jsonb_build_object('status', 'ERROR', 'message', 'Transfer item not found or has been deleted');
            END IF;

            IF v_targeted_person_id IS NULL OR v_targeted_person_id != p_target_person_id THEN
                RETURN jsonb_build_object('status', 'ERROR', 'message', 'You are not authorized to review this item');
            END IF;

            IF NOT v_is_received THEN
                RETURN jsonb_build_object('status', 'ERROR', 'message', 'Transfer request must be marked as received before reviewing items');
            END IF;

            UPDATE direct_asset_transfer_request_items
            SET 
                target_person_approval_status = p_approval_status,
                target_person_note = p_note,
                target_person_action_date = p_current_time,
                updated_at = p_current_time
            WHERE id = p_item_id AND tenant_id = p_tenant_id;

            BEGIN
                PERFORM log_activity(
                    CASE WHEN p_approval_status = 'APPROVED' THEN 'direct_transfer.target_approve_item' ELSE 'direct_transfer.target_reject_item' END,
                    'Target person ' || p_approval_status || ' item (Asset: ' || COALESCE(v_asset_tag, 'N/A') || ')' || CASE WHEN p_note IS NOT NULL THEN ': ' || p_note ELSE '' END,
                    'direct_asset_transfer_request_items', p_item_id, 'user', p_target_person_id,
                    jsonb_build_object('approval_status', p_approval_status, 'note', p_note, 'asset_tag', v_asset_tag, 'transfer_request_number', v_transfer_request_number, 'target_person_name', p_target_person_name, 'action_time', p_current_time),
                    p_tenant_id
                );
            EXCEPTION WHEN OTHERS THEN
                RAISE NOTICE 'Log activity failed: %', SQLERRM;
            END;

            RETURN jsonb_build_object(
                'status', 'SUCCESS', 'message', 'Item ' || p_approval_status || ' successfully',
                'item_id', p_item_id, 'transfer_request_id', v_transfer_request_id,
                'transfer_request_number', v_transfer_request_number,
                'approval_status', p_approval_status, 'action_date', p_current_time
            );

        EXCEPTION WHEN OTHERS THEN
            RETURN jsonb_build_object('status', 'ERROR', 'message', 'An error occurred: ' || SQLERRM);
        END;
        $$;
        SQL);
    }
};
