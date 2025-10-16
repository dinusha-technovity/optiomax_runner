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
                    WHERE proname = 'bulk_insert_asset_categories_with_relationships'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            -- Function to handle asset category bulk import with relationship management (Enterprise Optimized)
            CREATE OR REPLACE FUNCTION bulk_insert_asset_categories_with_relationships(
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
                
                current_category_id BIGINT;
                existing_category_id BIGINT;
                
                -- Lookup variables
                asset_type_id_val BIGINT;
                
                -- Batch processing variables
                current_name TEXT;
                batch_counter INTEGER := 0;
                
                -- Lookup variables for optimization
                names_in_batch TEXT[] := '{}';
                duplicate_names TEXT[] := '{}';
            BEGIN
                -- Create temporary tables for performance optimization
                CREATE TEMP TABLE temp_existing_categories (
                    name TEXT PRIMARY KEY,
                    category_id BIGINT
                ) ON COMMIT DROP;
                
                CREATE TEMP TABLE temp_existing_asset_types (
                    type_name_lower TEXT PRIMARY KEY,
                    type_id BIGINT
                ) ON COMMIT DROP;

                -- Pre-load existing asset categories for this tenant
                INSERT INTO temp_existing_categories (name, category_id)
                SELECT name, id
                FROM asset_categories 
                WHERE tenant_id = _tenant_id 
                AND name IS NOT NULL 
                AND isactive = true;
                
                -- Pre-load existing asset types
                INSERT INTO temp_existing_asset_types (type_name_lower, type_id)
                SELECT LOWER(name), id
                FROM assets_types 
                WHERE asset_type IS NOT NULL;

                -- Create indexes on temp tables for better performance
                CREATE INDEX idx_temp_categories_name ON temp_existing_categories(name);
                CREATE INDEX idx_temp_asset_types_name ON temp_existing_asset_types(type_name_lower);

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

                -- Process each asset category item in batches
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
                        current_category_id := NULL;
                        existing_category_id := NULL;
                        asset_type_id_val := NULL;

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

                        -- Handle Asset Type lookup
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
                                        'error', format('Invalid asset type: %s. Valid types are: Tangible, Intangible, Operating, Non-operating, Current, Fixed', item->>'asset_type_name')
                                    )
                                );
                                CONTINUE;
                            END IF;
                        END IF;

                        -- Check if category exists using temp table
                        SELECT category_id INTO existing_category_id
                        FROM temp_existing_categories
                        WHERE name = current_name
                        LIMIT 1;
                        
                        IF existing_category_id IS NOT NULL THEN
                            -- Category exists, update it
                            UPDATE asset_categories SET
                                description = COALESCE(item->>'description', description),
                                assets_type = COALESCE(asset_type_id_val, assets_type),
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
                            WHERE id = existing_category_id
                            RETURNING id INTO current_category_id;
                            
                            updated_count := updated_count + 1;
                        ELSE
                            -- Category doesn't exist, create new one
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
                                    current_name,
                                    item->>'description',
                                    asset_type_id_val,
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
                                RETURNING id INTO current_category_id;
                                
                                -- Update temp table cache with new category
                                INSERT INTO temp_existing_categories (name, category_id)
                                VALUES (current_name, current_category_id)
                                ON CONFLICT (name) DO NOTHING;
                                
                                inserted_count := inserted_count + 1;
                                
                            EXCEPTION WHEN unique_violation THEN
                                -- Handle concurrent insertion race condition
                                SELECT id INTO existing_category_id 
                                FROM asset_categories 
                                WHERE name = current_name 
                                AND tenant_id = _tenant_id
                                LIMIT 1;
                                
                                IF existing_category_id IS NOT NULL THEN
                                    -- Update temp table cache
                                    INSERT INTO temp_existing_categories (name, category_id)
                                    VALUES (current_name, existing_category_id)
                                    ON CONFLICT (name) DO UPDATE SET category_id = EXCLUDED.category_id;
                                    
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
                    format('Processed %s asset categories: %s inserted, %s updated, %s errors', 
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
                        format('Critical error during asset category bulk processing: %s (SQL State: %s)', SQLERRM, SQLSTATE)::TEXT AS message,
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
        DB::unprepared('DROP FUNCTION IF EXISTS bulk_insert_asset_categories_with_relationships(BIGINT, BIGINT, BIGINT, TIMESTAMP WITH TIME ZONE, JSON, INTEGER);');
    }
};
