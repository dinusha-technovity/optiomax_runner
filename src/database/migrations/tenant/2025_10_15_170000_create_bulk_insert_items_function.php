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
                    WHERE proname = 'bulk_insert_items_with_relationships'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            -- Fixed function to handle items bulk import with correct column references
            CREATE OR REPLACE FUNCTION bulk_insert_items_with_relationships(
                IN _created_by_user_id BIGINT,
                IN _tenant_id BIGINT,
                IN _job_id BIGINT,
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
                
                current_item_id BIGINT;
                existing_item_id BIGINT;
                
                -- Lookup variables
                category_id_val BIGINT;
                item_category_type_id_val BIGINT;
                type_id_val BIGINT;
                unit_of_measure_id_val BIGINT;
                purchase_price_currency_id_val BIGINT;
                selling_price_currency_id_val BIGINT;
                
                -- Batch processing variables
                current_item_name TEXT;
                batch_counter INTEGER := 0;
                
                -- Lookup variables for optimization
                names_in_batch TEXT[] := '{}';
                duplicate_names TEXT[] := '{}';
            BEGIN
                -- Create temporary tables for performance optimization
                CREATE TEMP TABLE temp_existing_items (
                    item_name TEXT PRIMARY KEY,
                    item_id BIGINT
                ) ON COMMIT DROP;
                
                CREATE TEMP TABLE temp_existing_categories (
                    category_name_lower TEXT PRIMARY KEY,
                    category_id BIGINT
                ) ON COMMIT DROP;
                
                CREATE TEMP TABLE temp_existing_item_category_types (
                    type_name_lower TEXT PRIMARY KEY,
                    type_id BIGINT
                ) ON COMMIT DROP;
                
                CREATE TEMP TABLE temp_existing_item_types (
                    type_name_lower TEXT PRIMARY KEY,
                    type_id BIGINT
                ) ON COMMIT DROP;
                
                CREATE TEMP TABLE temp_existing_measurements (
                    unit_name_lower TEXT PRIMARY KEY,
                    unit_id BIGINT
                ) ON COMMIT DROP;
                
                CREATE TEMP TABLE temp_existing_currencies (
                    currency_code_upper TEXT PRIMARY KEY,
                    currency_id BIGINT
                ) ON COMMIT DROP;

                -- Pre-load existing items for this tenant
                INSERT INTO temp_existing_items (item_name, item_id)
                SELECT item_name, id
                FROM items 
                WHERE tenant_id = _tenant_id 
                AND item_name IS NOT NULL 
                AND isactive = true;
                
                -- Pre-load existing asset categories
                INSERT INTO temp_existing_categories (category_name_lower, category_id)
                SELECT LOWER(name), id
                FROM asset_categories 
                WHERE tenant_id = _tenant_id 
                AND isactive = true;
                
                -- Pre-load existing item category types (check if table exists)
                BEGIN
                    INSERT INTO temp_existing_item_category_types (type_name_lower, type_id)
                    SELECT LOWER(name), id
                    FROM item_category_type 
                    WHERE name IS NOT NULL;
                EXCEPTION WHEN undefined_table THEN
                    -- Table doesn't exist, skip this lookup
                    NULL;
                END;
                
                -- Pre-load existing item types (handle different possible column names)
                BEGIN
                    -- Try type_name first
                    INSERT INTO temp_existing_item_types (type_name_lower, type_id)
                    SELECT LOWER(type_name), id
                    FROM item_types 
                    WHERE type_name IS NOT NULL;
                EXCEPTION WHEN undefined_column THEN
                    BEGIN
                        -- Try name column instead
                        INSERT INTO temp_existing_item_types (type_name_lower, type_id)
                        SELECT LOWER(name), id
                        FROM item_types 
                        WHERE name IS NOT NULL;
                    EXCEPTION WHEN undefined_column THEN
                        -- Neither column exists, skip this lookup
                        NULL;
                    END;
                END;
                
                -- Pre-load existing measurements (handle different possible column names)
                BEGIN
                    -- Try unit_name first
                    INSERT INTO temp_existing_measurements (unit_name_lower, unit_id)
                    SELECT LOWER(unit_name), id
                    FROM measurements 
                    WHERE unit_name IS NOT NULL;
                EXCEPTION WHEN undefined_column THEN
                    BEGIN
                        -- Try name column instead
                        INSERT INTO temp_existing_measurements (unit_name_lower, unit_id)
                        SELECT LOWER(name), id
                        FROM measurements 
                        WHERE name IS NOT NULL;
                    EXCEPTION WHEN undefined_column THEN
                        -- Try abbreviation column
                        BEGIN
                            INSERT INTO temp_existing_measurements (unit_name_lower, unit_id)
                            SELECT LOWER(abbreviation), id
                            FROM measurements 
                            WHERE abbreviation IS NOT NULL;
                        EXCEPTION WHEN undefined_column THEN
                            -- No suitable column found, skip this lookup
                            NULL;
                        END;
                    END;
                END;
                
                -- Pre-load existing currencies
                INSERT INTO temp_existing_currencies (currency_code_upper, currency_id)
                SELECT UPPER(code), id
                FROM currencies 
                WHERE is_active = true;

                -- Create indexes on temp tables for better performance
                CREATE INDEX idx_temp_items_name ON temp_existing_items(item_name);
                CREATE INDEX idx_temp_categories_name ON temp_existing_categories(category_name_lower);
                CREATE INDEX idx_temp_item_category_types_name ON temp_existing_item_category_types(type_name_lower);
                CREATE INDEX idx_temp_item_types_name ON temp_existing_item_types(type_name_lower);
                CREATE INDEX idx_temp_measurements_name ON temp_existing_measurements(unit_name_lower);
                CREATE INDEX idx_temp_currencies_code ON temp_existing_currencies(currency_code_upper);

                -- Pre-check for duplicate names in the input data
                FOR item IN SELECT * FROM json_array_elements(_items)
                LOOP
                    current_item_name := item->>'item_name';
                    
                    IF current_item_name IS NOT NULL AND current_item_name != '' THEN
                        -- Check if this name already appeared in this batch
                        IF current_item_name = ANY(names_in_batch) THEN
                            duplicate_names := array_append(duplicate_names, current_item_name);
                            error_count := error_count + 1;
                            error_details_array := array_append(error_details_array, 
                                json_build_object(
                                    'row', processed_count + 1,
                                    'item_name', current_item_name,
                                    'error', 'Duplicate item name within CSV data'
                                )
                            );
                        ELSE
                            names_in_batch := array_append(names_in_batch, current_item_name);
                        END IF;
                    END IF;
                END LOOP;

                -- Process each item in batches
                FOR item IN SELECT * FROM json_array_elements(_items)
                LOOP
                    processed_count := processed_count + 1;
                    batch_counter := batch_counter + 1;
                    current_item_name := item->>'item_name';
                    
                    -- Skip if this name was marked as duplicate
                    IF current_item_name = ANY(duplicate_names) THEN
                        CONTINUE;
                    END IF;
                    
                    BEGIN
                        -- Reset variables for each item
                        current_item_id := NULL;
                        existing_item_id := NULL;
                        category_id_val := NULL;
                        item_category_type_id_val := NULL;
                        type_id_val := NULL;
                        unit_of_measure_id_val := NULL;
                        purchase_price_currency_id_val := NULL;
                        selling_price_currency_id_val := NULL;

                        -- Skip if no name provided
                        IF current_item_name IS NULL OR current_item_name = '' THEN
                            error_count := error_count + 1;
                            error_details_array := array_append(error_details_array, 
                                json_build_object(
                                    'row', processed_count,
                                    'item_name', current_item_name,
                                    'error', 'Item name is required'
                                )
                            );
                            CONTINUE;
                        END IF;

                        -- Handle Category lookup/creation
                        IF (item->>'category_name') IS NOT NULL AND (item->>'category_name') != '' THEN
                            SELECT category_id INTO category_id_val
                            FROM temp_existing_categories
                            WHERE category_name_lower = LOWER(item->>'category_name')
                            LIMIT 1;
                            
                            -- If category not found, create it
                            IF category_id_val IS NULL THEN
                                BEGIN
                                    INSERT INTO asset_categories (
                                        name,
                                        description,
                                        assets_type,
                                        reading_parameters,
                                        deleted_at,
                                        isactive,
                                        tenant_id,
                                        created_by,
                                        is_created_from_imported_csv,
                                        if_imported_jobs_id,
                                        created_at,
                                        updated_at
                                    )
                                    VALUES (
                                        item->>'category_name',
                                        'Auto-created from items CSV import',
                                        NULL,
                                        NULL,
                                        NULL,
                                        true,
                                        _tenant_id,
                                        _created_by_user_id,
                                        true,
                                        _job_id,
                                        _current_time,
                                        _current_time
                                    )
                                    RETURNING id INTO category_id_val;
                                    
                                    -- Update temp table cache with new category
                                    INSERT INTO temp_existing_categories (category_name_lower, category_id)
                                    VALUES (LOWER(item->>'category_name'), category_id_val)
                                    ON CONFLICT (category_name_lower) DO NOTHING;
                                    
                                EXCEPTION WHEN unique_violation THEN
                                    -- Handle concurrent insertion race condition
                                    SELECT id INTO category_id_val
                                    FROM asset_categories
                                    WHERE LOWER(name) = LOWER(item->>'category_name')
                                    AND tenant_id = _tenant_id
                                    LIMIT 1;
                                    
                                    IF category_id_val IS NOT NULL THEN
                                        -- Update temp table cache
                                        INSERT INTO temp_existing_categories (category_name_lower, category_id)
                                        VALUES (LOWER(item->>'category_name'), category_id_val)
                                        ON CONFLICT (category_name_lower) DO UPDATE SET category_id = EXCLUDED.category_id;
                                    ELSE
                                        error_count := error_count + 1;
                                        error_details_array := array_append(error_details_array, 
                                            json_build_object(
                                                'row', processed_count,
                                                'item_name', current_item_name,
                                                'error', format('Failed to create or find category: %s', item->>'category_name')
                                            )
                                        );
                                        CONTINUE;
                                    END IF;
                                END;
                            END IF;
                        END IF;

                        -- Handle Item Category Type lookup (optional - may not exist)
                        IF (item->>'item_category_type_name') IS NOT NULL AND (item->>'item_category_type_name') != '' THEN
                            SELECT type_id INTO item_category_type_id_val
                            FROM temp_existing_item_category_types
                            WHERE type_name_lower = LOWER(item->>'item_category_type_name')
                            LIMIT 1;
                            
                            -- Note: This is optional, so we don't fail if not found
                        END IF;

                        -- Handle Item Type lookup (optional - may not exist)
                        IF (item->>'type_name') IS NOT NULL AND (item->>'type_name') != '' THEN
                            SELECT type_id INTO type_id_val
                            FROM temp_existing_item_types
                            WHERE type_name_lower = LOWER(item->>'type_name')
                            LIMIT 1;
                            
                            -- Note: This is optional, so we don't fail if not found
                        END IF;

                        -- Handle Unit of Measure lookup (optional - may not exist)
                        IF (item->>'unit_of_measure_name') IS NOT NULL AND (item->>'unit_of_measure_name') != '' THEN
                            SELECT unit_id INTO unit_of_measure_id_val
                            FROM temp_existing_measurements
                            WHERE unit_name_lower = LOWER(item->>'unit_of_measure_name')
                            LIMIT 1;
                            
                            -- Note: This is optional, so we don't fail if not found
                        END IF;

                        -- Handle Purchase Price Currency lookup
                        IF (item->>'purchase_price_currency_code') IS NOT NULL AND (item->>'purchase_price_currency_code') != '' THEN
                            SELECT currency_id INTO purchase_price_currency_id_val
                            FROM temp_existing_currencies
                            WHERE currency_code_upper = UPPER(item->>'purchase_price_currency_code')
                            LIMIT 1;
                            
                            IF purchase_price_currency_id_val IS NULL THEN
                                error_count := error_count + 1;
                                error_details_array := array_append(error_details_array, 
                                    json_build_object(
                                        'row', processed_count,
                                        'item_name', current_item_name,
                                        'error', format('Purchase price currency not found: %s', item->>'purchase_price_currency_code')
                                    )
                                );
                                CONTINUE;
                            END IF;
                        END IF;

                        -- Handle Selling Price Currency lookup
                        IF (item->>'selling_price_currency_code') IS NOT NULL AND (item->>'selling_price_currency_code') != '' THEN
                            SELECT currency_id INTO selling_price_currency_id_val
                            FROM temp_existing_currencies
                            WHERE currency_code_upper = UPPER(item->>'selling_price_currency_code')
                            LIMIT 1;
                            
                            IF selling_price_currency_id_val IS NULL THEN
                                error_count := error_count + 1;
                                error_details_array := array_append(error_details_array, 
                                    json_build_object(
                                        'row', processed_count,
                                        'item_name', current_item_name,
                                        'error', format('Selling price currency not found: %s', item->>'selling_price_currency_code')
                                    )
                                );
                                CONTINUE;
                            END IF;
                        END IF;

                        -- Check if item exists using temp table
                        SELECT item_id INTO existing_item_id
                        FROM temp_existing_items
                        WHERE item_name = current_item_name
                        LIMIT 1;
                        
                        IF existing_item_id IS NOT NULL THEN
                            -- Item exists, update it
                            UPDATE items SET
                                item_description = COALESCE(item->>'item_description', item_description),
                                category_id = COALESCE(category_id_val, category_id),
                                item_category_type_id = COALESCE(item_category_type_id_val, item_category_type_id),
                                type_id = COALESCE(type_id_val, type_id),
                                unit_of_measure_id = COALESCE(unit_of_measure_id_val, unit_of_measure_id),
                                purchase_price = CASE 
                                    WHEN item->>'purchase_price' IS NOT NULL 
                                    THEN (item->>'purchase_price')::DECIMAL(15,2)
                                    ELSE purchase_price 
                                END,
                                purchase_price_currency_id = COALESCE(purchase_price_currency_id_val, purchase_price_currency_id),
                                selling_price = CASE 
                                    WHEN item->>'selling_price' IS NOT NULL 
                                    THEN (item->>'selling_price')::DECIMAL(15,2)
                                    ELSE selling_price 
                                END,
                                selling_price_currency_id = COALESCE(selling_price_currency_id_val, selling_price_currency_id),
                                max_inventory_level = CASE 
                                    WHEN item->>'max_inventory_level' IS NOT NULL 
                                    THEN (item->>'max_inventory_level')::INTEGER
                                    ELSE max_inventory_level 
                                END,
                                min_inventory_level = CASE 
                                    WHEN item->>'min_inventory_level' IS NOT NULL 
                                    THEN (item->>'min_inventory_level')::INTEGER
                                    ELSE min_inventory_level 
                                END,
                                re_order_level = CASE 
                                    WHEN item->>'re_order_level' IS NOT NULL 
                                    THEN (item->>'re_order_level')::INTEGER
                                    ELSE re_order_level 
                                END,
                                low_stock_alert = CASE 
                                    WHEN item->>'low_stock_alert' IS NOT NULL 
                                    THEN (item->>'low_stock_alert')::BOOLEAN
                                    ELSE low_stock_alert 
                                END,
                                over_stock_alert = CASE 
                                    WHEN item->>'over_stock_alert' IS NOT NULL 
                                    THEN (item->>'over_stock_alert')::BOOLEAN
                                    ELSE over_stock_alert 
                                END,
                                image_links = CASE 
                                    WHEN item->>'image_links' IS NOT NULL 
                                    THEN (item->>'image_links')::JSONB
                                    ELSE image_links 
                                END,
                                isactive = true,
                                deleted_at = NULL,
                                created_by = COALESCE(created_by, _created_by_user_id),
                                is_created_from_imported_csv = true,
                                if_imported_jobs_id = _job_id,
                                updated_at = _current_time
                            WHERE id = existing_item_id
                            RETURNING id INTO current_item_id;
                            
                            updated_count := updated_count + 1;
                        ELSE
                            -- Item doesn't exist, create new one
                            BEGIN
                                INSERT INTO items (
                                    item_id,
                                    item_name,
                                    item_description,
                                    tenant_id,
                                    category_id,
                                    item_category_type_id,
                                    type_id,
                                    unit_of_measure_id,
                                    purchase_price,
                                    purchase_price_currency_id,
                                    selling_price,
                                    selling_price_currency_id,
                                    max_inventory_level,
                                    min_inventory_level,
                                    re_order_level,
                                    low_stock_alert,
                                    over_stock_alert,
                                    image_links,
                                    deleted_at,
                                    isactive,
                                    created_by,
                                    is_created_from_imported_csv,
                                    if_imported_jobs_id,
                                    created_at,
                                    updated_at
                                )
                                VALUES (
                                    item->>'item_id',
                                    current_item_name,
                                    item->>'item_description',
                                    _tenant_id,
                                    category_id_val,
                                    item_category_type_id_val,
                                    type_id_val,
                                    unit_of_measure_id_val,
                                    COALESCE((item->>'purchase_price')::DECIMAL(15,2), 0),
                                    purchase_price_currency_id_val,
                                    COALESCE((item->>'selling_price')::DECIMAL(15,2), 0),
                                    selling_price_currency_id_val,
                                    COALESCE((item->>'max_inventory_level')::INTEGER, 0),
                                    COALESCE((item->>'min_inventory_level')::INTEGER, 0),
                                    COALESCE((item->>'re_order_level')::INTEGER, 0),
                                    COALESCE((item->>'low_stock_alert')::BOOLEAN, false),
                                    COALESCE((item->>'over_stock_alert')::BOOLEAN, false),
                                    CASE 
                                        WHEN item->>'image_links' IS NOT NULL 
                                        THEN (item->>'image_links')::JSONB
                                        ELSE NULL 
                                    END,
                                    NULL,
                                    true,
                                    _created_by_user_id,
                                    true,
                                    _job_id,
                                    _current_time,
                                    _current_time
                                )
                                RETURNING id INTO current_item_id;
                                
                                -- Update temp table cache with new item
                                INSERT INTO temp_existing_items (item_name, item_id)
                                VALUES (current_item_name, current_item_id)
                                ON CONFLICT (item_name) DO NOTHING;
                                
                                inserted_count := inserted_count + 1;
                                
                            EXCEPTION WHEN unique_violation THEN
                                -- Handle concurrent insertion race condition
                                SELECT id INTO existing_item_id 
                                FROM items 
                                WHERE item_name = current_item_name 
                                AND tenant_id = _tenant_id
                                LIMIT 1;
                                
                                IF existing_item_id IS NOT NULL THEN
                                    -- Update temp table cache
                                    INSERT INTO temp_existing_items (item_name, item_id)
                                    VALUES (current_item_name, existing_item_id)
                                    ON CONFLICT (item_name) DO UPDATE SET item_id = EXCLUDED.item_id;
                                    
                                    updated_count := updated_count + 1;
                                ELSE
                                    error_count := error_count + 1;
                                    error_details_array := array_append(error_details_array, 
                                        json_build_object(
                                            'row', processed_count,
                                            'item_name', current_item_name,
                                            'error', 'Concurrent modification detected'
                                        )
                                    );
                                END IF;
                            END;
                        END IF;

                        -- Commit in batches for better performance
                        IF batch_counter >= _batch_size THEN
                            batch_counter := 0;
                            PERFORM pg_advisory_lock(_tenant_id);
                            PERFORM pg_advisory_unlock(_tenant_id);
                        END IF;

                    EXCEPTION WHEN OTHERS THEN
                        error_count := error_count + 1;
                        error_details_array := array_append(error_details_array, 
                            json_build_object(
                                'row', processed_count,
                                'item_name', current_item_name,
                                'error', SQLERRM,
                                'sql_state', SQLSTATE
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
                    json_build_object(
                        'batch_size', _batch_size,
                        'cache_hits', processed_count - error_count,
                        'processing_time_ms', extract(epoch from (clock_timestamp() - _current_time)) * 1000,
                        'duplicate_names_found', array_length(duplicate_names, 1),
                        'optimization_level', 'enterprise'
                    ) AS batch_results,
                    array_to_json(error_details_array) AS error_details;

            EXCEPTION
                WHEN OTHERS THEN
                    RETURN QUERY SELECT
                        'ERROR'::TEXT AS status,
                        format('Critical error during items bulk processing: %s (SQL State: %s)', SQLERRM, SQLSTATE)::TEXT AS message,
                        processed_count AS total_processed,
                        inserted_count AS total_inserted,
                        updated_count AS total_updated,
                        error_count AS total_errors,
                        json_build_object(
                            'critical_error', true,
                            'sql_state', SQLSTATE,
                            'processing_stopped_at_row', processed_count
                        ) AS batch_results,
                        json_build_array(json_build_object(
                            'error', 'Critical processing error',
                            'details', SQLERRM,
                            'sql_state', SQLSTATE,
                            'row', processed_count
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
        DB::unprepared('DROP FUNCTION IF EXISTS bulk_insert_items_with_relationships(BIGINT, BIGINT, BIGINT, TIMESTAMP WITH TIME ZONE, JSON, INTEGER);');
    }
};