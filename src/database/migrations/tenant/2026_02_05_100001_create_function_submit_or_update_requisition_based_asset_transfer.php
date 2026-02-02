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
        DO $$
        DECLARE
            r RECORD;
        BEGIN 
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'submit_or_update_requisition_based_asset_transfer'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION submit_or_update_requisition_based_asset_transfer(
            IN _requisition_by BIGINT,
            IN _based_asset_requisition BIGINT,
            IN _requisition_status VARCHAR(255),
            IN _tenant_id BIGINT,
            IN _current_time TIMESTAMP WITH TIME ZONE,
            IN _requested_date TIMESTAMP WITH TIME ZONE,
            IN _items JSONB,
            IN _requisition_id VARCHAR(255) DEFAULT NULL,
            IN _special_note TEXT DEFAULT NULL,
            IN _is_gatepass_required BOOLEAN DEFAULT FALSE,
            IN _is_cancelled BOOLEAN DEFAULT FALSE,
            IN _reason_for_cancellation TEXT DEFAULT NULL,
            IN _update_status_only BOOLEAN DEFAULT FALSE
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            old_requisition JSONB,
            new_requisition JSONB,
            requisition_items JSONB
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            curr_val INT;
            v_requisition_based_asset_transfer_id BIGINT;
            requisition_transfer_id TEXT;
            item JSONB;
            old_data JSONB;
            new_data JSONB;
            items_data JSONB := '[]'::JSONB;
            v_item_id BIGINT;
            v_asset_item_id BIGINT;
            v_based_internal_asset_requisitions_items BIGINT;
            v_is_reset_current_employee_schedule BOOLEAN;
            v_is_reset_current_availability_schedule BOOLEAN;
            v_item_special_note TEXT;
            v_asset_requester_approval_status VARCHAR(255);
            v_asset_requester_note TEXT;
            v_asset_requester_action_date TIMESTAMP WITH TIME ZONE;
        BEGIN
            ----------------------------------------------------------------
            -- Validations
            ----------------------------------------------------------------
            IF _tenant_id IS NULL OR _tenant_id <= 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT,
                    'Invalid tenant ID provided'::TEXT,
                    NULL::JSONB,
                    NULL::JSONB,
                    '[]'::JSONB;
                RETURN;
            END IF;

            IF _requisition_by IS NULL OR _requisition_by <= 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT,
                    'Invalid requisition_by user ID provided'::TEXT,
                    NULL::JSONB,
                    NULL::JSONB,
                    '[]'::JSONB;
                RETURN;
            END IF;

            -- Only validate based_asset_requisition when not doing status-only update
            IF _update_status_only = FALSE AND (_based_asset_requisition IS NULL OR _based_asset_requisition <= 0) THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT,
                    'Invalid based_asset_requisition ID provided'::TEXT,
                    NULL::JSONB,
                    NULL::JSONB,
                    '[]'::JSONB;
                RETURN;
            END IF;

            -- Validate status
            IF _requisition_status NOT IN ('PENDING', 'APPROVED', 'REJECTED') THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT,
                    'Invalid requisition_status. Must be PENDING, APPROVED, or REJECTED'::TEXT,
                    NULL::JSONB,
                    NULL::JSONB,
                    '[]'::JSONB;
                RETURN;
            END IF;

            -- Validate that based_asset_requisition exists (only when not doing status-only update)
            IF _update_status_only = FALSE AND NOT EXISTS (
                SELECT 1 FROM internal_asset_requisitions 
                WHERE id = _based_asset_requisition 
                AND deleted_at IS NULL
            ) THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT,
                    'Based asset requisition does not exist'::TEXT,
                    NULL::JSONB,
                    NULL::JSONB,
                    '[]'::JSONB;
                RETURN;
            END IF;

            ----------------------------------------------------------------
            -- Check if updating existing requisition
            ----------------------------------------------------------------
            IF _requisition_id IS NOT NULL THEN
                SELECT id, to_jsonb(requisition_based_asset_transfer_requwest.*) 
                INTO v_requisition_based_asset_transfer_id, old_data
                FROM requisition_based_asset_transfer_requwest 
                WHERE requisition_id = _requisition_id
                AND deleted_at IS NULL;

                IF NOT FOUND THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Requisition not found or already deleted'::TEXT,
                        NULL::JSONB,
                        NULL::JSONB,
                        '[]'::JSONB;
                    RETURN;
                END IF;

                ----------------------------------------------------------------
                -- Update only status if requested
                ----------------------------------------------------------------
                IF _update_status_only = TRUE THEN
                    UPDATE requisition_based_asset_transfer_requwest 
                    SET requisition_status = _requisition_status,
                        is_cancelled = _is_cancelled,
                        reason_for_cancellation = CASE 
                            WHEN _is_cancelled = TRUE THEN _reason_for_cancellation 
                            ELSE NULL 
                        END,
                        updated_at = _current_time
                    WHERE id = v_requisition_based_asset_transfer_id
                    RETURNING to_jsonb(requisition_based_asset_transfer_requwest.*) INTO new_data;

                    -- Fetch current items
                    SELECT COALESCE(
                        jsonb_agg(
                            jsonb_build_object(
                                'id', rbatri.id,
                                'asset_item_id', rbatri.asset_item_id,
                                'based_internal_asset_requisitions_items', rbatri.based_internal_asset_requisitions_items,
                                'is_reset_current_employee_schedule', rbatri.is_reset_current_employee_schedule,
                                'is_reset_current_availability_schedule', rbatri.is_reset_current_availability_schedule,
                                'special_note', rbatri.special_note,
                                'asset_requester_approval_status', rbatri.asset_requester_approval_status,
                                'asset_requester_note', rbatri.asset_requester_note,
                                'asset_requester_action_date', rbatri.asset_requester_action_date,
                                'asset_item', jsonb_build_object(
                                    'id', ai.id,
                                    'model_number', ai.model_number,
                                    'serial_number', ai.serial_number
                                ),
                                'requisition_item', jsonb_build_object(
                                    'id', iari.id,
                                    'item_name', iari.item_name,
                                    'required_quantity', iari.required_quantity,
                                    'fulfilled_quantity', iari.fulfilled_quantity
                                )
                            )
                        ) FILTER (WHERE rbatri.id IS NOT NULL),
                        '[]'::JSONB
                    ) INTO items_data
                    FROM requisition_based_asset_transfer_requwest_items rbatri
                    LEFT JOIN asset_items ai ON rbatri.asset_item_id = ai.id
                    LEFT JOIN internal_asset_requisitions_items iari ON rbatri.based_internal_asset_requisitions_items = iari.id
                    WHERE rbatri.requisition_based_asset_transfer_requwest = v_requisition_based_asset_transfer_id
                    AND rbatri.deleted_at IS NULL;

                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT,
                        'Requisition status updated successfully'::TEXT,
                        old_data,
                        new_data,
                        items_data;
                    RETURN;
                END IF;

                ----------------------------------------------------------------
                -- Full update of requisition and items
                ----------------------------------------------------------------
                UPDATE requisition_based_asset_transfer_requwest 
                SET based_asset_requisition = _based_asset_requisition,
                    requested_date = _requested_date,
                    requisition_status = _requisition_status,
                    special_note = _special_note,
                    is_gatepass_required = _is_gatepass_required,
                    is_cancelled = _is_cancelled,
                    reason_for_cancellation = CASE 
                        WHEN _is_cancelled = TRUE THEN _reason_for_cancellation 
                        ELSE NULL 
                    END,
                    updated_at = _current_time
                WHERE id = v_requisition_based_asset_transfer_id
                RETURNING to_jsonb(requisition_based_asset_transfer_requwest.*) INTO new_data;

                -- Soft delete all existing items for this requisition
                UPDATE requisition_based_asset_transfer_requwest_items
                SET deleted_at = _current_time,
                    isactive = FALSE,
                    updated_at = _current_time
                WHERE requisition_based_asset_transfer_requwest = v_requisition_based_asset_transfer_id
                AND deleted_at IS NULL;

                -- Insert new items
                FOR item IN SELECT * FROM jsonb_array_elements(_items) LOOP
                    v_asset_item_id := (item->>'asset_item_id')::BIGINT;
                    v_based_internal_asset_requisitions_items := (item->>'based_internal_asset_requisitions_items')::BIGINT;
                    v_is_reset_current_employee_schedule := COALESCE((item->>'is_reset_current_employee_schedule')::BOOLEAN, TRUE);
                    v_is_reset_current_availability_schedule := COALESCE((item->>'is_reset_current_availability_schedule')::BOOLEAN, FALSE);
                    v_item_special_note := item->>'special_note';
                    v_asset_requester_approval_status := COALESCE(item->>'asset_requester_approval_status', 'PENDING');
                    v_asset_requester_note := item->>'asset_requester_note';
                    v_asset_requester_action_date := (item->>'asset_requester_action_date')::TIMESTAMP WITH TIME ZONE;

                    -- Validate item status
                    IF v_asset_requester_approval_status NOT IN ('PENDING', 'APPROVED', 'REJECTED') THEN
                        RETURN QUERY SELECT 
                            'FAILURE'::TEXT,
                            'Invalid asset_requester_approval_status. Must be PENDING, APPROVED, or REJECTED'::TEXT,
                            NULL::JSONB,
                            NULL::JSONB,
                            '[]'::JSONB;
                        RETURN;
                    END IF;

                    INSERT INTO requisition_based_asset_transfer_requwest_items (
                        requisition_based_asset_transfer_requwest,
                        asset_item_id,
                        based_internal_asset_requisitions_items,
                        is_reset_current_employee_schedule,
                        is_reset_current_availability_schedule,
                        special_note,
                        asset_requester_approval_status,
                        asset_requester_note,
                        asset_requester_action_date,
                        tenant_id,
                        created_at,
                        updated_at
                    ) VALUES (
                        v_requisition_based_asset_transfer_id,
                        v_asset_item_id,
                        v_based_internal_asset_requisitions_items,
                        v_is_reset_current_employee_schedule,
                        v_is_reset_current_availability_schedule,
                        v_item_special_note,
                        v_asset_requester_approval_status,
                        v_asset_requester_note,
                        v_asset_requester_action_date,
                        _tenant_id,
                        _current_time,
                        _current_time
                    );
                END LOOP;

            ELSE
                ----------------------------------------------------------------
                -- Create new requisition
                ----------------------------------------------------------------
                -- Generate requisition ID
                SELECT nextval('requisition_based_asset_transfer_id_seq') INTO curr_val;
                requisition_transfer_id := 'REQTRANS-' || LPAD(curr_val::TEXT, 6, '0');

                -- Insert new requisition based asset transfer request
                INSERT INTO requisition_based_asset_transfer_requwest (
                    requisition_id,
                    based_asset_requisition,
                    requisition_by,
                    requested_date,
                    requisition_status,
                    special_note,
                    is_gatepass_required,
                    is_cancelled,
                    reason_for_cancellation,
                    tenant_id,
                    created_at,
                    updated_at
                ) VALUES (
                    requisition_transfer_id,
                    _based_asset_requisition,
                    _requisition_by,
                    _requested_date,
                    _requisition_status,
                    _special_note,
                    _is_gatepass_required,
                    _is_cancelled,
                    CASE WHEN _is_cancelled = TRUE THEN _reason_for_cancellation ELSE NULL END,
                    _tenant_id,
                    _current_time,
                    _current_time
                )
                RETURNING id, to_jsonb(requisition_based_asset_transfer_requwest.*) 
                INTO v_requisition_based_asset_transfer_id, new_data;

                -- Insert each item
                FOR item IN SELECT * FROM jsonb_array_elements(_items) LOOP
                    v_asset_item_id := (item->>'asset_item_id')::BIGINT;
                    v_based_internal_asset_requisitions_items := (item->>'based_internal_asset_requisitions_items')::BIGINT;
                    v_is_reset_current_employee_schedule := COALESCE((item->>'is_reset_current_employee_schedule')::BOOLEAN, TRUE);
                    v_is_reset_current_availability_schedule := COALESCE((item->>'is_reset_current_availability_schedule')::BOOLEAN, FALSE);
                    v_item_special_note := item->>'special_note';
                    v_asset_requester_approval_status := COALESCE(item->>'asset_requester_approval_status', 'PENDING');
                    v_asset_requester_note := item->>'asset_requester_note';
                    v_asset_requester_action_date := (item->>'asset_requester_action_date')::TIMESTAMP WITH TIME ZONE;

                    -- Validate item status
                    IF v_asset_requester_approval_status NOT IN ('PENDING', 'APPROVED', 'REJECTED') THEN
                        RETURN QUERY SELECT 
                            'FAILURE'::TEXT,
                            'Invalid asset_requester_approval_status. Must be PENDING, APPROVED, or REJECTED'::TEXT,
                            NULL::JSONB,
                            NULL::JSONB,
                            '[]'::JSONB;
                        RETURN;
                    END IF;

                    INSERT INTO requisition_based_asset_transfer_requwest_items (
                        requisition_based_asset_transfer_requwest,
                        asset_item_id,
                        based_internal_asset_requisitions_items,
                        is_reset_current_employee_schedule,
                        is_reset_current_availability_schedule,
                        special_note,
                        asset_requester_approval_status,
                        asset_requester_note,
                        asset_requester_action_date,
                        tenant_id,
                        created_at,
                        updated_at
                    ) VALUES (
                        v_requisition_based_asset_transfer_id,
                        v_asset_item_id,
                        v_based_internal_asset_requisitions_items,
                        v_is_reset_current_employee_schedule,
                        v_is_reset_current_availability_schedule,
                        v_item_special_note,
                        v_asset_requester_approval_status,
                        v_asset_requester_note,
                        v_asset_requester_action_date,
                        _tenant_id,
                        _current_time,
                        _current_time
                    );
                END LOOP;
            END IF;

            ----------------------------------------------------------------
            -- Fetch all related items with joins
            ----------------------------------------------------------------
            SELECT COALESCE(
                jsonb_agg(
                    jsonb_build_object(
                        'id', rbatri.id,
                        'asset_item_id', rbatri.asset_item_id,
                        'based_internal_asset_requisitions_items', rbatri.based_internal_asset_requisitions_items,
                        'is_reset_current_employee_schedule', rbatri.is_reset_current_employee_schedule,
                        'is_reset_current_availability_schedule', rbatri.is_reset_current_availability_schedule,
                        'special_note', rbatri.special_note,
                        'asset_requester_approval_status', rbatri.asset_requester_approval_status,
                        'asset_requester_note', rbatri.asset_requester_note,
                        'asset_requester_action_date', rbatri.asset_requester_action_date,
                        'asset_item', jsonb_build_object(
                            'id', ai.id,
                            'model_number', ai.model_number,
                            'serial_number', ai.serial_number,
                            'asset', jsonb_build_object(
                                'id', a.id,
                                'name', a.name
                            )
                        ),
                        'requisition_item', jsonb_build_object(
                            'id', iari.id,
                            'item_name', iari.item_name,
                            'required_quantity', iari.required_quantity,
                            'fulfilled_quantity', iari.fulfilled_quantity,
                            'required_date', iari.required_date,
                            'reason_for_requirement', iari.reason_for_requirement
                        )
                    )
                ) FILTER (WHERE rbatri.id IS NOT NULL),
                '[]'::JSONB
            ) INTO items_data
            FROM requisition_based_asset_transfer_requwest_items rbatri
            LEFT JOIN asset_items ai ON rbatri.asset_item_id = ai.id
            LEFT JOIN assets a ON ai.asset_id = a.id
            LEFT JOIN internal_asset_requisitions_items iari ON rbatri.based_internal_asset_requisitions_items = iari.id
            WHERE rbatri.requisition_based_asset_transfer_requwest = v_requisition_based_asset_transfer_id
            AND rbatri.deleted_at IS NULL;

            ----------------------------------------------------------------
            -- Return final result
            ----------------------------------------------------------------
            RETURN QUERY SELECT 
                'SUCCESS'::TEXT,
                CASE 
                    WHEN _requisition_id IS NOT NULL THEN 'Requisition based asset transfer updated successfully'
                    ELSE 'Requisition based asset transfer created successfully'
                END::TEXT,
                old_data,
                new_data,
                items_data;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS submit_or_update_requisition_based_asset_transfer CASCADE;');
    }
};