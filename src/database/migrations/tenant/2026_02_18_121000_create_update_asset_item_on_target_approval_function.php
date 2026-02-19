<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Create function to update asset items on target person approval
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- Function to update asset item and create transfer log when target person approves
        CREATE OR REPLACE FUNCTION update_asset_item_on_target_approval(
            p_transfer_item_id BIGINT,
            p_target_person_id BIGINT,
            p_tenant_id BIGINT,
            p_current_time TIMESTAMPTZ DEFAULT NOW()
        ) RETURNS JSONB LANGUAGE plpgsql AS $$
        DECLARE
            v_asset_item_id BIGINT;
            v_transfer_request_id BIGINT;
            v_transfer_request_number VARCHAR(255);
            v_transfer_type VARCHAR(255);
            
            -- Old values (from asset_items)
            v_old_responsible_person BIGINT;
            v_old_department BIGINT;
            v_old_location_lat VARCHAR(255);
            v_old_location_lng VARCHAR(255);
            
            -- New values (from transfer request item)
            v_new_owner_id BIGINT;
            v_new_department_id BIGINT;
            v_new_location_lat VARCHAR(255);
            v_new_location_lng VARCHAR(255);
            
            -- For logging
            v_old_owner_name VARCHAR(255);
            v_new_owner_name VARCHAR(255);
            v_old_dept_name TEXT;
            v_new_dept_name TEXT;
            v_asset_tag VARCHAR(255);
        BEGIN
            -- Get transfer item details
            SELECT 
                datri.asset_item_id,
                datri.direct_asset_transfer_request_id,
                datri.new_owner_id,
                datri.new_department_id,
                datri.new_location_latitude,
                datri.new_location_longitude,
                datr.transfer_request_number,
                datr.transfer_type
            INTO 
                v_asset_item_id,
                v_transfer_request_id,
                v_new_owner_id,
                v_new_department_id,
                v_new_location_lat,
                v_new_location_lng,
                v_transfer_request_number,
                v_transfer_type
            FROM direct_asset_transfer_request_items datri
            INNER JOIN direct_asset_transfer_requests datr ON datr.id = datri.direct_asset_transfer_request_id
            WHERE datri.id = p_transfer_item_id
                AND datri.deleted_at IS NULL
                AND datr.tenant_id = p_tenant_id;

            IF v_asset_item_id IS NULL THEN
                RETURN jsonb_build_object(
                    'status', 'FAILURE',
                    'message', 'Transfer item not found'
                );
            END IF;

            -- Get current asset item details (old values)
            SELECT 
                ai.responsible_person,
                ai.department,
                ai.asset_location_latitude,
                ai.asset_location_longitude,
                ai.asset_tag,
                u.name AS old_owner_name,
                org.data->>'organizationName' AS old_dept_name
            INTO
                v_old_responsible_person,
                v_old_department,
                v_old_location_lat,
                v_old_location_lng,
                v_asset_tag,
                v_old_owner_name,
                v_old_dept_name
            FROM asset_items ai
            LEFT JOIN users u ON u.id = ai.responsible_person
            LEFT JOIN organization org ON org.id = ai.department
            WHERE ai.id = v_asset_item_id
                AND ai.tenant_id = p_tenant_id
                AND ai.deleted_at IS NULL;

            -- Get new owner and department names for logging
            SELECT u.name, org.data->>'organizationName'
            INTO v_new_owner_name, v_new_dept_name
            FROM users u
            LEFT JOIN organization org ON org.id = v_new_department_id
            WHERE u.id = v_new_owner_id;

            -- Update asset_items table based on transfer type
            IF v_transfer_type = 'OWNER_LOCATION_DEPARTMENT' THEN
                -- Update owner, location, and department
                UPDATE asset_items
                SET 
                    responsible_person = v_new_owner_id,
                    asset_location_latitude = v_new_location_lat,
                    asset_location_longitude = v_new_location_lng,
                    department = v_new_department_id,
                    updated_at = p_current_time
                WHERE id = v_asset_item_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;
                    
            ELSIF v_transfer_type = 'OWNER_ONLY' THEN
                -- Update owner only
                UPDATE asset_items
                SET 
                    responsible_person = v_new_owner_id,
                    updated_at = p_current_time
                WHERE id = v_asset_item_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;
                    
            ELSIF v_transfer_type = 'LOCATION_DEPARTMENT' THEN
                -- Update location and department (owner already updated by workflow approval)
                UPDATE asset_items
                SET 
                    asset_location_latitude = v_new_location_lat,
                    asset_location_longitude = v_new_location_lng,
                    department = v_new_department_id,
                    updated_at = p_current_time
                WHERE id = v_asset_item_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;
                    
            ELSIF v_transfer_type = 'LOCATION_ONLY' THEN
                -- Update location only (already handled by workflow approval, but ensure consistency)
                UPDATE asset_items
                SET 
                    asset_location_latitude = v_new_location_lat,
                    asset_location_longitude = v_new_location_lng,
                    updated_at = p_current_time
                WHERE id = v_asset_item_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;
                    
            ELSIF v_transfer_type = 'DEPARTMENT_ONLY' THEN
                -- Update department only (already handled by workflow approval, but ensure consistency)
                UPDATE asset_items
                SET 
                    department = v_new_department_id,
                    updated_at = p_current_time
                WHERE id = v_asset_item_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;
            END IF;

            -- Create entry in asset_transfer_logs table
            INSERT INTO asset_transfer_logs (
                asset_item_id,
                direct_transfer_request_item_id,
                internal_requisition_id,
                internal_requisition_item_id,
                transfer_request_item_id,
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
                p_transfer_item_id,
                NULL,  -- Not applicable for direct transfers
                NULL,  -- Not applicable for direct transfers
                NULL,  -- Not applicable for direct transfers
                v_old_responsible_person,
                v_old_department,
                v_old_location_lat,
                v_old_location_lng,
                v_new_owner_id,
                v_new_department_id,
                v_new_location_lat,
                v_new_location_lng,
                'APPROVED',
                'Target person approved transfer',
                p_current_time,
                p_target_person_id,
                p_tenant_id,
                p_current_time,
                p_current_time
            );

            -- Log activity for the asset item update
            BEGIN
                PERFORM log_activity(
                    'direct_transfer.asset_updated_on_target_approval',      -- p_log_name
                    'Asset item updated after target person approval: ' || v_transfer_request_number,  -- p_description
                    'asset_items',                                           -- p_subject_type
                    v_asset_item_id,                                         -- p_subject_id
                    'user',                                                  -- p_causer_type
                    p_target_person_id,                                      -- p_causer_id
                    jsonb_build_object(
                        'transfer_request_id', v_transfer_request_id,
                        'transfer_request_number', v_transfer_request_number,
                        'transfer_type', v_transfer_type,
                        'transfer_item_id', p_transfer_item_id,
                        'asset_item_id', v_asset_item_id,
                        'asset_tag', v_asset_tag,
                        'old_values', jsonb_build_object(
                            'responsible_person_id', v_old_responsible_person,
                            'responsible_person_name', v_old_owner_name,
                            'department_id', v_old_department,
                            'department_name', v_old_dept_name,
                            'location', jsonb_build_object(
                                'latitude', v_old_location_lat,
                                'longitude', v_old_location_lng
                            )
                        ),
                        'new_values', jsonb_build_object(
                            'responsible_person_id', v_new_owner_id,
                            'responsible_person_name', v_new_owner_name,
                            'department_id', v_new_department_id,
                            'department_name', v_new_dept_name,
                            'location', jsonb_build_object(
                                'latitude', v_new_location_lat,
                                'longitude', v_new_location_lng
                            )
                        )
                    ),                                                       -- p_properties
                    p_tenant_id                                              -- p_tenant_id
                );
            EXCEPTION WHEN OTHERS THEN
                RAISE NOTICE 'Log activity failed for asset item update: %', SQLERRM;
            END;

            RETURN jsonb_build_object(
                'status', 'SUCCESS',
                'message', 'Asset item updated and transfer logged successfully',
                'asset_item_id', v_asset_item_id,
                'transfer_type', v_transfer_type,
                'transfer_request_number', v_transfer_request_number
            );

        EXCEPTION WHEN OTHERS THEN
            RETURN jsonb_build_object(
                'status', 'ERROR',
                'message', SQLERRM
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
        DB::unprepared('DROP FUNCTION IF EXISTS update_asset_item_on_target_approval(BIGINT, BIGINT, BIGINT, TIMESTAMPTZ) CASCADE;');
    }
};
