<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
            // SQL statement to create the stored procedure
            // $procedure = <<<SQL
            //     CREATE OR REPLACE PROCEDURE create_insert_asset_items_procedures(
            //         IN _registered_by_user_id BIGINT,
            //         IN _tenant_id BIGINT,
            //         IN _current_time TIMESTAMP WITH TIME ZONE, 
            //         IN _items JSON
            //     )
            //     LANGUAGE plpgsql
            //     AS $$
            //     DECLARE
            //         asset_item_id INTEGER;
            //         item JSON;
            //         error_message TEXT;
            //     BEGIN

            //         DROP TABLE IF EXISTS asset_item_add_response_from_store_procedure;
            //         CREATE TEMP TABLE asset_item_add_response_from_store_procedure (
            //             status TEXT,
            //             message TEXT,
            //             asset_item_id BIGINT DEFAULT 0
            //         );
                    
            //         -- Loop through the items JSON array and insert each item
            //         FOR item IN SELECT * FROM json_array_elements(_items)
            //             LOOP
            //                 INSERT INTO asset_items (
            //                     asset_id, 
            //                     model_number,
            //                     serial_number, 
            //                     thumbnail_image, 
            //                     qr_code, 
            //                     item_value,
            //                     item_documents, 
            //                     supplier,
            //                     purchase_order_number,
            //                     purchase_cost,
            //                     purchase_type,
            //                     other_purchase_details,
            //                     purchase_document,
            //                     received_condition,
            //                     warranty,
            //                     warranty_exparing_at,
            //                     insurance_number,
            //                     insurance_exparing_at,
            //                     insurance_document,
            //                     expected_life_time,
            //                     depreciation_value,
            //                     responsible_person,
            //                     asset_location_latitude,
            //                     asset_location_longitude,
            //                     department,
            //                     registered_by,
            //                     reading_parameters,
            //                     tenant_id,
            //                     created_at,
            //                     updated_at
            //                 )
            //                 VALUES (
            //                     (item->>'asset')::BIGINT,
            //                     item->>'modelNumber', 
            //                     item->>'serialNumber', 
            //                     (item->>'thumbnail_image')::JSON,
            //                     item->>'qr_code', 
            //                     item->>'itemValue', 
            //                     (item->>'itemDocument')::JSON,
            //                     (item->>'supplier')::BIGINT,
            //                     item->>'purchaseOrderNumber',
            //                     item->>'purchaseCost',
            //                     (item->>'purchaseType')::BIGINT,
            //                     item->>'otherPurchaseDetails',
            //                     (item->>'purchaseDocument')::JSON,
            //                     item->>'receivedCondition', 
            //                     item->>'warranty', 
            //                     item->>'warrantyExpirerDate', 
            //                     item->>'insuranceNumber', 
            //                     item->>'insuranceExpirerDate', 
            //                     (item->>'insuranceDocument')::JSON,
            //                     item->>'expectedLifeTime', 
            //                     item->>'estimatedDepreciationValue', 
            //                     item->>'responsiblePerson', 
            //                     item->>'asset_location_latitude', 
            //                     item->>'asset_location_longitude', 
            //                     (item->>'department')::BIGINT,
            //                     _registered_by_user_id,
            //                     (item->>'reading_parameters')::JSON,
            //                     _tenant_id,
            //                     _current_time,
            //                     _current_time
            //                 );
            //             END LOOP;
            //             -- Insert success message into the response table
            //             INSERT INTO asset_item_add_response_from_store_procedure (status, message, asset_item_id)
            //             VALUES ('SUCCESS', 'asset requisitions inserted successfully', asset_item_id);
            //             END IF;
            //     END;
            //     \$\$;
            //     SQL;
  
            // $procedure = <<<SQL
            // CREATE OR REPLACE PROCEDURE create_insert_asset_items_procedures(
            //     IN _registered_by_user_id BIGINT,
            //     IN _tenant_id BIGINT,
            //     IN _current_time TIMESTAMP WITH TIME ZONE, 
            //     IN _items JSON
            // )
            // LANGUAGE plpgsql
            // AS $$
            // DECLARE
            //     asset_item_id BIGINT; -- Captures the ID of each inserted item
            //     item JSON;           -- Iterates over each item in the input JSON array
            //     asset_item_ids TEXT[] := '{}'; -- Array to store all inserted IDs
            // BEGIN
    
            //     -- Drop and recreate the temporary response table
            //     DROP TABLE IF EXISTS asset_item_add_response_from_store_procedure;
            //     CREATE TEMP TABLE asset_item_add_response_from_store_procedure (
            //         status TEXT,
            //         message TEXT,
            //         asset_item_id TEXT, -- Change to TEXT to store concatenated IDs
            //         asset_requisition_reg_no VARCHAR(50)
            //     );
                
            //     -- Loop through the items JSON array and insert each item
            //     FOR item IN SELECT * FROM json_array_elements(_items)
            //     LOOP
            //         -- Insert item into asset_items table and get the generated ID
            //         INSERT INTO asset_items (
            //             asset_id, 
            //             model_number,
            //             serial_number, 
            //             thumbnail_image, 
            //             qr_code, 
            //             item_value,
            //             item_documents, 
            //             supplier,
            //             purchase_order_number,
            //             purchase_cost,
            //             purchase_type,
            //             other_purchase_details,
            //             purchase_document,
            //             received_condition,
            //             warranty,
            //             warranty_exparing_at,
            //             insurance_number,
            //             insurance_exparing_at,
            //             insurance_document,
            //             expected_life_time,
            //             depreciation_value,
            //             responsible_person,
            //             asset_location_latitude,
            //             asset_location_longitude,
            //             department,
            //             registered_by,
            //             reading_parameters,
            //             tenant_id,
            //             created_at,
            //             updated_at
            //         )
            //         VALUES (
            //             (item->>'asset')::BIGINT,
            //             item->>'modelNumber', 
            //             item->>'serialNumber', 
            //             (item->>'thumbnail_image')::JSON,
            //             item->>'qr_code', 
            //             (item->>'itemValue')::numeric,
            //             (item->>'itemDocument')::JSON,
            //             (item->>'supplier')::BIGINT,
            //             item->>'purchaseOrderNumber',
            //             (item->>'purchaseCost')::numeric,
            //             (item->>'purchaseType')::BIGINT,
            //             item->>'otherPurchaseDetails',
            //             (item->>'purchaseDocument')::JSON,
            //             item->>'receivedCondition', 
            //             item->>'warranty', 
            //             (item->>'warrantyExpirerDate')::date,
            //             item->>'insuranceNumber', 
            //             (item->>'insuranceExpirerDate')::date,
            //             (item->>'insuranceDocument')::JSON,
            //             item->>'expectedLifeTime', 
            //             (item->>'estimatedDepreciationValue')::numeric,
            //             (item->>'responsiblePerson')::BIGINT,
            //             item->>'asset_location_latitude', 
            //             item->>'asset_location_longitude', 
            //             (item->>'department')::BIGINT,
            //             _registered_by_user_id,
            //             (item->>'reading_parameters')::JSON,
            //             _tenant_id,
            //             _current_time,
            //             _current_time
            //         )
            //         RETURNING id INTO asset_item_id; -- Get the generated ID
                    
            //         -- Append the ID to the array
            //         asset_item_ids := array_append(asset_item_ids, asset_item_id::TEXT);
            //     END LOOP;
    
            //     -- After the loop, concatenate all IDs and insert the success message
            //     INSERT INTO asset_item_add_response_from_store_procedure (status, message, asset_item_id)
            //     VALUES (
            //         'SUCCESS', 
            //         'All asset items inserted successfully', 
            //         array_to_string(asset_item_ids, ', ')
            //     );
    
            // END;
            // $$;
            // SQL;
    
            // // Execute the SQL to create the stored procedure
            // DB::unprepared($procedure);

        // DB::unprepared(<<<SQL
        //     CREATE OR REPLACE FUNCTION create_insert_asset_items(
        //         IN _registered_by_user_id BIGINT,
        //         IN _tenant_id BIGINT,
        //         IN _current_time TIMESTAMP WITH TIME ZONE,
        //         IN _items JSON
        //     )
        //     RETURNS TABLE (
        //         status TEXT,
        //         message TEXT,
        //         asset_item_ids TEXT
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     DECLARE
        //         asset_item_id BIGINT; -- Captures the ID of each inserted item
        //         item JSON;           -- Iterates over each item in the input JSON array
        //         asset_item_ids_array TEXT[] := '{}'; -- Array to store all inserted IDs
        //     BEGIN
        //         -- Loop through the items JSON array and insert each item
        //         FOR item IN SELECT * FROM json_array_elements(_items)
        //         LOOP
        //             -- Insert item into asset_items table and get the generated ID
        //             INSERT INTO asset_items (
        //                 asset_id, 
        //                 model_number,
        //                 serial_number, 
        //                 thumbnail_image, 
        //                 qr_code, 
        //                 item_value,
        //                 item_documents, 
        //                 supplier,
        //                 purchase_order_number,
        //                 purchase_cost,
        //                 purchase_type,
        //                 other_purchase_details,
        //                 purchase_document,
        //                 received_condition,
        //                 warranty,
        //                 warranty_exparing_at,
        //                 insurance_number,
        //                 insurance_exparing_at,
        //                 insurance_document,
        //                 expected_life_time,
        //                 depreciation_value,
        //                 responsible_person,
        //                 asset_location_latitude,
        //                 asset_location_longitude,
        //                 department,
        //                 registered_by,
        //                 reading_parameters,
        //                 tenant_id,
        //                 created_at,
        //                 updated_at
        //             )
        //             VALUES (
        //                 (item->>'asset')::BIGINT,
        //                 item->>'modelNumber', 
        //                 item->>'serialNumber', 
        //                 (item->>'thumbnail_image')::JSON,
        //                 item->>'qr_code', 
        //                 (item->>'itemValue')::NUMERIC,
        //                 (item->>'itemDocument')::JSON,
        //                 (item->>'supplier')::BIGINT,
        //                 item->>'purchaseOrderNumber',
        //                 (item->>'purchaseCost')::NUMERIC,
        //                 (item->>'purchaseType')::BIGINT,
        //                 item->>'otherPurchaseDetails',
        //                 (item->>'purchaseDocument')::JSON,
        //                 item->>'receivedCondition', 
        //                 item->>'warranty', 
        //                 (item->>'warrantyExpirerDate')::DATE,
        //                 item->>'insuranceNumber', 
        //                 (item->>'insuranceExpirerDate')::DATE,
        //                 (item->>'insuranceDocument')::JSON,
        //                 item->>'expectedLifeTime', 
        //                 (item->>'estimatedDepreciationValue')::NUMERIC,
        //                 (item->>'responsiblePerson')::BIGINT,
        //                 item->>'asset_location_latitude', 
        //                 item->>'asset_location_longitude', 
        //                 (item->>'department')::BIGINT,
        //                 _registered_by_user_id,
        //                 (item->>'reading_parameters')::JSON,
        //                 _tenant_id,
        //                 _current_time,
        //                 _current_time
        //             )
        //             RETURNING id INTO asset_item_id; -- Get the generated ID

        //             -- Append the ID to the array
        //             asset_item_ids_array := array_append(asset_item_ids_array, asset_item_id::TEXT);
        //         END LOOP;

        //         -- Return the concatenated IDs and success message
        //         RETURN QUERY SELECT 
        //             'SUCCESS'::TEXT AS status,
        //             'All asset items inserted successfully'::TEXT AS message,
        //             array_to_string(asset_item_ids_array, ', ') AS asset_item_ids;
        //     END;
        //     $$;
        // SQL);

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION create_insert_asset_items(
                IN _registered_by_user_id BIGINT,
                IN _tenant_id BIGINT,
                IN _current_time TIMESTAMP WITH TIME ZONE,
                IN _items JSON
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                inserted_data JSON
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                item JSON;           -- Iterates over each item in the input JSON array
                inserted_row JSON;   -- Captures the inserted row as a JSON object
                inserted_data_array JSON[] := '{}'; -- Array to store all inserted rows as JSON objects
            BEGIN
                -- Loop through the items JSON array and insert each item
                FOR item IN SELECT * FROM json_array_elements(_items)
                LOOP
                    -- Insert item into asset_items table and get the generated row as JSON
                    INSERT INTO asset_items (
                        asset_id, 
                        model_number,
                        serial_number, 
                        thumbnail_image, 
                        qr_code, 
                        item_value,
                        item_documents, 
                        supplier,
                        purchase_order_number,
                        purchase_cost,
                        purchase_type,
                        other_purchase_details,
                        purchase_document,
                        received_condition,
                        warranty,
                        warranty_exparing_at,
                        insurance_number,
                        insurance_exparing_at,
                        insurance_document,
                        expected_life_time,
                        depreciation_value,
                        responsible_person,
                        asset_location_latitude,
                        asset_location_longitude,
                        department,
                        registered_by, 
                        asset_classification,
                        reading_parameters,
                        tenant_id,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        (item->>'asset')::BIGINT,
                        item->>'modelNumber', 
                        item->>'serialNumber', 
                        (item->>'thumbnail_image')::JSON,
                        item->>'qr_code', 
                        (item->>'itemValue')::NUMERIC,
                        (item->>'itemDocument')::JSON,
                        (item->>'supplier')::BIGINT,
                        item->>'purchaseOrderNumber',
                        (item->>'purchaseCost')::NUMERIC,
                        (item->>'purchaseType')::BIGINT,
                        item->>'otherPurchaseDetails',
                        (item->>'purchaseDocument')::JSON,
                        item->>'receivedCondition', 
                        item->>'warranty', 
                        (item->>'warrantyExpirerDate')::DATE,
                        item->>'insuranceNumber', 
                        (item->>'insuranceExpirerDate')::DATE,
                        (item->>'insuranceDocument')::JSON,
                        item->>'expectedLifeTime', 
                        (item->>'estimatedDepreciationValue')::NUMERIC,
                        (item->>'responsiblePerson')::BIGINT,
                        item->>'asset_location_latitude', 
                        item->>'asset_location_longitude', 
                        (item->>'department')::BIGINT,
                        _registered_by_user_id,
                        (item->>'asset_classification')::JSON,
                        (item->>'reading_parameters')::JSON,
                        _tenant_id,
                        _current_time,
                        _current_time
                    )
                    RETURNING row_to_json(asset_items) INTO inserted_row; -- Get the entire row as JSON
            
                    -- Append the JSON row to the array
                    inserted_data_array := array_append(inserted_data_array, inserted_row);
                END LOOP;
            
                -- Return the concatenated JSON array and success message
                RETURN QUERY SELECT 
                    'SUCCESS'::TEXT AS status,
                    'All asset items inserted successfully'::TEXT AS message,
                    json_agg(unnested_row) AS inserted_data
                FROM unnest(inserted_data_array) unnested_row; -- Use a different alias to avoid conflict
            END;
            $$;
        SQL);        

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS create_insert_asset_items_procedures');
    }
};