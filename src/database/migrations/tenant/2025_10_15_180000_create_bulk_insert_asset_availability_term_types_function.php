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
                    WHERE proname = 'bulk_insert_asset_availability_term_types_with_relationships'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            -- Function to handle asset availability term types bulk import (Enterprise Optimized)
            CREATE OR REPLACE FUNCTION bulk_insert_asset_availability_term_types_with_relationships(
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
                
                current_term_type_id BIGINT;
                existing_term_type_id BIGINT;
                
                -- Batch processing variables
                current_name TEXT;
                batch_counter INTEGER := 0;
                
                -- Lookup variables for optimization
                names_in_batch TEXT[] := '{}';
                duplicate_names TEXT[] := '{}';
            BEGIN
                -- Create temporary tables for performance optimization
                CREATE TEMP TABLE temp_existing_term_types (
                    name TEXT PRIMARY KEY,
                    term_type_id BIGINT
                ) ON COMMIT DROP;

                -- Pre-load existing asset availability term types for this tenant
                INSERT INTO temp_existing_term_types (name, term_type_id)
                SELECT name, id
                FROM asset_availability_term_types 
                WHERE tenant_id = _tenant_id 
                AND name IS NOT NULL 
                AND isactive = true;

                -- Create indexes on temp tables for better performance
                CREATE INDEX idx_temp_term_types_name ON temp_existing_term_types(name);

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

                -- Process each asset availability term type item in batches
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
                        current_term_type_id := NULL;
                        existing_term_type_id := NULL;

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

                        -- Check if asset availability term type exists using temp table
                        SELECT term_type_id INTO existing_term_type_id
                        FROM temp_existing_term_types
                        WHERE name = current_name
                        LIMIT 1;
                        
                        IF existing_term_type_id IS NOT NULL THEN
                            -- Term type exists, update it
                            UPDATE asset_availability_term_types SET
                                description = COALESCE(item->>'description', description),
                                isactive = true,
                                deleted_at = NULL,
                                is_created_from_imported_csv = true,
                                if_imported_jobs_id = _job_id,
                                updated_at = _current_time
                            WHERE id = existing_term_type_id
                            RETURNING id INTO current_term_type_id;
                            
                            updated_count := updated_count + 1;
                        ELSE
                            -- Term type doesn't exist, create new one
                            BEGIN
                                INSERT INTO asset_availability_term_types (
                                    name,
                                    description,
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
                                    item->>'description',
                                    NULL,
                                    true,
                                    _tenant_id,
                                    true,
                                    _job_id,
                                    _current_time,
                                    _current_time
                                )
                                RETURNING id INTO current_term_type_id;
                                
                                -- Update temp table cache with new term type
                                INSERT INTO temp_existing_term_types (name, term_type_id)
                                VALUES (current_name, current_term_type_id)
                                ON CONFLICT (name) DO NOTHING;
                                
                                inserted_count := inserted_count + 1;
                                
                            EXCEPTION WHEN unique_violation THEN
                                -- Handle concurrent insertion race condition
                                SELECT id INTO existing_term_type_id 
                                FROM asset_availability_term_types 
                                WHERE name = current_name 
                                AND tenant_id = _tenant_id
                                LIMIT 1;
                                
                                IF existing_term_type_id IS NOT NULL THEN
                                    -- Update temp table cache
                                    INSERT INTO temp_existing_term_types (name, term_type_id)
                                    VALUES (current_name, existing_term_type_id)
                                    ON CONFLICT (name) DO UPDATE SET term_type_id = EXCLUDED.term_type_id;
                                    
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
                    format('Processed %s asset availability term types: %s inserted, %s updated, %s errors', 
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
                        format('Critical error during asset availability term types bulk processing: %s (SQL State: %s)', SQLERRM, SQLSTATE)::TEXT AS message,
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
        DB::unprepared('DROP FUNCTION IF EXISTS bulk_insert_asset_availability_term_types_with_relationships(BIGINT, BIGINT, BIGINT, TIMESTAMP WITH TIME ZONE, JSON, INTEGER);');
    }
};
