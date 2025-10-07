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
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION update_asset_requisition_details(
                IN _id BIGINT,                           -- Asset requisition ID
                IN _requisition_id VARCHAR(255),         -- Requisition identifier
                IN _requisition_status VARCHAR(255),     -- Status of the requisition
                IN _tenant_id BIGINT,                    -- Tenant ID
                IN _current_time TIMESTAMP WITH TIME ZONE, -- Current timestamp
                IN _assets JSONB                         -- JSONB array of assets
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                asset_requisition JSONB,
                requisition_items JSONB
            )
            LANGUAGE plpgsql
            AS $$
           DECLARE
                asset JSONB;
                v_item_id BIGINT;
                asset_requisition_data JSONB;
                requisition_items_data JSONB := '[]'::JSONB;
                existing_item_ids BIGINT[];
                provided_item_ids BIGINT[];
                newly_created_item_ids BIGINT[] := ARRAY[]::BIGINT[];
            BEGIN
                -- Update the requisition status
                UPDATE asset_requisitions
                SET requisition_status = _requisition_status,
                    updated_at = _current_time
                WHERE id = _id
                RETURNING to_jsonb(asset_requisitions.*) INTO asset_requisition_data;

                -- Get all existing item IDs for this requisition
                SELECT array_agg(id) INTO existing_item_ids
                FROM asset_requisitions_items
                WHERE asset_requisition_id = _id AND deleted_at IS NULL;

                -- Get all provided item IDs from the assets array
                SELECT array_agg((asset_item->>'item_id')::BIGINT) INTO provided_item_ids
                FROM jsonb_array_elements(_assets) asset_item
                WHERE asset_item->>'item_id' IS NOT NULL 
                AND asset_item->>'item_id' != 'null' 
                AND asset_item->>'item_id' != '';

                -- Loop through assets JSONB
                FOR asset IN SELECT * FROM jsonb_array_elements(_assets) LOOP
                    v_item_id := CASE 
                        WHEN asset->>'item_id' IS NULL OR asset->>'item_id' = 'null' OR asset->>'item_id' = '' THEN NULL
                        ELSE (asset->>'item_id')::BIGINT
                    END;

                    IF v_item_id IS NULL THEN
                        -- Insert new item
                        INSERT INTO asset_requisitions_items (
                            asset_requisition_id, 
                            item_name, 
                            asset_item_id,
                            quantity, 
                            budget, 
                            budget_currency,
                            business_purpose, 
                            acquisition_type,
                            period_status, 
                            period_from, 
                            period_to, 
                            period, 
                            availability_type, 
                            priority, 
                            required_date, 
                            organization, 
                            reason, 
                            business_impact, 
                            suppliers, 
                            files, 
                            item_details, 
                            expected_conditions,
                            maintenance_kpi, 
                            service_support_kpi, 
                            consumables_kpi, 
                            asset_category,
                            asset_sub_category,
                            description,
                            expected_depreciation_value,
                            tenant_id, 
                            created_at, 
                            updated_at
                        ) VALUES (
                            _id,
                            asset->>'item_name',
                            NULLIF(asset->>'asset_item_id', '')::BIGINT,
                            NULLIF(asset->>'quantity', '')::INTEGER,
                            NULLIF(asset->>'budget', '')::NUMERIC,
                            NULLIF(asset->>'budget_currency', '')::BIGINT,
                            asset->>'business_purpose',
                            NULLIF(asset->>'asset_acquisition_type', '')::BIGINT,
                            NULLIF(asset->>'period_status', '')::BIGINT,
                            NULLIF(asset->>'period_from', 'null')::DATE,
                            NULLIF(asset->>'period_to', 'null')::DATE,
                            asset->>'period',
                            NULLIF(asset->>'availability_type', '')::BIGINT,
                            NULLIF(asset->>'priority', '')::BIGINT,
                            NULLIF(asset->>'required_date', 'null')::DATE,
                            NULLIF(asset->>'organization', '')::BIGINT,
                            asset->>'reason',
                            asset->>'business_impact',
                            NULLIF(asset->>'suppliers', '[]')::JSONB,
                            NULLIF(asset->>'files', '[]')::JSONB,
                            NULLIF(asset->>'item_details', '[]')::JSONB,
                            asset->>'expected_conditions',
                            NULLIF(asset->>'maintenance_kpi', '[]')::JSONB,
                            NULLIF(asset->>'service_support_kpi', '[]')::JSONB,
                            NULLIF(asset->>'consumables_kpi', '[]')::JSONB,
                            NULLIF(asset->>'asset_category', '')::BIGINT,
                            NULLIF(asset->>'asset_sub_category', '')::BIGINT,
                            asset->>'description',
                            NULLIF(asset->>'expected_depreciation_value', '')::INTEGER,
                            _tenant_id,
                            _current_time,
                            _current_time
                        )
                        RETURNING id INTO v_item_id;
                        
                        -- Add the newly created item ID to the array
                        newly_created_item_ids := array_append(newly_created_item_ids, v_item_id);
                    ELSE
                        -- Update existing item
                        UPDATE asset_requisitions_items
                    SET 
                        item_name = asset->>'item_name',
                        asset_item_id = NULLIF(asset->>'asset_item_id', '')::BIGINT,
                        quantity = NULLIF(asset->>'quantity', '')::INTEGER,
                        budget = NULLIF(asset->>'budget', '')::NUMERIC,
                        budget_currency = NULLIF(asset->>'budget_currency', '')::BIGINT,
                        business_purpose = asset->>'business_purpose',
                        acquisition_type = NULLIF(asset->>'asset_acquisition_type', '')::BIGINT,
                        period_status = NULLIF(asset->>'period_status', '')::BIGINT,
                        period_from = NULLIF(asset->>'period_from', 'null')::DATE,
                        period_to = NULLIF(asset->>'period_to', 'null')::DATE,
                        period = asset->>'period',
                        availability_type = NULLIF(asset->>'availability_type', '')::BIGINT,
                        priority = NULLIF(asset->>'priority', '')::BIGINT,
                        required_date = NULLIF(asset->>'required_date', 'null')::DATE,
                        organization = NULLIF(asset->>'organization', '')::BIGINT,
                        reason = asset->>'reason',
                        business_impact = asset->>'business_impact',
                        suppliers = NULLIF(asset->>'suppliers', '[]')::JSONB,
                        files = NULLIF(asset->>'files', '[]')::JSONB,
                        item_details = NULLIF(asset->>'item_details', '[]')::JSONB,
                        expected_conditions = asset->>'expected_conditions',
                        maintenance_kpi = NULLIF(asset->>'maintenance_kpi', '[]')::JSONB,
                        service_support_kpi = NULLIF(asset->>'service_support_kpi', '[]')::JSONB,
                        consumables_kpi = NULLIF(asset->>'consumables_kpi', '[]')::JSONB,
                        asset_category = NULLIF(asset->>'asset_category', '')::BIGINT,
                        asset_sub_category = NULLIF(asset->>'asset_sub_category', '')::BIGINT,
                        description = asset->>'description',
                        expected_depreciation_value = NULLIF(asset->>'expected_depreciation_value', '')::INTEGER,
                        updated_at = _current_time
                    WHERE id = v_item_id
                        RETURNING to_jsonb(asset_requisitions_items.*) INTO requisition_items_data;
                    END IF;
                END LOOP;

                -- Soft delete existing items that are not in the provided assets array or newly created
                UPDATE asset_requisitions_items
                SET deleted_at = _current_time,
                    updated_at = _current_time
                WHERE asset_requisition_id = _id
                AND deleted_at IS NULL
                AND (provided_item_ids IS NULL OR id != ALL(provided_item_ids))
                AND (newly_created_item_ids IS NULL OR id != ALL(newly_created_item_ids));

                -- Fetch all related items (excluding soft deleted ones)
                SELECT jsonb_agg(to_jsonb(asset_requisitions_items.*))
                INTO requisition_items_data
                FROM asset_requisitions_items
                WHERE asset_requisition_id = _id AND deleted_at IS NULL;

                -- Return final result
                RETURN QUERY SELECT 'SUCCESS', 'Asset requisition updated successfully.', asset_requisition_data, requisition_items_data;
            END;

            $$;
        SQL);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS update_asset_requisition_details CASCADE');
    }
};