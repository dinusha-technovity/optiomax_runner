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
        // DB::unprepared(<<<SQL
        
        //         CREATE OR REPLACE FUNCTION update_asset_requisition_details(
        //             IN _id BIGINT,                           
        //             IN _requisition_id VARCHAR(255),         
        //             IN _requisition_status VARCHAR(255),     
        //             IN _tenant_id BIGINT,                    
        //             IN _current_time TIMESTAMP WITH TIME ZONE, 
        //             IN _assets JSONB                           
        //         )
        //         RETURNS TABLE (
        //             status TEXT,
        //             message TEXT,
        //             asset_requisition_id BIGINT
        //         )
        //         LANGUAGE plpgsql
        //         AS $$

        //         DECLARE
        //             asset JSONB;
        //             v_item_id BIGINT;
        //         BEGIN
        //             -- Update the requisition status in the asset_requisitions table
        //             UPDATE asset_requisitions
        //             SET requisition_status = _requisition_status,
        //                 updated_at = _current_time
        //             WHERE id = _id;

        //             -- Loop through the JSONB array of assets
        //             FOR asset IN SELECT * FROM jsonb_array_elements(_assets) LOOP
        //                 -- Extract item_id and convert to BIGINT
        //                 v_item_id := CASE 
        //                     WHEN asset->>'item_id' IS NULL OR asset->>'item_id' = 'null' OR asset->>'item_id' = '' THEN NULL 
        //                     ELSE (asset->>'item_id')::BIGINT 
        //                 END;

        //                 IF v_item_id IS NULL THEN
        //                     -- Insert new asset if item_id is NULL (new record)
        //                     INSERT INTO asset_requisitions_items (
        //                         asset_requisition_id, item_name, asset_type, quantity, budget, 
        //                         business_purpose, upgrade_or_new, period_status, period_from, period_to, 
        //                         availability_type, priority, required_date, organization, reason, 
        //                         business_impact, expected_conditions, suppliers, item_details, 
        //                         maintenance_kpi, service_support_kpi, consumables_kpi, files, tenant_id, 
        //                         created_at, updated_at
        //                     ) VALUES (
        //                         _id, asset->>'itemName', (asset->>'assetType')::INTEGER, (asset->>'quantity')::INTEGER, 
        //                         (asset->>'budget')::DECIMAL, asset->>'businessPurpose', asset->>'upgradeOrNew', 
        //                         (asset->>'periodStatus')::INTEGER, NULLIF(asset->>'periodFrom', 'null')::DATE, 
        //                         NULLIF(asset->>'periodTo', 'null')::DATE, (asset->>'availabilityType')::INTEGER, 
        //                         (asset->>'priority')::INTEGER, NULLIF(asset->>'requiredDate', 'null')::DATE, 
        //                         (asset->>'organization')::INTEGER, asset->>'reason', asset->>'businessImpact', 
        //                         asset->>'expected_conditions', asset->'suppliers', asset->'itemDetails', 
        //                         asset->'maintenanceKpi', asset->'serviceSupportKpi', asset->'consumablesKPI', 
        //                         asset->'files', _tenant_id, _current_time, _current_time
        //                     );
        //                 ELSE
        //                     -- Update existing asset if item_id is provided
        //                     UPDATE asset_requisitions_items
        //                     SET item_name = asset->>'itemName',
        //                         asset_type = (asset->>'assetType')::INTEGER,
        //                         quantity = (asset->>'quantity')::INTEGER,
        //                         budget = (asset->>'budget')::DECIMAL,
        //                         business_purpose = asset->>'businessPurpose',
        //                         upgrade_or_new = asset->>'upgradeOrNew',
        //                         period_status = (asset->>'periodStatus')::INTEGER,
        //                         period_from = NULLIF(asset->>'periodFrom', 'null')::DATE,
        //                         period_to = NULLIF(asset->>'periodTo', 'null')::DATE,
        //                         availability_type = (asset->>'availabilityType')::INTEGER,
        //                         priority = (asset->>'priority')::INTEGER,
        //                         required_date = NULLIF(asset->>'requiredDate', 'null')::DATE,
        //                         organization = (asset->>'organization')::INTEGER,
        //                         reason = asset->>'reason',
        //                         business_impact = asset->>'businessImpact',
        //                         expected_conditions = asset->>'expected_conditions',
        //                         suppliers = asset->'suppliers',
        //                         item_details = asset->'itemDetails',
        //                         maintenance_kpi = asset->'maintenanceKpi',
        //                         service_support_kpi = asset->'serviceSupportKpi',
        //                         consumables_kpi = asset->'consumablesKPI',
        //                         files = asset->'files',
        //                         updated_at = _current_time
        //                     WHERE id = v_item_id;
        //                 END IF;
        //             END LOOP;

        //             -- Return success response
        //             RETURN QUERY SELECT 'SUCCESS', 'Asset requisition updated successfully.', _id;
        //         END;
        //         $$;

        // SQL);
        DB::unprepared('DROP FUNCTION IF EXISTS update_asset_requisition_details CASCADE');

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
            BEGIN
                -- Update the requisition status in the asset_requisitions table
                UPDATE asset_requisitions
                SET requisition_status = _requisition_status,
                    updated_at = _current_time
                WHERE id = _id
                RETURNING to_jsonb(asset_requisitions.*) INTO asset_requisition_data;

                -- Loop through the JSONB array of assets
                FOR asset IN SELECT * FROM jsonb_array_elements(_assets) LOOP
                    -- Extract item_id and convert to BIGINT
                    v_item_id := CASE 
                        WHEN asset->>'item_id' IS NULL OR asset->>'item_id' = 'null' OR asset->>'item_id' = '' THEN NULL 
                        ELSE (asset->>'item_id')::BIGINT 
                    END;

                    IF v_item_id IS NULL THEN
                        -- Insert new asset if item_id is NULL (new record)
                        INSERT INTO asset_requisitions_items (
                            asset_requisition_id, 
                            item_name, 
                            asset_item_id,
                            quantity, 
                            budget, 
                            business_purpose, 
                            upgrade_or_new, 
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
                            files, 
                            suppliers, 
                            item_details, 
                            new_detail_type,
                            new_details,
                            expected_conditions,
                            asset_category,
                            asset_sub_category,
                            kpi_type,
                            new_kpi_details,
                            maintenance_kpi, 
                            service_support_kpi, 
                            consumables_kpi, 
                            description,
                            tenant_id, 
                            created_at, 
                            updated_at
                        ) VALUES (
                            _id,
                            asset->>'item_name',
                            NULLIF(asset->>'asset_item_id', '')::BIGINT,
                            NULLIF(asset->>'quantity', '')::INTEGER,
                            NULLIF(asset->>'budget', '')::NUMERIC,
                            asset->>'business_purpose',
                            asset->>'upgrade_or_new',
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
                            NULLIF(asset->>'files', '[]')::JSONB,
                            NULLIF(asset->>'suppliers', '[]')::JSONB,
                            NULLIF(asset->>'item_details', '[]')::JSONB,
                            asset->>'newDetailType',
                            asset->>'newDetails',
                            asset->>'expected_conditions',
                            NULLIF(asset->>'asset_category', '')::BIGINT,
                            NULLIF(asset->>'asset_sub_category', '')::BIGINT,
                            NULLIF(asset->>'kpiType', '')::BIGINT,
                            asset->>'newKpiDetails',
                            NULLIF(asset->>'maintenance_kpi', '[]')::JSONB,
                            NULLIF(asset->>'service_support_kpi', '[]')::JSONB,
                            NULLIF(asset->>'consumables_kpi', '[]')::JSONB,
                            asset->>'description',
                            _tenant_id,
                            _current_time,
                            _current_time
                        )
                         RETURNING to_jsonb(asset_requisitions_items.*) INTO requisition_items_data;
                    ELSE
                        -- Update existing asset if item_id is provided
                        UPDATE asset_requisitions_items
                        SET item_name = asset->>'item_name',
                            asset_item_id = NULLIF(asset->>'asset_item_id', '')::BIGINT,
                            quantity = NULLIF(asset->>'quantity', '')::INTEGER,
                            budget = NULLIF(asset->>'budget', '')::NUMERIC,
                            business_purpose = asset->>'business_purpose',
                            upgrade_or_new = asset->>'upgrade_or_new',
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
                            files = NULLIF(asset->>'files', '[]')::JSONB,
                            suppliers = NULLIF(asset->>'suppliers', '[]')::JSONB,
                            item_details = NULLIF(asset->>'item_details', '[]')::JSONB,
                            new_detail_type = asset->>'newDetailType',
                            new_details = asset->>'newDetails',
                            expected_conditions = asset->>'expected_conditions',
                            asset_category = NULLIF(asset->>'asset_category', '')::BIGINT,
                            asset_sub_category = NULLIF(asset->>'asset_sub_category', '')::BIGINT,
                            kpi_type = NULLIF(asset->>'kpiType', '')::BIGINT,
                            new_kpi_details = asset->>'newKpiDetails',
                            maintenance_kpi = NULLIF(asset->>'maintenance_kpi', '[]')::JSONB,
                            service_support_kpi = NULLIF(asset->>'service_support_kpi', '[]')::JSONB,
                            consumables_kpi = NULLIF(asset->>'consumables_kpi', '[]')::JSONB,
                            description = asset->>'description',
                            updated_at = _current_time
                        WHERE id = v_item_id
                        RETURNING to_jsonb(asset_requisitions_items.*) INTO requisition_items_data;
                    END IF;
                END LOOP;

                -- Collect all related requisition items
                SELECT jsonb_agg(to_jsonb(asset_requisitions_items.*)) 
                INTO requisition_items_data
                FROM asset_requisitions_items
                WHERE asset_requisition_id = _id;

                -- Return success response with updated requisition and related items
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