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
        DROP FUNCTION IF EXISTS submit_or_update_direct_asset_transfer_request(
            BIGINT, JSONB, VARCHAR, TEXT, TEXT, BIGINT, BIGINT, VARCHAR, TIMESTAMPTZ, VARCHAR
        );

        -- Function to submit or update direct asset transfer request
        CREATE OR REPLACE FUNCTION submit_or_update_direct_asset_transfer_request(
            p_transfer_request_id BIGINT DEFAULT NULL,
            p_assets JSONB DEFAULT NULL,
            p_transfer_type VARCHAR DEFAULT NULL,
            p_transfer_reason TEXT DEFAULT NULL,
            p_special_note TEXT DEFAULT NULL,
            p_tenant_id BIGINT DEFAULT NULL,
            p_requester_id BIGINT DEFAULT NULL,
            p_requester_name VARCHAR DEFAULT NULL,
            p_current_time TIMESTAMPTZ DEFAULT now(),
            p_prefix VARCHAR DEFAULT 'DATR'
        ) RETURNS TABLE (
            status TEXT,
            message TEXT,
            transfer_request_id BIGINT,
            transfer_request_number TEXT,
            transfer_data JSONB
        ) LANGUAGE plpgsql AS $$
        DECLARE
            v_transfer_request_id BIGINT;
            v_transfer_request_number TEXT;
            v_seq_val BIGINT;
            v_targeted_responsible_person BIGINT;
            v_asset JSONB;
            v_asset_item_id BIGINT;
            v_new_owner_id BIGINT;
            v_new_department_id BIGINT;
            v_new_location_lat DECIMAL(10,8);
            v_new_location_lng DECIMAL(11,8);
            v_new_location_address TEXT;
            v_current_owner_id BIGINT;
            v_current_department_id BIGINT;
            v_current_location_lat DECIMAL(10,8);
            v_current_location_lng DECIMAL(11,8);
            v_current_location_address TEXT;
            v_status VARCHAR := 'DRAFT';
            v_result_data JSONB;
        BEGIN
            -- Validation
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 
                    'Invalid tenant ID provided'::TEXT, 
                    NULL::BIGINT, 
                    NULL::TEXT,
                    NULL::JSONB;
                RETURN;
            END IF;

            IF p_requester_id IS NULL OR p_requester_id <= 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 
                    'Invalid requester ID provided'::TEXT, 
                    NULL::BIGINT, 
                    NULL::TEXT,
                    NULL::JSONB;
                RETURN;
            END IF;

            IF p_assets IS NULL OR jsonb_typeof(p_assets) != 'array' OR jsonb_array_length(p_assets) = 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 
                    'Assets cannot be empty'::TEXT, 
                    NULL::BIGINT, 
                    NULL::TEXT,
                    NULL::JSONB;
                RETURN;
            END IF;

            IF p_transfer_type IS NULL OR p_transfer_type = '' THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 
                    'Transfer type is required'::TEXT, 
                    NULL::BIGINT, 
                    NULL::TEXT,
                    NULL::JSONB;
                RETURN;
            END IF;

            IF p_transfer_reason IS NULL OR LENGTH(p_transfer_reason) < 20 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 
                    'Transfer reason must be at least 20 characters'::TEXT, 
                    NULL::BIGINT, 
                    NULL::TEXT,
                    NULL::JSONB;
                RETURN;
            END IF;

            -- Check if updating existing request
            IF p_transfer_request_id IS NOT NULL THEN
                -- Verify request exists and belongs to tenant
                SELECT id INTO v_transfer_request_id
                FROM direct_asset_transfer_requests
                WHERE id = p_transfer_request_id 
                    AND tenant_id = p_tenant_id
                    AND transfer_status IN ('DRAFT', 'PENDING')
                    AND deleted_at IS NULL;

                IF v_transfer_request_id IS NULL THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT, 
                        'Transfer request not found or cannot be modified'::TEXT, 
                        NULL::BIGINT, 
                        NULL::TEXT,
                        NULL::JSONB;
                    RETURN;
                END IF;

                -- Get existing transfer request number
                SELECT transfer_request_number INTO v_transfer_request_number
                FROM direct_asset_transfer_requests
                WHERE id = v_transfer_request_id;

                -- Extract targeted responsible person from first asset if assets provided
                IF p_assets IS NOT NULL THEN
                    SELECT NULLIF((jsonb_array_elements(p_assets)->>'new_owner_id'), '')::BIGINT
                    INTO v_targeted_responsible_person
                    LIMIT 1;
                END IF;

                -- Update main request
                UPDATE direct_asset_transfer_requests
                SET
                    targeted_responsible_person = COALESCE(v_targeted_responsible_person, targeted_responsible_person),
                    transfer_type = COALESCE(p_transfer_type, transfer_type),
                    transfer_reason = COALESCE(p_transfer_reason, transfer_reason),
                    special_note = COALESCE(p_special_note, special_note),
                    updated_at = p_current_time
                WHERE id = v_transfer_request_id;

                -- Delete existing items if assets provided
                IF p_assets IS NOT NULL THEN
                    DELETE FROM direct_asset_transfer_request_items
                    WHERE direct_asset_transfer_request_id = v_transfer_request_id;
                END IF;

                -- Log activity for update
                IF p_requester_id IS NOT NULL AND p_requester_name IS NOT NULL THEN
                    PERFORM log_activity(
                        'direct_asset_transfer.updated',
                        'Transfer request updated by ' || p_requester_name || ': ' || v_transfer_request_number,
                        'direct_asset_transfer_request',
                        v_transfer_request_id,
                        'user',
                        p_requester_id,
                        jsonb_build_object(
                            'transfer_request_id', v_transfer_request_id,
                            'transfer_type', p_transfer_type,
                            'assets_count', jsonb_array_length(p_assets)
                        ),
                        p_tenant_id
                    );
                END IF;
            ELSE
                -- Create new request
                SELECT nextval('direct_asset_transfer_request_number_seq') INTO v_seq_val;
                v_transfer_request_number := p_prefix || '-' || to_char(p_current_time, 'YYYYMM') || '-' || LPAD(v_seq_val::TEXT, 4, '0');

                -- Determine status (DRAFT or PENDING)
                v_status := 'DRAFT';

                -- Extract targeted responsible person from first asset (new owner ID)
                SELECT NULLIF((jsonb_array_elements(p_assets)->>'new_owner_id'), '')::BIGINT
                INTO v_targeted_responsible_person
                LIMIT 1;

                INSERT INTO direct_asset_transfer_requests(
                    transfer_request_number,
                    targeted_responsible_person,
                    requester_id,
                    requested_date,
                    transfer_type,
                    transfer_status,
                    work_flow_request,
                    transfer_reason,
                    special_note,
                    is_cancelled,
                    isactive,
                    tenant_id,
                    created_at,
                    updated_at
                ) VALUES (
                    v_transfer_request_number,
                    v_targeted_responsible_person,
                    p_requester_id,
                    p_current_time,
                    p_transfer_type,
                    v_status,
                    NULL,
                    p_transfer_reason,
                    p_special_note,
                    false,
                    true,
                    p_tenant_id,
                    p_current_time,
                    p_current_time
                ) RETURNING id INTO v_transfer_request_id;

                -- Log activity for creation
                IF p_requester_id IS NOT NULL AND p_requester_name IS NOT NULL THEN
                    PERFORM log_activity(
                        'direct_asset_transfer.created',
                        'Transfer request created by ' || p_requester_name || ': ' || v_transfer_request_number,
                        'direct_asset_transfer_request',
                        v_transfer_request_id,
                        'user',
                        p_requester_id,
                        jsonb_build_object(
                            'transfer_request_id', v_transfer_request_id,
                            'transfer_request_number', v_transfer_request_number,
                            'transfer_type', p_transfer_type,
                            'assets_count', jsonb_array_length(p_assets)
                        ),
                        p_tenant_id
                    );
                END IF;
            END IF;

            -- Insert/Update asset items
            FOR v_asset IN SELECT * FROM jsonb_array_elements(p_assets)
            LOOP
                v_asset_item_id := (v_asset->>'asset_item_id')::BIGINT;
                
                -- Extract new values from asset object
                v_new_owner_id := NULLIF((v_asset->>'new_owner_id'), '')::BIGINT;
                v_new_department_id := NULLIF((v_asset->>'new_department_id'), '')::BIGINT;
                v_new_location_lat := NULLIF((v_asset->>'new_location_latitude'), '')::DECIMAL(10,8);
                v_new_location_lng := NULLIF((v_asset->>'new_location_longitude'), '')::DECIMAL(11,8);
                v_new_location_address := v_asset->>'new_location_address';

                -- Get current values from asset_items table
                SELECT 
                    ai.responsible_person,
                    ai.department,
                    ai.asset_location_latitude,
                    ai.asset_location_longitude,
                    NULL
                INTO 
                    v_current_owner_id,
                    v_current_department_id,
                    v_current_location_lat,
                    v_current_location_lng,
                    v_current_location_address
                FROM asset_items ai
                WHERE ai.id = v_asset_item_id 
                    AND ai.tenant_id = p_tenant_id
                    AND ai.deleted_at IS NULL;

                IF v_current_owner_id IS NULL THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT, 
                        'Asset item not found: ' || v_asset_item_id::TEXT,
                        NULL::BIGINT, 
                        NULL::TEXT,
                        NULL::JSONB;
                    RETURN;
                END IF;

                -- Insert transfer request item
                INSERT INTO direct_asset_transfer_request_items(
                    direct_asset_transfer_request_id,
                    asset_item_id,
                    current_owner_id,
                    current_department_id,
                    current_location_latitude,
                    current_location_longitude,
                    current_location_address,
                    new_owner_id,
                    new_department_id,
                    new_location_latitude,
                    new_location_longitude,
                    new_location_address,
                    is_reset_current_employee_schedule,
                    is_reset_current_availability_schedule,
                    is_transferred,
                    isactive,
                    tenant_id,
                    created_at,
                    updated_at
                ) VALUES (
                    v_transfer_request_id,
                    v_asset_item_id,
                    v_current_owner_id,
                    v_current_department_id,
                    v_current_location_lat,
                    v_current_location_lng,
                    v_current_location_address,
                    v_new_owner_id,
                    v_new_department_id,
                    v_new_location_lat,
                    v_new_location_lng,
                    v_new_location_address,
                    COALESCE((v_asset->>'is_reset_current_employee_schedule')::BOOLEAN, false),
                    COALESCE((v_asset->>'is_reset_current_availability_schedule')::BOOLEAN, false),
                    false,
                    true,
                    p_tenant_id,
                    p_current_time,
                    p_current_time
                );
            END LOOP;

            -- Build result data
            SELECT jsonb_build_object(
                'id', v_transfer_request_id,
                'transfer_request_number', v_transfer_request_number,
                'transfer_type', p_transfer_type,
                'transfer_status', v_status,
                'assets_count', jsonb_array_length(p_assets)
            ) INTO v_result_data;

            RETURN QUERY SELECT 
                'SUCCESS'::TEXT,
                'Transfer request saved successfully'::TEXT,
                v_transfer_request_id,
                v_transfer_request_number,
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
        DROP FUNCTION IF EXISTS submit_or_update_direct_asset_transfer_request(
            BIGINT, JSONB, VARCHAR, TEXT, TEXT, BIGINT, BIGINT, BIGINT, VARCHAR, TIMESTAMPTZ, VARCHAR
        );
        SQL);
    }
};