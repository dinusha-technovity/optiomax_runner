<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
           CREATE OR REPLACE FUNCTION create_or_update_asset_items(
                IN _registered_by_user_id BIGINT,
                IN _tenant_id BIGINT,
                IN _current_time TIMESTAMP WITH TIME ZONE,
                IN _items JSON
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                item_id BIGINT,
                inserted_data JSON
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                item JSON;           -- Iterates over each item in the input JSON array
                inserted_row JSON;   -- Captures the inserted/updated row as a JSON object
                inserted_data_array JSON[] := '{}'; -- Array to store all inserted/updated rows as JSON objects
                current_item_id BIGINT; -- Stores the ID of the inserted or updated item
                existing_item_id BIGINT; -- Stores ID of existing item with same serial number
                duplicate_serial_number TEXT; -- Stores the duplicate serial number if found
                consumable_item JSON; -- Iterates over each consumable item in the asset_consumables array
            BEGIN
                -- First, check for duplicate serial numbers in the input
                FOR item IN SELECT * FROM json_array_elements(_items)
                LOOP
                    -- Skip check if this is an update operation (asset_item_id exists)
                    IF (item->>'asset_item_id') IS NULL THEN
                        -- Check if serial number exists for this tenant
                        SELECT id INTO existing_item_id 
                        FROM asset_items 
                        WHERE serial_number = item->>'serialNumber' 
                        AND tenant_id = _tenant_id
                        LIMIT 1;
                        
                        IF existing_item_id IS NOT NULL THEN
                            duplicate_serial_number := item->>'serialNumber';
                            RETURN QUERY SELECT
                                'ERROR'::TEXT AS status,
                                format('Duplicate serial number: %s already exists for item ID: %s', 
                                    duplicate_serial_number, existing_item_id)::TEXT AS message,
                                existing_item_id AS item_id,
                                NULL::JSON AS inserted_data;
                            RETURN;
                        END IF;
                    END IF;
                END LOOP;

                -- If no duplicates, proceed with insert/update operations
                FOR item IN SELECT * FROM json_array_elements(_items)
                LOOP
                    -- Check if asset_item_id exists for update, otherwise insert
                    IF (item->>'asset_item_id') IS NOT NULL THEN
                        -- Update existing asset item
                        UPDATE asset_items
                        SET
                            asset_id = (item->>'asset_id')::BIGINT,
                            model_number = item->>'modelNumber',
                            serial_number = item->>'serialNumber',
                            thumbnail_image = (item->>'thumbnailImages')::JSON,
                            qr_code = item->>'qr_code',
                            item_value = (item->>'itemValue')::NUMERIC,
                            item_value_currency_id = (item->>'itemValueCurrId')::BIGINT,
                            item_documents = (item->>'itemDocumentIds')::JSON,
                            supplier = (item->>'supplier')::BIGINT,
                            purchase_order_number = item->>'purchaseOrderNumber',
                            purchase_cost = (item->>'purchaseCost')::NUMERIC,
                            purchase_cost_currency_id = (item->>'purchaseCostCurrId')::BIGINT,
                            purchase_type = (item->>'purchaseType')::BIGINT,
                            other_purchase_details = item->>'otherPurchaseDetails',
                            purchase_document = (item->>'purchaseDocumentIds')::JSON,
                            received_condition = item->>'receivedConditionId',
                            warranty = item->>'warranty',
                            warrenty_condition_type_id = (item->>'warrantyConditionTypeId')::BIGINT,
                            warranty_exparing_at = (item->>'warrantyExpirerDate')::DATE,
                            warrenty_usage_name = item->>'warrantyUsageName',
                            warranty_usage_value = item->>'warrantyUsageValue',
                            insurance_number = item->>'insuranceNumber',
                            insurance_exparing_at = (item->>'insuranceExpirerDate')::DATE,
                            insurance_document = (item->>'insuranceDocumentIds')::JSON,
                            expected_life_time = item->>'expectedLifeTime',
                            depreciation_value = (item->>'estimatedDepreciationValue')::NUMERIC,
                            depreciation_method = (item->>'depreciationMethod')::BIGINT,
                            manufacturer = item->>'Manufacturer',
                            responsible_person = (item->>'responsiblePersonId')::BIGINT,
                            asset_location_latitude = (item->'location'->>'latitude')::TEXT,
                            asset_location_longitude = (item->'location'->>'longitude')::TEXT,
                            department = (item->>'departmentId')::BIGINT,
                            registered_by = _registered_by_user_id,
                            asset_classification = json_build_object(
                                'asset_category_id', (item->>'asset_category_id')::BIGINT,
                                'asset_sub_category_id', (item->>'asset_sub_category_id')::BIGINT,
                                'asset_tags', (item->>'assetTags')::JSON
                            ),
                            reading_parameters = (item->>'reading_parameters')::JSON,
                            tenant_id = _tenant_id,
                            consumables_kpi = (item->>'consumables_kpi')::JSONB,
                            maintenance_kpi = (item->>'maintenance_kpi')::JSONB,
                            service_support_kpi = (item->>'service_support_kpi')::JSONB,
                            asset_requisition_item_id = (item->>'asset_requisition_item_id')::BIGINT,
                            asset_requisition_id = (item->>'asset_requisition_id')::BIGINT,
                            procurement_id = (item->>'procurement_id')::BIGINT,

                            salvage_value = (item->>'salvage_value')::NUMERIC,
                            total_estimated_units = (item->>'total_estimated_units')::NUMERIC,
                            depreciation_start_date = (item->>'depreciationStartDate')::DATE,
                            expected_life_time_unit = (item->>'expectedLifeTimeUnit')::BIGINT,
                            decline_rate = (item->>'declineRate')::NUMERIC,


                            updated_at = _current_time
                        WHERE id = (item->>'asset_item_id')::BIGINT
                        RETURNING id, row_to_json(asset_items) INTO current_item_id, inserted_row;
                        
                        -- If no rows were updated, raise an exception
                        IF NOT FOUND THEN
                            RETURN QUERY SELECT
                                'ERROR'::TEXT AS status,
                                format('Asset item with id % not found', (item->>'asset_item_id')::BIGINT)::TEXT AS message,
                                NULL::BIGINT AS item_id,
                                NULL::JSON AS inserted_data;
                            CONTINUE;
                        END IF;
                        
                        -- Process consumables for updated asset item
                        IF (item->>'asset_consumables') IS NOT NULL THEN
                            DECLARE
                                new_consumable_ids BIGINT[] := '{}';
                                current_consumable_id BIGINT;
                            BEGIN
                                -- Collect all new consumable IDs from the input
                                FOR consumable_item IN SELECT * FROM json_array_elements((item->>'asset_consumables')::JSON)
                                LOOP
                                    new_consumable_ids := array_append(new_consumable_ids, (consumable_item->>'id')::BIGINT);
                                END LOOP;
                                
                                -- Mark as deleted any consumables not in the new list
                                UPDATE asset_item_consumables
                                SET 
                                    is_active = false,
                                    deleted_at = _current_time,
                                    updated_at = _current_time
                                WHERE 
                                    asset_item_id = (item->>'asset_item_id')::BIGINT 
                                    AND tenant_id = _tenant_id
                                    AND consumable_id <> ALL(new_consumable_ids)
                                    AND is_active = true;
                                
                                -- For each consumable in the input
                                FOR consumable_item IN SELECT * FROM json_array_elements((item->>'asset_consumables')::JSON)
                                LOOP
                                    current_consumable_id := (consumable_item->>'id')::BIGINT;
                                    
                                    -- Check if this relationship already exists
                                    IF EXISTS (
                                        SELECT 1 FROM asset_item_consumables 
                                        WHERE asset_item_id = (item->>'asset_item_id')::BIGINT
                                        AND consumable_id = current_consumable_id
                                        AND tenant_id = _tenant_id
                                    ) THEN
                                        -- Reactivate if it was previously deactivated
                                        UPDATE asset_item_consumables
                                        SET 
                                            is_active = true,
                                            deleted_at = NULL,
                                            updated_at = _current_time
                                        WHERE 
                                            asset_item_id = (item->>'asset_item_id')::BIGINT
                                            AND consumable_id = current_consumable_id
                                            AND tenant_id = _tenant_id;
                                    ELSE
                                        -- Insert new relationship
                                        INSERT INTO asset_item_consumables (
                                            asset_item_id, 
                                            consumable_id, 
                                            tenant_id,
                                            is_active,
                                            created_at,
                                            updated_at
                                        ) VALUES (
                                            (item->>'asset_item_id')::BIGINT,
                                            current_consumable_id,
                                            _tenant_id,
                                            true,
                                            _current_time,
                                            _current_time
                                        );
                                    END IF;
                                END LOOP;
                            END;
                        END IF;
                    ELSE
                        -- Insert new asset item
                        BEGIN
                            INSERT INTO asset_items (
                                asset_id,
                                model_number,
                                serial_number,
                                asset_tag,
                                thumbnail_image,
                                qr_code,
                                item_value,
                                item_value_currency_id,
                                item_documents,
                                supplier,
                                purchase_order_number,
                                purchase_cost,
                                purchase_cost_currency_id,
                                purchase_type,
                                other_purchase_details,
                                purchase_document,
                                received_condition,
                                warranty,
                                warrenty_condition_type_id,
                                warranty_exparing_at,
                                warrenty_usage_name,
                                warranty_usage_value,
                                insurance_number,
                                insurance_exparing_at,
                                insurance_document,
                                expected_life_time,
                                depreciation_value,
                                depreciation_method,
                                manufacturer,
                                responsible_person,
                                asset_location_latitude,
                                asset_location_longitude,
                                department,
                                registered_by,
                                asset_classification,
                                reading_parameters,
                                tenant_id,
                                consumables_kpi,
                                maintenance_kpi,
                                service_support_kpi,
                                asset_requisition_item_id,
                                asset_requisition_id,
                                procurement_id,

                                salvage_value,
                                total_estimated_units,
                                depreciation_start_date,
                                expected_life_time_unit,
                                decline_rate,

                                created_at,
                                updated_at
                            )
                            VALUES (
                                (item->>'asset_id')::BIGINT,
                                item->>'modelNumber',
                                item->>'serialNumber',
                                CASE 
                                    WHEN (item->>'asset_tag') IS NULL OR (item->>'asset_tag') = '' 
                                    THEN generate_asset_item_tag_id() 
                                    ELSE item->>'asset_tag' 
                                END,
                                (item->>'thumbnailImages')::JSON,
                                item->>'qr_code',
                                (item->>'itemValue')::NUMERIC,
                                (item->>'itemValueCurrId')::BIGINT,
                                (item->>'itemDocumentIds')::JSON,
                                (item->>'supplier')::BIGINT,
                                item->>'purchaseOrderNumber',
                                (item->>'purchaseCost')::NUMERIC,
                                (item->>'purchaseCostCurrId')::BIGINT,
                                (item->>'purchaseType')::BIGINT,
                                item->>'otherPurchaseDetails',
                                (item->>'purchaseDocumentIds')::JSON,
                                item->>'receivedConditionId',
                                item->>'warranty',
                                (item->>'warrantyConditionTypeId')::BIGINT,
                                (item->>'warrantyExpirerDate')::DATE,
                                item->>'warrantyUsageName',
                                item->>'warrantyUsageValue',
                                item->>'insuranceNumber',
                                (item->>'insuranceExpirerDate')::DATE,
                                (item->>'insuranceDocumentIds')::JSON,
                                item->>'expectedLifeTime',
                                (item->>'estimatedDepreciationValue')::NUMERIC,
                                (item->>'depreciationMethod')::BIGINT,
                                item->>'Manufacturer',
                                (item->>'responsiblePersonId')::BIGINT,
                                (item->'location'->>'latitude')::TEXT,
                                (item->'location'->>'longitude')::TEXT,
                                (item->>'departmentId')::BIGINT,
                                _registered_by_user_id,
                                json_build_object(
                                    'asset_category_id', (item->>'asset_category_id')::BIGINT,
                                    'asset_sub_category_id', (item->>'asset_sub_category_id')::BIGINT,
                                    'asset_tags', (item->>'assetTags')::JSON
                                ),
                                (item->>'reading_parameters')::JSON,
                                _tenant_id,
                                (item->>'consumables_kpi')::JSONB,
                                (item->>'maintenance_kpi')::JSONB,
                                (item->>'service_support_kpi')::JSONB,
                                (item->>'asset_requisition_item_id')::BIGINT,
                                (item->>'asset_requisition_id')::BIGINT,
                                (item->>'procurement_id')::BIGINT,

                                (item->>'salvage_value')::NUMERIC,
                                (item->>'total_estimated_units')::NUMERIC,
                                (item->>'depreciationStartDate')::DATE,
                                (item->>'expectedLifeTimeUnit')::BIGINT,
                                (item->>'declineRate')::NUMERIC,

                                _current_time,
                                _current_time
                            )
                            RETURNING id, row_to_json(asset_items) INTO current_item_id, inserted_row;
                            
                            -- Process consumables for newly created asset item
                            IF (item->>'asset_consumables') IS NOT NULL THEN
                                -- For new asset items, simply insert all consumable relationships
                                -- No need to check for existing ones as this is a new asset item
                                FOR consumable_item IN SELECT * FROM json_array_elements((item->>'asset_consumables')::JSON)
                                LOOP
                                    INSERT INTO asset_item_consumables (
                                        asset_item_id, 
                                        consumable_id, 
                                        tenant_id,
                                        is_active,
                                        created_at,
                                        updated_at
                                    ) VALUES (
                                        current_item_id,
                                        (consumable_item->>'id')::BIGINT,
                                        _tenant_id,
                                        true,
                                        _current_time,
                                        _current_time
                                    );
                                END LOOP;
                            END IF;
                        EXCEPTION WHEN unique_violation THEN
                            -- Handle case where another process inserted the same serial number
                            SELECT id INTO existing_item_id 
                            FROM asset_items 
                            WHERE serial_number = item->>'serialNumber' 
                            AND tenant_id = _tenant_id
                            LIMIT 1;


                            
                            RETURN QUERY SELECT
                                'ERROR'::TEXT AS status,
                                format('Duplicate serial number: %s already exists for item ID: %s', 
                                    item->>'serialNumber', existing_item_id)::TEXT AS message,
                                existing_item_id AS item_id,
                                NULL::JSON AS inserted_data;
                            CONTINUE;
                        END;
                    END IF;

                    -- Append the JSON row to the array
                    inserted_data_array := array_append(inserted_data_array, inserted_row);
                END LOOP;

                -- Return the result with status, message, item_id, and inserted data
                RETURN QUERY SELECT
                    'SUCCESS'::TEXT AS status,
                CASE
                    WHEN (item->>'asset_item_id') IS NOT NULL THEN 'Asset item updated successfully'
                    ELSE 'Asset item inserted successfully'
                END AS message,
                    current_item_id AS item_id,
                    json_agg(unnested_row) AS inserted_data
                FROM unnest(inserted_data_array) unnested_row;
            EXCEPTION
                WHEN OTHERS THEN
                    RETURN QUERY SELECT
                        'ERROR'::TEXT AS status,
                        format('Error: %s', SQLERRM)::TEXT AS message,
                        NULL::BIGINT AS item_id,
                        NULL::JSON AS inserted_data;
            END;

            $$;
         SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS create_or_update_asset_items( BIGINT, BIGINT,TIME,JSON);');
    }
};
