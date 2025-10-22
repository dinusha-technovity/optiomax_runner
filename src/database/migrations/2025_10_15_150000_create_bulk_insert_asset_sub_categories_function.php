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
                    WHERE proname = 'bulk_insert_asset_sub_categories_with_relationships'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            -- Function to handle asset sub-category bulk import with relationship management (Enterprise Optimized)
            CREATE OR REPLACE FUNCTION bulk_insert_asset_sub_categories_with_relationships(
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
                
                current_sub_category_id BIGINT;
                existing_sub_category_id BIGINT;
                
                -- Lookup variables
                asset_category_id_val BIGINT;
                
                -- Batch processing variables
                current_name TEXT;
                current_category_name TEXT;
                batch_counter INTEGER := 0;
                
                -- Lookup variables for optimization
                names_in_batch TEXT[] := '{}';
                duplicate_names TEXT[] := '{}';
            BEGIN
                -- Create temporary tables for performance optimization
                CREATE TEMP TABLE temp_existing_sub_categories (
                    name TEXT,
                    category_name TEXT,
                    sub_category_id BIGINT,
                    PRIMARY KEY (name, category_name)
                ) ON COMMIT DROP;
                
                CREATE TEMP TABLE temp_existing_categories (
                    category_name_lower TEXT PRIMARY KEY,
                    category_id BIGINT
                ) ON COMMIT DROP;

                -- Pre-load existing asset sub-categories for this tenant
                INSERT INTO temp_existing_sub_categories (name, category_name, sub_category_id)
                SELECT sub_cat.name, cat.name as category_name, sub_cat.id
                FROM asset_sub_categories sub_cat
                JOIN asset_categories cat ON sub_cat.asset_category_id = cat.id
                WHERE sub_cat.tenant_id = _tenant_id 
                AND sub_cat.name IS NOT NULL 
                AND sub_cat.isactive = true;
                
                -- Pre-load existing asset categories
                INSERT INTO temp_existing_categories (category_name_lower, category_id)
                SELECT LOWER(name), id
                FROM asset_categories 
                WHERE tenant_id = _tenant_id 
                AND isactive = true;

                -- Create indexes on temp tables for better performance
                CREATE INDEX idx_temp_sub_categories_name ON temp_existing_sub_categories(name, category_name);
                CREATE INDEX idx_temp_categories_name ON temp_existing_categories(category_name_lower);

                -- Pre-check for duplicate name+category combinations in the input data
                FOR item IN SELECT * FROM json_array_elements(_items)
                LOOP
                    current_name := item->>'name';
                    current_category_name := item->>'asset_category_name';
                    
                    IF current_name IS NOT NULL AND current_name != '' AND current_category_name IS NOT NULL AND current_category_name != '' THEN
                        -- Check if this name+category combination already appeared in this batch
                        IF (current_name || '|' || current_category_name) = ANY(names_in_batch) THEN
                            duplicate_names := array_append(duplicate_names, current_name || '|' || current_category_name);
                            error_count := error_count + 1;
                            error_details_array := array_append(error_details_array, 
                                json_build_object(
                                    'row', processed_count + 1,
                                    'name', current_name,
                                    'category_name', current_category_name,
                                    'error', 'Duplicate name and category combination within CSV data'
                                )
                            );
                        ELSE
                            names_in_batch := array_append(names_in_batch, current_name || '|' || current_category_name);
                        END IF;
                    END IF;
                END LOOP;

                -- Process each asset sub-category item in batches
                FOR item IN SELECT * FROM json_array_elements(_items)
                LOOP
                    processed_count := processed_count + 1;
                    batch_counter := batch_counter + 1;
                    current_name := item->>'name';
                    current_category_name := item->>'asset_category_name';
                    
                    -- Skip if this name+category was marked as duplicate
                    IF (current_name || '|' || current_category_name) = ANY(duplicate_names) THEN
                        CONTINUE;
                    END IF;
                    
                    BEGIN
                        -- Reset variables for each item
                        current_sub_category_id := NULL;
                        existing_sub_category_id := NULL;
                        asset_category_id_val := NULL;

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

                        -- Skip if no category name provided
                        IF current_category_name IS NULL OR current_category_name = '' THEN
                            error_count := error_count + 1;
                            error_details_array := array_append(error_details_array, 
                                json_build_object(
                                    'row', processed_count,
                                    'name', current_name,
                                    'error', 'Asset category name is required'
                                )
                            );
                            CONTINUE;
                        END IF;

                        -- Handle Asset Category lookup
                        SELECT category_id INTO asset_category_id_val
                        FROM temp_existing_categories
                        WHERE category_name_lower = LOWER(current_category_name)
                        LIMIT 1;
                        
                        -- If asset category not found, add error
                        IF asset_category_id_val IS NULL THEN
                            error_count := error_count + 1;
                            error_details_array := array_append(error_details_array, 
                                json_build_object(
                                    'row', processed_count,
                                    'name', current_name,
                                    'error', format('Asset category not found: %s. Please ensure the category exists.', current_category_name)
                                )
                            );
                            CONTINUE;
                        END IF;

                        -- Check if sub-category exists using temp table
                        SELECT sub_category_id INTO existing_sub_category_id
                        FROM temp_existing_sub_categories
                        WHERE name = current_name AND category_name = current_category_name
                        LIMIT 1;
                        
                        IF existing_sub_category_id IS NOT NULL THEN
                            -- Sub-category exists, update it
                            UPDATE asset_sub_categories SET
                                description = COALESCE(item->>'description', description),
                                reading_parameters = CASE 
                                    WHEN item->>'reading_parameters' IS NOT NULL 
                                    THEN (item->>'reading_parameters')::JSONB
                                    ELSE reading_parameters 
                                END,
                                isactive = true,
                                deleted_at = NULL,
                                created_by = COALESCE(created_by, _created_by_user_id),
                                is_created_from_imported_csv = true,
                                if_imported_jobs_id = _job_id,
                                updated_at = _current_time
                            WHERE id = existing_sub_category_id
                            RETURNING id INTO current_sub_category_id;
                            
                            updated_count := updated_count + 1;
                        ELSE
                            -- Sub-category doesn't exist, create new one
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
                                    asset_category_id_val,
                                    current_name,
                                    item->>'description',
                                    CASE 
                                        WHEN item->>'reading_parameters' IS NOT NULL 
                                        THEN (item->>'reading_parameters')::JSONB
                                        ELSE NULL 
                                    END,
                                    NULL,
                                    true,
                                    _tenant_id,
                                    _created_by_user_id,
                                    true,
                                    _job_id,
                                    _current_time,
                                    _current_time
                                )
                                RETURNING id INTO current_sub_category_id;
                                
                                -- Update temp table cache with new sub-category
                                INSERT INTO temp_existing_sub_categories (name, category_name, sub_category_id)
                                VALUES (current_name, current_category_name, current_sub_category_id)
                                ON CONFLICT (name, category_name) DO NOTHING;
                                
                                inserted_count := inserted_count + 1;
                                
                            EXCEPTION WHEN unique_violation THEN
                                -- Handle concurrent insertion race condition
                                SELECT sub_cat.id INTO existing_sub_category_id 
                                FROM asset_sub_categories sub_cat
                                JOIN asset_categories cat ON sub_cat.asset_category_id = cat.id
                                WHERE sub_cat.name = current_name 
                                AND cat.name = current_category_name
                                AND sub_cat.tenant_id = _tenant_id
                                LIMIT 1;
                                
                                IF existing_sub_category_id IS NOT NULL THEN
                                    -- Update temp table cache
                                    INSERT INTO temp_existing_sub_categories (name, category_name, sub_category_id)
                                    VALUES (current_name, current_category_name, existing_sub_category_id)
                                    ON CONFLICT (name, category_name) DO UPDATE SET sub_category_id = EXCLUDED.sub_category_id;
                                    
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
                    format('Processed %s asset sub-categories: %s inserted, %s updated, %s errors', 
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
                        format('Critical error during asset sub-category bulk processing: %s (SQL State: %s)', SQLERRM, SQLSTATE)::TEXT AS message,
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
        DB::unprepared('DROP FUNCTION IF EXISTS bulk_insert_asset_sub_categories_with_relationships(BIGINT, BIGINT, BIGINT, TIMESTAMP WITH TIME ZONE, JSON, INTEGER);');
    }
};