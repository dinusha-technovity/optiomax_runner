<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Create function to update asset items location and department for approved transfers
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- Function to update asset items location and department after transfer approval
        CREATE OR REPLACE FUNCTION update_asset_items_location_department_on_transfer_approval(
            p_transfer_request_id BIGINT,
            p_tenant_id BIGINT,
            p_current_time TIMESTAMP DEFAULT NOW()
        ) RETURNS JSONB LANGUAGE plpgsql AS $$
        DECLARE
            v_transfer_type VARCHAR(255);
            v_transfer_request_number VARCHAR(255);
            v_updated_count INT := 0;
            v_item RECORD;
            v_asset_item_id BIGINT;
            v_asset_tag VARCHAR(255);
            v_old_location_lat VARCHAR(255);
            v_old_location_lng VARCHAR(255);
            v_old_department_id BIGINT;
            v_old_department_name TEXT;
            v_new_department_name TEXT;
        BEGIN
            -- Get transfer type and request number
            SELECT transfer_type, transfer_request_number
            INTO v_transfer_type, v_transfer_request_number
            FROM direct_asset_transfer_requests
            WHERE id = p_transfer_request_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

            IF v_transfer_type IS NULL THEN
                RETURN jsonb_build_object(
                    'status', 'FAILURE',
                    'message', 'Transfer request not found'
                );
            END IF;

            -- Only proceed if transfer type requires location/department update without owner change
            IF v_transfer_type NOT IN ('OWNER_LOCATION_DEPARTMENT', 'OWNER_ONLY') THEN
                
                -- Loop through all items in the transfer request
                FOR v_item IN
                    SELECT 
                        datri.id,
                        datri.asset_item_id,
                        datri.new_department_id,
                        datri.new_location_latitude,
                        datri.new_location_longitude,
                        ai.asset_tag,
                        ai.asset_location_latitude AS old_location_lat,
                        ai.asset_location_longitude AS old_location_lng,
                        ai.department AS old_department_id,
                        old_org.data->>'organizationName' AS old_department_name,
                        new_org.data->>'organizationName' AS new_department_name
                    FROM direct_asset_transfer_request_items datri
                    INNER JOIN asset_items ai ON ai.id = datri.asset_item_id
                    LEFT JOIN organization old_org ON old_org.id = ai.department
                    LEFT JOIN organization new_org ON new_org.id = datri.new_department_id
                    WHERE datri.direct_asset_transfer_request_id = p_transfer_request_id
                        AND datri.deleted_at IS NULL
                        AND ai.deleted_at IS NULL
                LOOP
                    -- Update asset_items table based on transfer type
                    IF v_transfer_type = 'LOCATION_DEPARTMENT' THEN
                        -- Update both location and department
                        UPDATE asset_items
                        SET 
                            asset_location_latitude = v_item.new_location_latitude,
                            asset_location_longitude = v_item.new_location_longitude,
                            department = v_item.new_department_id,
                            updated_at = p_current_time
                        WHERE id = v_item.asset_item_id
                            AND tenant_id = p_tenant_id
                            AND deleted_at IS NULL;
                        
                    ELSIF v_transfer_type = 'LOCATION_ONLY' THEN
                        -- Update location only
                        UPDATE asset_items
                        SET 
                            asset_location_latitude = v_item.new_location_latitude,
                            asset_location_longitude = v_item.new_location_longitude,
                            updated_at = p_current_time
                        WHERE id = v_item.asset_item_id
                            AND tenant_id = p_tenant_id
                            AND deleted_at IS NULL;
                        
                    ELSIF v_transfer_type = 'DEPARTMENT_ONLY' THEN
                        -- Update department only
                        UPDATE asset_items
                        SET 
                            department = v_item.new_department_id,
                            updated_at = p_current_time
                        WHERE id = v_item.asset_item_id
                            AND tenant_id = p_tenant_id
                            AND deleted_at IS NULL;
                    END IF;

                    -- Log activity for the update
                    BEGIN
                        PERFORM log_activity(
                            CASE 
                                WHEN v_transfer_type = 'LOCATION_DEPARTMENT' THEN 'asset_transfer.location_department_updated'
                                WHEN v_transfer_type = 'LOCATION_ONLY' THEN 'asset_transfer.location_updated'
                                WHEN v_transfer_type = 'DEPARTMENT_ONLY' THEN 'asset_transfer.department_updated'
                            END,                                                    -- p_log_name
                            CASE 
                                WHEN v_transfer_type = 'LOCATION_DEPARTMENT' THEN 
                                    'Asset location and department updated via transfer approval: ' || v_transfer_request_number
                                WHEN v_transfer_type = 'LOCATION_ONLY' THEN 
                                    'Asset location updated via transfer approval: ' || v_transfer_request_number
                                WHEN v_transfer_type = 'DEPARTMENT_ONLY' THEN 
                                    'Asset department updated via transfer approval: ' || v_transfer_request_number
                            END,                                                    -- p_description
                            'asset_items',                                          -- p_subject_type
                            v_item.asset_item_id,                                   -- p_subject_id
                            'system',                                               -- p_causer_type
                            0,                                                      -- p_causer_id
                            jsonb_build_object(
                                'transfer_request_id', p_transfer_request_id,
                                'transfer_request_number', v_transfer_request_number,
                                'transfer_type', v_transfer_type,
                                'asset_item_id', v_item.asset_item_id,
                                'asset_tag', v_item.asset_tag,
                                'old_location', jsonb_build_object(
                                    'latitude', v_item.old_location_lat,
                                    'longitude', v_item.old_location_lng
                                ),
                                'new_location', jsonb_build_object(
                                    'latitude', v_item.new_location_latitude,
                                    'longitude', v_item.new_location_longitude
                                ),
                                'old_department_id', v_item.old_department_id,
                                'old_department_name', v_item.old_department_name,
                                'new_department_id', v_item.new_department_id,
                                'new_department_name', v_item.new_department_name
                            ),                                                      -- p_properties
                            p_tenant_id                                             -- p_tenant_id
                        );
                    EXCEPTION WHEN OTHERS THEN
                        RAISE NOTICE 'Log activity failed for asset item %: %', v_item.asset_item_id, SQLERRM;
                    END;

                    v_updated_count := v_updated_count + 1;
                END LOOP;

                RETURN jsonb_build_object(
                    'status', 'SUCCESS',
                    'message', 'Asset items updated successfully',
                    'updated_count', v_updated_count,
                    'transfer_type', v_transfer_type,
                    'transfer_request_number', v_transfer_request_number
                );
            ELSE
                -- Transfer type doesn't require automatic location/department update
                RETURN jsonb_build_object(
                    'status', 'SKIPPED',
                    'message', 'Transfer type does not require automatic asset item update',
                    'transfer_type', v_transfer_type
                );
            END IF;

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
        DB::unprepared('DROP FUNCTION IF EXISTS update_asset_items_location_department_on_transfer_approval(BIGINT, BIGINT, TIMESTAMP) CASCADE;');
    }
};
