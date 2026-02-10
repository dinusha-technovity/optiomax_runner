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
                WHERE proname = 'update_asset_transfer_approval_status'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION update_asset_transfer_approval_status(
            IN _transfer_item_id BIGINT,
            IN _approval_status VARCHAR(50),
            IN _approved_by BIGINT,
            IN _tenant_id BIGINT,
            IN _current_time TIMESTAMP,
            IN _approval_note TEXT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            transfer_item JSONB,
            transfer_log JSONB
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_asset_item_id BIGINT;
            v_internal_req_item_id BIGINT;
            v_internal_req_id BIGINT;
            v_requisition_by BIGINT;
            v_department BIGINT;
            v_req_latitude VARCHAR;
            v_req_longitude VARCHAR;
            v_old_responsible_person BIGINT;
            v_old_department BIGINT;
            v_old_latitude VARCHAR;
            v_old_longitude VARCHAR;
            v_transfer_log_id BIGINT;
            v_transfer_item JSONB;
            v_transfer_log JSONB;
        BEGIN
            ----------------------------------------------------------------
            -- Validations
            ----------------------------------------------------------------
            IF _transfer_item_id IS NULL OR _transfer_item_id <= 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT,
                    'Invalid transfer item ID'::TEXT,
                    NULL::JSONB,
                    NULL::JSONB;
                RETURN;
            END IF;

            IF _approval_status NOT IN ('APPROVED', 'REJECTED') THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT,
                    'Invalid approval status. Must be APPROVED or REJECTED'::TEXT,
                    NULL::JSONB,
                    NULL::JSONB;
                RETURN;
            END IF;

            IF _tenant_id IS NULL OR _tenant_id <= 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT,
                    'Invalid tenant ID'::TEXT,
                    NULL::JSONB,
                    NULL::JSONB;
                RETURN;
            END IF;

            ----------------------------------------------------------------
            -- Check if transfer item exists
            ----------------------------------------------------------------
            SELECT 
                rbatri.asset_item_id,
                rbatri.based_internal_asset_requisitions_items
            INTO 
                v_asset_item_id,
                v_internal_req_item_id
            FROM requisition_based_asset_transfer_requwest_items rbatri
            WHERE rbatri.id = _transfer_item_id
            AND rbatri.tenant_id = _tenant_id
            AND rbatri.deleted_at IS NULL;

            IF v_asset_item_id IS NULL THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT,
                    'Transfer item not found'::TEXT,
                    NULL::JSONB,
                    NULL::JSONB;
                RETURN;
            END IF;

            ----------------------------------------------------------------
            -- Get current asset item values (before update)
            ----------------------------------------------------------------
            SELECT 
                ai.responsible_person,
                ai.department,
                ai.asset_location_latitude,
                ai.asset_location_longitude
            INTO 
                v_old_responsible_person,
                v_old_department,
                v_old_latitude,
                v_old_longitude
            FROM asset_items ai
            WHERE ai.id = v_asset_item_id;

            ----------------------------------------------------------------
            -- Get internal requisition details
            ----------------------------------------------------------------
            SELECT 
                iari.internal_asset_requisition_id,
                iari.department,
                iari.required_location_latitude,
                iari.required_location_longitude
            INTO 
                v_internal_req_id,
                v_department,
                v_req_latitude,
                v_req_longitude
            FROM internal_asset_requisitions_items iari
            WHERE iari.id = v_internal_req_item_id;

            -- Get requisition_by from internal_asset_requisitions
            SELECT iar.requisition_by
            INTO v_requisition_by
            FROM internal_asset_requisitions iar
            WHERE iar.id = v_internal_req_id;

            ----------------------------------------------------------------
            -- Update transfer item approval status
            ----------------------------------------------------------------
            UPDATE requisition_based_asset_transfer_requwest_items
            SET 
                asset_requester_approval_status = _approval_status,
                asset_requester_note = _approval_note,
                asset_requester_action_date = _current_time,
                updated_at = _current_time
            WHERE id = _transfer_item_id
            RETURNING to_jsonb(requisition_based_asset_transfer_requwest_items.*) INTO v_transfer_item;

            ----------------------------------------------------------------
            -- If APPROVED, update asset_item and create transfer log
            ----------------------------------------------------------------
            IF _approval_status = 'APPROVED' THEN
                -- Update asset_item with new values
                UPDATE asset_items
                SET 
                    responsible_person = v_requisition_by,
                    department = v_department,
                    asset_location_latitude = v_req_latitude,
                    asset_location_longitude = v_req_longitude,
                    updated_at = _current_time
                WHERE id = v_asset_item_id;

                -- Create transfer log
                INSERT INTO asset_transfer_logs (
                    asset_item_id,
                    transfer_request_item_id,
                    internal_requisition_id,
                    internal_requisition_item_id,
                    from_responsible_person,
                    from_department,
                    from_location_latitude,
                    from_location_longitude,
                    to_responsible_person,
                    to_department,
                    to_location_latitude,
                    to_location_longitude,
                    approval_status,
                    approval_note,
                    approval_date,
                    approved_by,
                    tenant_id,
                    created_at,
                    updated_at
                ) VALUES (
                    v_asset_item_id,
                    _transfer_item_id,
                    v_internal_req_id,
                    v_internal_req_item_id,
                    v_old_responsible_person,
                    v_old_department,
                    v_old_latitude,
                    v_old_longitude,
                    v_requisition_by,
                    v_department,
                    v_req_latitude,
                    v_req_longitude,
                    _approval_status,
                    _approval_note,
                    _current_time,
                    _approved_by,
                    _tenant_id,
                    _current_time,
                    _current_time
                ) RETURNING to_jsonb(asset_transfer_logs.*) INTO v_transfer_log;

                RETURN QUERY SELECT 
                    'SUCCESS'::TEXT,
                    'Transfer request approved and asset updated successfully'::TEXT,
                    v_transfer_item,
                    v_transfer_log;
            ELSE
                -- REJECTED - only create log without updating asset
                INSERT INTO asset_transfer_logs (
                    asset_item_id,
                    transfer_request_item_id,
                    internal_requisition_id,
                    internal_requisition_item_id,
                    from_responsible_person,
                    from_department,
                    from_location_latitude,
                    from_location_longitude,
                    to_responsible_person,
                    to_department,
                    to_location_latitude,
                    to_location_longitude,
                    approval_status,
                    approval_note,
                    approval_date,
                    approved_by,
                    tenant_id,
                    created_at,
                    updated_at
                ) VALUES (
                    v_asset_item_id,
                    _transfer_item_id,
                    v_internal_req_id,
                    v_internal_req_item_id,
                    v_old_responsible_person,
                    v_old_department,
                    v_old_latitude,
                    v_old_longitude,
                    v_requisition_by,
                    v_department,
                    v_req_latitude,
                    v_req_longitude,
                    _approval_status,
                    _approval_note,
                    _current_time,
                    _approved_by,
                    _tenant_id,
                    _current_time,
                    _current_time
                ) RETURNING to_jsonb(asset_transfer_logs.*) INTO v_transfer_log;

                RETURN QUERY SELECT 
                    'SUCCESS'::TEXT,
                    'Transfer request rejected'::TEXT,
                    v_transfer_item,
                    v_transfer_log;
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
        DB::unprepared('DROP FUNCTION IF EXISTS update_asset_transfer_approval_status CASCADE;');
    }
};