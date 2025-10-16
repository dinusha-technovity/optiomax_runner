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
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'bulk_insert_asset_items_with_relationships'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            -- Function to handle complex relationship lookups and creation for asset items
            CREATE OR REPLACE FUNCTION bulk_insert_asset_items_with_relationships(
                IN _registered_by_user_id BIGINT,
                IN _tenant_id BIGINT,
                IN _import_job_id BIGINT,
                IN _current_time TIMESTAMP WITH TIME ZONE,
                IN _items JSON,
                IN _batch_size INTEGER DEFAULT 1000
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                total_processed BIGINT,
                total_inserted BIGINT,
                total_updated BIGINT,
                total_errors BIGINT,
                batch_results JSON,
                error_details JSON
            )
            LANGUAGE plpgsql
            AS \$\$
            DECLARE
                item JSON;
                processed_count BIGINT := 0;
                inserted_count BIGINT := 0;
                updated_count BIGINT := 0;
                error_count BIGINT := 0;
                error_details_array JSON[] := '{}';
                batch_results_array JSON[] := '{}';
                
                -- Lookup variables
                asset_id_val BIGINT;
                asset_type_id_val BIGINT;                           -- Added asset type variable
                item_value_currency_id_val BIGINT;
                purchase_cost_currency_id_val BIGINT;
                purchase_type_id_val BIGINT;
                supplier_id_val BIGINT;
                warranty_condition_type_id_val BIGINT;
                time_period_id_val BIGINT;
                depreciation_method_id_val BIGINT;
                responsible_person_id_val BIGINT;
                department_id_val BIGINT;
                asset_category_id_val BIGINT;
                asset_sub_category_id_val BIGINT;
                
                supplier_data_json JSON;
                current_item_id BIGINT;
                existing_item_id BIGINT;

                curr_val INT;
                new_supplier_reg_no VARCHAR(50);
            BEGIN
                -- Process each item
                FOR item IN SELECT * FROM json_array_elements(_items)
                LOOP
                    processed_count := processed_count + 1;
                    
                    BEGIN
                        -- Reset lookup variables
                        asset_id_val := NULL;
                        asset_type_id_val := NULL;                  -- Reset asset type variable
                        item_value_currency_id_val := NULL;
                        purchase_cost_currency_id_val := NULL;
                        purchase_type_id_val := NULL;
                        supplier_id_val := NULL;
                        warranty_condition_type_id_val := NULL;
                        time_period_id_val := NULL;
                        depreciation_method_id_val := NULL;
                        responsible_person_id_val := NULL;
                        department_id_val := NULL;
                        asset_category_id_val := NULL;
                        asset_sub_category_id_val := NULL;

                        -- 0. Handle Asset Type lookup first
                        IF (item->>'asset_type_name') IS NOT NULL AND (item->>'asset_type_name') != '' THEN
                            SELECT id INTO asset_type_id_val 
                            FROM assets_types 
                            WHERE LOWER(name) = LOWER(item->>'asset_type_name')
                            LIMIT 1;
                            
                            -- If asset type not found, use default (Tangible assets)
                            IF asset_type_id_val IS NULL THEN
                                SELECT id INTO asset_type_id_val 
                                FROM assets_types 
                                WHERE LOWER(name) = LOWER('Tangible assets')
                                LIMIT 1;
                            END IF;
                        ELSE
                            -- Default to Tangible assets if not specified
                            SELECT id INTO asset_type_id_val 
                            FROM assets_types 
                            WHERE LOWER(name) = LOWER('Tangible assets')
                            LIMIT 1;
                        END IF;

                        -- 1. Handle Asset Name lookup/creation
                        IF (item->>'asset_name') IS NOT NULL AND (item->>'asset_name') != '' THEN
                            -- First, get or create asset categories
                            IF (item->>'asset_category_name') IS NOT NULL AND (item->>'asset_category_name') != '' THEN
                                SELECT id INTO asset_category_id_val 
                                FROM asset_categories 
                                WHERE LOWER(name) = LOWER(item->>'asset_category_name') 
                                AND tenant_id = _tenant_id
                                AND deleted_at IS NULL
                                AND isactive = true
                                LIMIT 1;
                                
                                IF asset_category_id_val IS NULL THEN
                                    INSERT INTO asset_categories (name, tenant_id, isactive, created_at, updated_at, assets_type, created_by, is_created_from_imported_csv, if_imported_jobs_id) 
                                    VALUES (item->>'asset_category_name', _tenant_id, true, _current_time, _current_time, COALESCE(asset_type_id_val, 1), _registered_by_user_id, true, _import_job_id)
                                    RETURNING id INTO asset_category_id_val;
                                END IF;
                            END IF;

                            -- Get or create asset sub-categories
                            IF (item->>'asset_sub_category_name') IS NOT NULL AND (item->>'asset_sub_category_name') != '' 
                               AND asset_category_id_val IS NOT NULL THEN
                                SELECT id INTO asset_sub_category_id_val 
                                FROM asset_sub_categories 
                                WHERE LOWER(name) = LOWER(item->>'asset_sub_category_name') 
                                AND asset_category_id = asset_category_id_val
                                AND tenant_id = _tenant_id
                                AND deleted_at IS NULL
                                AND isactive = true
                                LIMIT 1;
                                
                                IF asset_sub_category_id_val IS NULL THEN
                                    INSERT INTO asset_sub_categories (asset_category_id, name, tenant_id, isactive, created_at, updated_at, created_by, is_created_from_imported_csv, if_imported_jobs_id)
                                    VALUES (asset_category_id_val, item->>'asset_sub_category_name', _tenant_id, true, _current_time, _current_time, _registered_by_user_id, true, _import_job_id)
                                    RETURNING id INTO asset_sub_category_id_val;
                                END IF;
                            END IF;

                            -- Get or create asset with asset type
                            SELECT id INTO asset_id_val 
                            FROM assets 
                            WHERE LOWER(name) = LOWER(item->>'asset_name') 
                            AND tenant_id = _tenant_id
                            AND isactive = true
                            LIMIT 1;
                            
                            IF asset_id_val IS NULL THEN
                                INSERT INTO assets (
                                    name, category, sub_category, 
                                    asset_description, registered_by, tenant_id, 
                                    isactive, created_at, updated_at, is_created_from_imported_csv, if_imported_jobs_id
                                )
                                VALUES (
                                    item->>'asset_name',
                                    COALESCE(asset_category_id_val, 1),
                                    COALESCE(asset_sub_category_id_val, 1),
                                    'Auto-created from CSV import: ' || (item->>'model_number'),
                                    _registered_by_user_id,
                                    _tenant_id,
                                    true,
                                    _current_time,
                                    _current_time,
                                    true, 
                                    _import_job_id
                                )
                                RETURNING id INTO asset_id_val;
                            END IF;
                        ELSE
                            -- Use the provided asset_id directly if asset_name is not provided
                            asset_id_val := (item->>'asset_id')::BIGINT;
                        END IF;

                        -- 2. Handle Currency lookups
                        IF (item->>'item_value_currency_code') IS NOT NULL AND (item->>'item_value_currency_code') != '' THEN
                            SELECT id INTO item_value_currency_id_val 
                            FROM currencies 
                            WHERE UPPER(code) = UPPER(item->>'item_value_currency_code')
                            AND is_active = true
                            LIMIT 1;
                        END IF;

                        IF (item->>'purchase_cost_currency_code') IS NOT NULL AND (item->>'purchase_cost_currency_code') != '' THEN
                            SELECT id INTO purchase_cost_currency_id_val 
                            FROM currencies 
                            WHERE UPPER(code) = UPPER(item->>'purchase_cost_currency_code')
                            AND is_active = true
                            LIMIT 1;
                        ELSE
                            -- Use same currency as item value if not specified
                            purchase_cost_currency_id_val := item_value_currency_id_val;
                        END IF;

                        -- 3. Handle Purchase Type lookup
                        IF (item->>'purchase_type_name') IS NOT NULL AND (item->>'purchase_type_name') != '' THEN
                            SELECT id INTO purchase_type_id_val 
                            FROM asset_requisition_availability_types 
                            WHERE LOWER(name) = LOWER(item->>'purchase_type_name')
                            AND isactive = true
                            LIMIT 1;
                        END IF;

                        -- 4. Handle Supplier lookup/creation with status management
                        IF (item->>'supplier_data') IS NOT NULL AND (item->>'supplier_data') != '' THEN
                            supplier_data_json := (item->>'supplier_data')::JSON;
                            
                            -- First check if supplier exists by email (any status, active or inactive)
                            SELECT id INTO supplier_id_val 
                            FROM suppliers 
                            WHERE email = supplier_data_json->>'email'
                            AND tenant_id = _tenant_id
                            LIMIT 1;
                            
                            IF supplier_id_val IS NOT NULL THEN
                                -- Supplier exists, update it to ensure it's active and approved
                                UPDATE suppliers SET
                                    name = COALESCE(supplier_data_json->>'name', name),
                                    supplier_type = COALESCE(supplier_data_json->>'type', supplier_type),
                                    supplier_reg_status = 'APPROVED',
                                    isactive = true,
                                    deleted_at = NULL,
                                    updated_at = _current_time
                                WHERE id = supplier_id_val;
                            ELSE
                                -- Supplier doesn't exist, create new one
                                SELECT nextval('supplier_id_seq') INTO curr_val;
                                new_supplier_reg_no := 'SUPPLIER-' || LPAD(curr_val::TEXT, 4, '0');

                                INSERT INTO suppliers (
                                    name, email, supplier_type, supplier_reg_status,
                                    supplier_reg_no, tenant_id, isactive, created_at, updated_at, created_by, is_created_from_imported_csv, if_imported_jobs_id
                                )
                                VALUES (
                                    supplier_data_json->>'name',
                                    supplier_data_json->>'email',
                                    COALESCE(supplier_data_json->>'type', 'Individual'),
                                    'APPROVED',
                                    new_supplier_reg_no,
                                    _tenant_id,
                                    true,
                                    _current_time,
                                    _current_time,
                                    _registered_by_user_id, 
                                    true, 
                                    _import_job_id
                                )
                                RETURNING id INTO supplier_id_val;
                            END IF;
                        END IF;

                        -- 5. Handle Warranty Condition Type lookup
                        IF (item->>'warranty_condition_type_name') IS NOT NULL AND (item->>'warranty_condition_type_name') != '' THEN
                            SELECT id INTO warranty_condition_type_id_val 
                            FROM warrenty_condition_types 
                            WHERE LOWER(name) = LOWER(item->>'warranty_condition_type_name')
                            LIMIT 1;
                        END IF;

                        -- 6. Handle Time Period Unit lookup
                        IF (item->>'expected_life_time_unit_name') IS NOT NULL AND (item->>'expected_life_time_unit_name') != '' THEN
                            SELECT id INTO time_period_id_val 
                            FROM time_period_entries 
                            WHERE LOWER(name) = LOWER(item->>'expected_life_time_unit_name')
                            LIMIT 1;
                        END IF;

                        -- 7. Handle Depreciation Method lookup
                        IF (item->>'depreciation_method_name') IS NOT NULL AND (item->>'depreciation_method_name') != '' THEN
                            SELECT id INTO depreciation_method_id_val 
                            FROM depreciation_method_table 
                            WHERE LOWER(name) = LOWER(item->>'depreciation_method_name')
                            LIMIT 1;
                        END IF;

                        -- 8. Handle Responsible Person lookup
                        IF (item->>'responsible_person_name') IS NOT NULL AND (item->>'responsible_person_name') != '' THEN
                            SELECT id INTO responsible_person_id_val 
                            FROM users 
                            WHERE LOWER(name) = LOWER(item->>'responsible_person_name')
                            AND tenant_id = _tenant_id
                            LIMIT 1;
                        END IF;

                        -- 9. Handle Department lookup
                        IF (item->>'department_name') IS NOT NULL AND (item->>'department_name') != '' THEN
                            SELECT id INTO department_id_val 
                            FROM organization 
                            WHERE LOWER(data->>'organizationName') = LOWER(item->>'department_name')
                            AND tenant_id = _tenant_id
                            LIMIT 1;
                        END IF;

                        -- Check if serial number already exists (for new items only)
                        IF (item->>'asset_item_id') IS NULL THEN
                            SELECT id INTO existing_item_id 
                            FROM asset_items 
                            WHERE serial_number = item->>'serial_number' 
                            AND tenant_id = _tenant_id
                            LIMIT 1;
                            
                            IF existing_item_id IS NOT NULL THEN
                                error_count := error_count + 1;
                                error_details_array := array_append(error_details_array, 
                                    json_build_object(
                                        'row', processed_count,
                                        'serial_number', item->>'serial_number',
                                        'error', format('Serial number already exists with ID: %s', existing_item_id)
                                    )
                                );
                                CONTINUE;
                            END IF;
                        END IF;

                        -- Insert or update the asset item
                        IF (item->>'asset_item_id') IS NOT NULL THEN
                            -- Update existing item
                            UPDATE asset_items SET
                                asset_id = asset_id_val,
                                model_number = item->>'model_number',
                                serial_number = item->>'serial_number',
                                asset_tag = COALESCE(item->>'asset_tag', asset_tag),
                                qr_code = item->>'qr_code',
                                item_value = COALESCE((item->>'item_value')::NUMERIC, item_value),
                                item_value_currency_id = COALESCE(item_value_currency_id_val, item_value_currency_id),
                                purchase_cost = COALESCE((item->>'purchase_cost')::NUMERIC, purchase_cost),
                                purchase_cost_currency_id = COALESCE(purchase_cost_currency_id_val, purchase_cost_currency_id),
                                purchase_type = COALESCE(purchase_type_id_val, purchase_type),
                                purchase_order_number = COALESCE(item->>'purchase_order_number', purchase_order_number),
                                other_purchase_details = COALESCE(item->>'other_purchase_details', other_purchase_details),
                                supplier = COALESCE(supplier_id_val, supplier),
                                salvage_value = COALESCE((item->>'salvage_value')::NUMERIC, salvage_value),
                                warranty = COALESCE(item->>'warranty', warranty),
                                warrenty_condition_type_id = COALESCE(warranty_condition_type_id_val, warrenty_condition_type_id),
                                warranty_exparing_at = COALESCE((item->>'warranty_expiry_date')::DATE, warranty_exparing_at),
                                warrenty_usage_name = COALESCE(item->>'warranty_usage_name', warrenty_usage_name),
                                warranty_usage_value = COALESCE(item->>'warranty_usage_value', warranty_usage_value),
                                insurance_number = COALESCE(item->>'insurance_number', insurance_number),
                                insurance_exparing_at = COALESCE((item->>'insurance_expiry_date')::DATE, insurance_exparing_at),
                                expected_life_time = COALESCE(item->>'expected_life_time', expected_life_time),
                                expected_life_time_unit = COALESCE(time_period_id_val, expected_life_time_unit),
                                depreciation_value = COALESCE((item->>'depreciation_value')::NUMERIC, depreciation_value),
                                depreciation_method = COALESCE(depreciation_method_id_val, depreciation_method),
                                depreciation_start_date = COALESCE((item->>'depreciation_start_date')::DATE, depreciation_start_date),
                                decline_rate = COALESCE((item->>'decline_rate')::NUMERIC, decline_rate),
                                total_estimated_units = COALESCE((item->>'total_estimated_units')::NUMERIC, total_estimated_units),
                                manufacturer = COALESCE(item->>'manufacturer', manufacturer),
                                responsible_person = COALESCE(responsible_person_id_val, responsible_person),
                                department = COALESCE(department_id_val, department),
                                asset_location_latitude = COALESCE((item->>'latitude')::TEXT, asset_location_latitude),
                                asset_location_longitude = COALESCE((item->>'longitude')::TEXT, asset_location_longitude),
                                received_condition = COALESCE(item->>'received_condition_id', received_condition),
                                asset_classification = json_build_object(
                                    'asset_category_id', COALESCE(asset_category_id_val, (asset_classification->>'asset_category_id')::BIGINT),
                                    'asset_sub_category_id', COALESCE(asset_sub_category_id_val, (asset_classification->>'asset_sub_category_id')::BIGINT),
                                    'asset_tags', COALESCE((item->>'asset_tags')::JSON, asset_classification->'asset_tags')
                                ),
                                updated_at = _current_time,
                                is_created_from_imported_csv = true,
                                if_imported_jobs_id = _import_job_id
                            WHERE id = (item->>'asset_item_id')::BIGINT AND tenant_id = _tenant_id
                            RETURNING id INTO current_item_id;
                            
                            IF FOUND THEN
                                updated_count := updated_count + 1;
                            ELSE
                                error_count := error_count + 1;
                                error_details_array := array_append(error_details_array, 
                                    json_build_object(
                                        'row', processed_count,
                                        'asset_item_id', item->>'asset_item_id',
                                        'error', 'Asset item not found for update'
                                    )
                                );
                                CONTINUE;
                            END IF;
                        ELSE
                            -- Insert new item
                            INSERT INTO asset_items (
                                asset_id, model_number, serial_number, asset_tag,
                                qr_code, item_value, item_value_currency_id,
                                purchase_cost, purchase_cost_currency_id, purchase_type,
                                purchase_order_number, other_purchase_details, supplier,
                                salvage_value, warranty, warrenty_condition_type_id,
                                warranty_exparing_at, warrenty_usage_name, warranty_usage_value,
                                insurance_number, insurance_exparing_at, expected_life_time,
                                expected_life_time_unit, depreciation_value, depreciation_method,
                                depreciation_start_date, decline_rate, total_estimated_units,
                                manufacturer, responsible_person, department,
                                asset_location_latitude, asset_location_longitude, received_condition,
                                asset_classification, registered_by, tenant_id,
                                created_at, updated_at, is_created_from_imported_csv, if_imported_jobs_id
                            )
                            VALUES (
                                asset_id_val,
                                item->>'model_number',
                                item->>'serial_number',
                                CASE 
                                    WHEN (item->>'asset_tag') IS NULL OR (item->>'asset_tag') = '' 
                                    THEN generate_asset_item_tag_id() 
                                    ELSE item->>'asset_tag' 
                                END,
                                item->>'qr_code',
                                (item->>'item_value')::NUMERIC,
                                item_value_currency_id_val,
                                (item->>'purchase_cost')::NUMERIC,
                                purchase_cost_currency_id_val,
                                purchase_type_id_val,
                                item->>'purchase_order_number',
                                item->>'other_purchase_details',
                                supplier_id_val,
                                (item->>'salvage_value')::NUMERIC,
                                item->>'warranty',
                                warranty_condition_type_id_val,
                                (item->>'warranty_expiry_date')::DATE,
                                item->>'warranty_usage_name',
                                item->>'warranty_usage_value',
                                item->>'insurance_number',
                                (item->>'insurance_expiry_date')::DATE,
                                item->>'expected_life_time',
                                time_period_id_val,
                                (item->>'depreciation_value')::NUMERIC,
                                depreciation_method_id_val,
                                (item->>'depreciation_start_date')::DATE,
                                (item->>'decline_rate')::NUMERIC,
                                (item->>'total_estimated_units')::NUMERIC,
                                item->>'manufacturer',
                                responsible_person_id_val,
                                department_id_val,
                                (item->>'latitude')::TEXT,
                                (item->>'longitude')::TEXT,
                                item->>'received_condition_id',
                                json_build_object(
                                    'asset_category_id', asset_category_id_val,
                                    'asset_sub_category_id', asset_sub_category_id_val,
                                    'asset_tags', (item->>'asset_tags')::JSON
                                ),
                                _registered_by_user_id,
                                _tenant_id,
                                _current_time,
                                _current_time,
                                true,
                                _import_job_id
                            )
                            RETURNING id INTO current_item_id;
                            
                            inserted_count := inserted_count + 1;
                        END IF;

                    EXCEPTION WHEN OTHERS THEN
                        error_count := error_count + 1;
                        error_details_array := array_append(error_details_array, 
                            json_build_object(
                                'row', processed_count,
                                'serial_number', item->>'serial_number',
                                'error', SQLERRM
                            )
                        );
                    END;
                END LOOP;

                -- Return comprehensive results
                RETURN QUERY SELECT
                    'SUCCESS'::TEXT AS status,
                    format('Processed %s items: %s inserted, %s updated, %s errors', 
                        processed_count, inserted_count, updated_count, error_count)::TEXT AS message,
                    processed_count AS total_processed,
                    inserted_count AS total_inserted,
                    updated_count AS total_updated,
                    error_count AS total_errors,
                    '[]'::JSON AS batch_results,
                    array_to_json(error_details_array) AS error_details;

            EXCEPTION
                WHEN OTHERS THEN
                    RETURN QUERY SELECT
                        'ERROR'::TEXT AS status,
                        format('Critical error during bulk processing: %s', SQLERRM)::TEXT AS message,
                        processed_count AS total_processed,
                        inserted_count AS total_inserted,
                        updated_count AS total_updated,
                        error_count AS total_errors,
                        '[]'::JSON AS batch_results,
                        json_build_array(json_build_object(
                            'error', 'Critical processing error',
                            'details', SQLERRM
                        )) AS error_details;
            END;
            \$\$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS bulk_insert_asset_items_with_relationships(BIGINT, BIGINT, TIMESTAMP WITH TIME ZONE, JSON, INTEGER);');
    }
};