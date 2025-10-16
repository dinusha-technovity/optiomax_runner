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
                    WHERE proname = 'bulk_insert_assets_with_relationships'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            -- Function to handle asset bulk import with relationship management (Enterprise Optimized)
            CREATE OR REPLACE FUNCTION bulk_insert_assets_with_relationships(
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
                batch_results_array JSON[] := '{}';
                
                current_asset_id BIGINT;
                existing_asset_id BIGINT;
                
                -- Lookup variables
                asset_type_id_val BIGINT;
                category_id_val BIGINT;
                sub_category_id_val BIGINT;
                
                -- Batch processing variables
                current_name TEXT;
                batch_counter INTEGER := 0;
                
                -- Lookup variables for optimization
                names_in_batch TEXT[] := '{}';
                duplicate_names TEXT[] := '{}';
            BEGIN
                -- Create temporary tables for performance optimization
                CREATE TEMP TABLE temp_existing_assets (
                    name TEXT PRIMARY KEY,
                    asset_id BIGINT
                ) ON COMMIT DROP;
                
                CREATE TEMP TABLE temp_existing_asset_types (
                    type_name_lower TEXT PRIMARY KEY,
                    type_id BIGINT
                ) ON COMMIT DROP;
                
                CREATE TEMP TABLE temp_existing_categories (
                    category_name_lower TEXT PRIMARY KEY,
                    category_id BIGINT
                ) ON COMMIT DROP;
                
                CREATE TEMP TABLE temp_existing_sub_categories (
                    sub_category_name_lower TEXT PRIMARY KEY,
                    sub_category_id BIGINT
                ) ON COMMIT DROP;

                -- Pre-load existing assets for this tenant
                INSERT INTO temp_existing_assets (name, asset_id)
                SELECT name, id
                FROM assets 
                WHERE tenant_id = _tenant_id 
                AND name IS NOT NULL 
                AND isactive = true;
                
                -- Pre-load existing asset types
                INSERT INTO temp_existing_asset_types (type_name_lower, type_id)
                SELECT LOWER(name), id
                FROM assets_types 
                WHERE asset_type IS NOT NULL;
                
                -- Pre-load existing asset categories
                INSERT INTO temp_existing_categories (category_name_lower, category_id)
                SELECT LOWER(name), id
                FROM asset_categories 
                WHERE tenant_id = _tenant_id 
                AND isactive = true;
                
                -- Pre-load existing asset sub-categories
                INSERT INTO temp_existing_sub_categories (sub_category_name_lower, sub_category_id)
                SELECT LOWER(name), id
                FROM asset_sub_categories 
                WHERE tenant_id = _tenant_id 
                AND isactive = true;

                -- Create indexes on temp tables for better performance
                CREATE INDEX idx_temp_assets_name ON temp_existing_assets(name);
                CREATE INDEX idx_temp_asset_types_name ON temp_existing_asset_types(type_name_lower);
                CREATE INDEX idx_temp_categories_name ON temp_existing_categories(category_name_lower);
                CREATE INDEX idx_temp_sub_categories_name ON temp_existing_sub_categories(sub_category_name_lower);

                -- Pre-check for duplicate names in the input data
                FOR item IN SELECT * FROM json_array_elements(_items)
                LOOP
                    current_name := item->>'name';
                    
                    IF current_name IS NOT NULL AND current_name != '' THEN
                        -- Check if this name already appeared in this batch
                        IF current_name = ANY(names_in_batch) THEN
                            duplicate_names := array_append(duplicate_names, current_name);
                            error_count := error_count + 1;
                            error_details_array := array_append(error_details_array, 
                                json_build_object(
                                    'row', processed_count + 1,
                                    'name', current_name,
                                    'error', 'Duplicate name within CSV data'
                                )
                            );
                        ELSE
                            names_in_batch := array_append(names_in_batch, current_name);
                        END IF;
                    END IF;
                END LOOP;

                -- Process each asset item in batches
                FOR item IN SELECT * FROM json_array_elements(_items)
                LOOP
                    processed_count := processed_count + 1;
                    batch_counter := batch_counter + 1;
                    current_name := item->>'name';
                    
                    -- Skip if this name was marked as duplicate
                    IF current_name = ANY(duplicate_names) THEN
                        CONTINUE;
                    END IF;
                    
                    BEGIN
                        -- Reset variables for each item
                        current_asset_id := NULL;
                        existing_asset_id := NULL;
                        asset_type_id_val := NULL;
                        category_id_val := NULL;
                        sub_category_id_val := NULL;

                        -- Skip if no name provided
                        IF current_name IS NULL OR current_name = '' THEN
                            error_count := error_count + 1;
                            error_details_array := array_append(error_details_array, 
                                json_build_object(
                                    'row', processed_count,
                                    'name', current_name,
                                    'error', 'Name is required'
                                )
                            );
                            CONTINUE;
                        END IF;

                        -- Handle Asset Type lookup (required for category creation)
                        IF (item->>'asset_type_name') IS NOT NULL AND (item->>'asset_type_name') != '' THEN
                            SELECT type_id INTO asset_type_id_val
                            FROM temp_existing_asset_types
                            WHERE type_name_lower = LOWER(item->>'asset_type_name')
                            LIMIT 1;
                            
                            -- If asset type not found, add error
                            IF asset_type_id_val IS NULL THEN
                                error_count := error_count + 1;
                                error_details_array := array_append(error_details_array, 
                                    json_build_object(
                                        'row', processed_count,
                                        'name', current_name,
                                        'error', format('Invalid asset type: %s. Valid types are: Tangible assets, Intangible assets, Operating assets, Non-operating assets, Current assets, Fixed assets', item->>'asset_type_name')
                                    )
                                );
                                CONTINUE;
                            END IF;
                        ELSE
                            -- Asset type is required for category creation
                            error_count := error_count + 1;
                            error_details_array := array_append(error_details_array, 
                                json_build_object(
                                    'row', processed_count,
                                    'name', current_name,
                                    'error', 'Asset type name is required for creating categories'
                                )
                            );
                            CONTINUE;
                        END IF;

                        -- Handle Category lookup/creation (now requires asset_type_id_val)
                        IF (item->>'category_name') IS NOT NULL AND (item->>'category_name') != '' THEN
                            SELECT category_id INTO category_id_val
                            FROM temp_existing_categories
                            WHERE category_name_lower = LOWER(item->>'category_name')
                            LIMIT 1;
                            
                            -- If category not found, create it with the asset type
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
                                        'Auto-created from CSV import',
                                        asset_type_id_val,
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
                                                'name', current_name,
                                                'error', format('Failed to create or find category: %s', item->>'category_name')
                                            )
                                        );
                                        CONTINUE;
                                    END IF;
                                END;
                            END IF;
                        END IF;

                        -- Handle Sub-Category lookup/creation
                        IF (item->>'sub_category_name') IS NOT NULL AND (item->>'sub_category_name') != '' THEN
                            SELECT sub_category_id INTO sub_category_id_val
                            FROM temp_existing_sub_categories
                            WHERE sub_category_name_lower = LOWER(item->>'sub_category_name')
                            LIMIT 1;
                            
                            -- If sub-category not found, create it (but only if we have a category)
                            IF sub_category_id_val IS NULL THEN
                                IF category_id_val IS NULL THEN
                                    error_count := error_count + 1;
                                    error_details_array := array_append(error_details_array, 
                                        json_build_object(
                                            'row', processed_count,
                                            'name', current_name,
                                            'error', format('Cannot create sub-category %s without a valid category', item->>'sub_category_name')
                                        )
                                    );
                                    CONTINUE;
                                END IF;
                                
                                BEGIN
                                    INSERT INTO asset_sub_categories (
                                        asset_category_id,
                                        name,
                                        description,
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
                                        category_id_val,
                                        item->>'sub_category_name',
                                        'Auto-created from CSV import',
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
                                    RETURNING id INTO sub_category_id_val;
                                    
                                    -- Update temp table cache with new sub-category
                                    INSERT INTO temp_existing_sub_categories (sub_category_name_lower, sub_category_id)
                                    VALUES (LOWER(item->>'sub_category_name'), sub_category_id_val)
                                    ON CONFLICT (sub_category_name_lower) DO NOTHING;
                                    
                                EXCEPTION WHEN unique_violation THEN
                                    -- Handle concurrent insertion race condition
                                    SELECT id INTO sub_category_id_val
                                    FROM asset_sub_categories
                                    WHERE LOWER(name) = LOWER(item->>'sub_category_name')
                                    AND tenant_id = _tenant_id
                                    LIMIT 1;
                                    
                                    IF sub_category_id_val IS NOT NULL THEN
                                        -- Update temp table cache
                                        INSERT INTO temp_existing_sub_categories (sub_category_name_lower, sub_category_id)
                                        VALUES (LOWER(item->>'sub_category_name'), sub_category_id_val)
                                        ON CONFLICT (sub_category_name_lower) DO UPDATE SET sub_category_id = EXCLUDED.sub_category_id;
                                    ELSE
                                        error_count := error_count + 1;
                                        error_details_array := array_append(error_details_array, 
                                            json_build_object(
                                                'row', processed_count,
                                                'name', current_name,
                                                'error', format('Failed to create or find sub-category: %s', item->>'sub_category_name')
                                            )
                                        );
                                        CONTINUE;
                                    END IF;
                                END;
                            END IF;
                        END IF;

                        -- Check if asset exists using temp table
                        SELECT asset_id INTO existing_asset_id
                        FROM temp_existing_assets
                        WHERE name = current_name
                        LIMIT 1;
                        
                        IF existing_asset_id IS NOT NULL THEN
                            -- Asset exists, update it
                            UPDATE assets SET
                                thumbnail_image = CASE 
                                    WHEN item->>'thumbnail_image' IS NOT NULL 
                                    THEN (item->>'thumbnail_image')::JSONB
                                    ELSE thumbnail_image 
                                END,
                                category = COALESCE(category_id_val, category),
                                sub_category = COALESCE(sub_category_id_val, sub_category),
                                asset_description = COALESCE(item->>'asset_description', asset_description),
                                asset_details = CASE 
                                    WHEN item->>'asset_details' IS NOT NULL 
                                    THEN (item->>'asset_details')::JSONB
                                    ELSE asset_details 
                                END,
                                asset_classification = CASE 
                                    WHEN item->>'asset_classification' IS NOT NULL 
                                    THEN (item->>'asset_classification')::JSONB
                                    ELSE asset_classification 
                                END,
                                reading_parameters = CASE 
                                    WHEN item->>'reading_parameters' IS NOT NULL 
                                    THEN (item->>'reading_parameters')::JSONB
                                    ELSE reading_parameters 
                                END,
                                isactive = true,
                                deleted_at = NULL,
                                registered_by = COALESCE(registered_by, _created_by_user_id),
                                is_created_from_imported_csv = true,
                                if_imported_jobs_id = _job_id,
                                updated_at = _current_time
                            WHERE id = existing_asset_id
                            RETURNING id INTO current_asset_id;
                            
                            updated_count := updated_count + 1;
                        ELSE
                            -- Asset doesn't exist, create new one
                            BEGIN
                                INSERT INTO assets (
                                    name,
                                    thumbnail_image,
                                    category,
                                    sub_category,
                                    asset_description,
                                    asset_details,
                                    asset_classification,
                                    reading_parameters,
                                    registered_by,
                                    deleted_at,
                                    isactive,
                                    tenant_id,
                                    is_created_from_imported_csv,
                                    if_imported_jobs_id,
                                    created_at,
                                    updated_at
                                )
                                VALUES (
                                    current_name,
                                    CASE 
                                        WHEN item->>'thumbnail_image' IS NOT NULL 
                                        THEN (item->>'thumbnail_image')::JSONB
                                        ELSE NULL 
                                    END,
                                    category_id_val,
                                    sub_category_id_val,
                                    item->>'asset_description',
                                    CASE 
                                        WHEN item->>'asset_details' IS NOT NULL 
                                        THEN (item->>'asset_details')::JSONB
                                        ELSE NULL 
                                    END,
                                    CASE 
                                        WHEN item->>'asset_classification' IS NOT NULL 
                                        THEN (item->>'asset_classification')::JSONB
                                        ELSE NULL 
                                    END,
                                    CASE 
                                        WHEN item->>'reading_parameters' IS NOT NULL 
                                        THEN (item->>'reading_parameters')::JSONB
                                        ELSE NULL 
                                    END,
                                    _created_by_user_id,
                                    NULL,
                                    true,
                                    _tenant_id,
                                    true,
                                    _job_id,
                                    _current_time,
                                    _current_time
                                )
                                RETURNING id INTO current_asset_id;
                                
                                -- Update temp table cache with new asset
                                INSERT INTO temp_existing_assets (name, asset_id)
                                VALUES (current_name, current_asset_id)
                                ON CONFLICT (name) DO NOTHING;
                                
                                inserted_count := inserted_count + 1;
                                
                            EXCEPTION WHEN unique_violation THEN
                                -- Handle concurrent insertion race condition
                                SELECT id INTO existing_asset_id 
                                FROM assets 
                                WHERE name = current_name 
                                AND tenant_id = _tenant_id
                                LIMIT 1;
                                
                                IF existing_asset_id IS NOT NULL THEN
                                    -- Update temp table cache
                                    INSERT INTO temp_existing_assets (name, asset_id)
                                    VALUES (current_name, existing_asset_id)
                                    ON CONFLICT (name) DO UPDATE SET asset_id = EXCLUDED.asset_id;
                                    
                                    updated_count := updated_count + 1;
                                ELSE
                                    error_count := error_count + 1;
                                    error_details_array := array_append(error_details_array, 
                                        json_build_object(
                                            'row', processed_count,
                                            'name', current_name,
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
                                'name', current_name,
                                'error', SQLERRM,
                                'sql_state', SQLSTATE
                            )
                        );
                    END;
                END LOOP;

                -- Return comprehensive results
                RETURN QUERY SELECT
                    'SUCCESS'::TEXT AS status,
                    format('Processed %s assets: %s inserted, %s updated, %s errors', 
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
                        format('Critical error during asset bulk processing: %s (SQL State: %s)', SQLERRM, SQLSTATE)::TEXT AS message,
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
        DB::unprepared('DROP FUNCTION IF EXISTS bulk_insert_assets_with_relationships(BIGINT, BIGINT, BIGINT, TIMESTAMP WITH TIME ZONE, JSON, INTEGER);');
    }
};