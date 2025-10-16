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
                    WHERE proname = 'bulk_insert_suppliers_with_relationships'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            -- Function to handle supplier bulk import with relationship management (Enterprise Optimized)
            CREATE OR REPLACE FUNCTION bulk_insert_suppliers_with_relationships(
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
                
                current_supplier_id BIGINT;
                existing_supplier_id BIGINT;
                curr_val INT;
                new_supplier_reg_no VARCHAR(50);
                
                -- Asset category variables
                asset_categories_text TEXT;
                category_names TEXT[];
                category_name TEXT;
                asset_category_id_val BIGINT;
                asset_categories_json JSON[] := '{}';
                
                -- Batch processing variables
                current_email TEXT;
                batch_counter INTEGER := 0;
                
                -- Lookup variables for optimization
                emails_in_batch TEXT[] := '{}';
                duplicate_emails TEXT[] := '{}';
            BEGIN
                -- Create temporary tables for performance optimization
                CREATE TEMP TABLE temp_existing_suppliers (
                    email TEXT PRIMARY KEY,
                    supplier_id BIGINT
                ) ON COMMIT DROP;
                
                CREATE TEMP TABLE temp_existing_categories (
                    category_name_lower TEXT PRIMARY KEY,
                    category_id BIGINT
                ) ON COMMIT DROP;

                -- Pre-load existing suppliers for this tenant
                INSERT INTO temp_existing_suppliers (email, supplier_id)
                SELECT email, id
                FROM suppliers 
                WHERE tenant_id = _tenant_id 
                AND email IS NOT NULL 
                AND isactive = true;
                
                -- Pre-load existing asset categories for this tenant
                INSERT INTO temp_existing_categories (category_name_lower, category_id)
                SELECT LOWER(name), id
                FROM asset_categories 
                WHERE tenant_id = _tenant_id 
                AND isactive = true;

                -- Create indexes on temp tables for better performance
                CREATE INDEX idx_temp_suppliers_email ON temp_existing_suppliers(email);
                CREATE INDEX idx_temp_categories_name ON temp_existing_categories(category_name_lower);

                -- Pre-check for duplicate emails in the input data
                FOR item IN SELECT * FROM json_array_elements(_items)
                LOOP
                    current_email := item->>'email';
                    
                    IF current_email IS NOT NULL AND current_email != '' THEN
                        -- Check if this email already appeared in this batch
                        IF current_email = ANY(emails_in_batch) THEN
                            duplicate_emails := array_append(duplicate_emails, current_email);
                            error_count := error_count + 1;
                            error_details_array := array_append(error_details_array, 
                                json_build_object(
                                    'row', processed_count + 1,
                                    'email', current_email,
                                    'error', 'Duplicate email within CSV data'
                                )
                            );
                        ELSE
                            emails_in_batch := array_append(emails_in_batch, current_email);
                        END IF;
                    END IF;
                END LOOP;

                -- Process each supplier item in batches
                FOR item IN SELECT * FROM json_array_elements(_items)
                LOOP
                    processed_count := processed_count + 1;
                    batch_counter := batch_counter + 1;
                    current_email := item->>'email';
                    
                    -- Skip if this email was marked as duplicate
                    IF current_email = ANY(duplicate_emails) THEN
                        CONTINUE;
                    END IF;
                    
                    BEGIN
                        -- Reset variables for each item
                        current_supplier_id := NULL;
                        existing_supplier_id := NULL;
                        asset_categories_json := '{}';

                        -- Skip if no email provided
                        IF current_email IS NULL OR current_email = '' THEN
                            error_count := error_count + 1;
                            error_details_array := array_append(error_details_array, 
                                json_build_object(
                                    'row', processed_count,
                                    'email', current_email,
                                    'error', 'Email is required'
                                )
                            );
                            CONTINUE;
                        END IF;

                        -- Handle Asset Categories lookup/creation (Optimized with temp table)
                        IF (item->>'asset_categories') IS NOT NULL AND (item->>'asset_categories') != '' THEN
                            asset_categories_text := item->>'asset_categories';
                            category_names := string_to_array(asset_categories_text, ',');
                            
                            FOREACH category_name IN ARRAY category_names
                            LOOP
                                category_name := trim(category_name);
                                
                                IF category_name != '' THEN
                                    -- Check in pre-loaded temp table first
                                    SELECT category_id INTO asset_category_id_val
                                    FROM temp_existing_categories
                                    WHERE category_name_lower = LOWER(category_name)
                                    LIMIT 1;
                                    
                                    IF asset_category_id_val IS NULL THEN
                                        -- Category doesn't exist, create it
                                        INSERT INTO asset_categories (name, tenant_id, isactive, created_at, updated_at, created_by, is_created_from_imported_csv, if_imported_jobs_id)
                                        VALUES (category_name, _tenant_id, true, _current_time, _current_time, _created_by_user_id, true, _job_id)
                                        RETURNING id INTO asset_category_id_val;
                                        
                                        -- Update temp table cache
                                        INSERT INTO temp_existing_categories (category_name_lower, category_id)
                                        VALUES (LOWER(category_name), asset_category_id_val)
                                        ON CONFLICT (category_name_lower) DO NOTHING;
                                    END IF;
                                    
                                    asset_categories_json := array_append(asset_categories_json, 
                                        json_build_object('id', asset_category_id_val));
                                END IF;
                            END LOOP;
                        END IF;

                        -- Check if supplier exists using temp table
                        SELECT supplier_id INTO existing_supplier_id
                        FROM temp_existing_suppliers
                        WHERE email = current_email
                        LIMIT 1;
                        
                        IF existing_supplier_id IS NOT NULL THEN
                            -- Supplier exists, update it
                            UPDATE suppliers SET
                                name = COALESCE(item->>'name', name),
                                supplier_primary_email = COALESCE(item->>'supplier_primary_email', supplier_primary_email),
                                supplier_secondary_email = COALESCE(item->>'supplier_secondary_email', supplier_secondary_email),
                                supplier_type = COALESCE(item->>'supplier_type', supplier_type),
                                supplier_business_name = COALESCE(item->>'supplier_business_name', supplier_business_name),
                                supplier_business_register_no = COALESCE(item->>'supplier_business_register_no', supplier_business_register_no),
                                contact_no = CASE 
                                    WHEN item->>'contact_no' IS NOT NULL 
                                    THEN json_build_array(item->>'contact_no')
                                    ELSE contact_no 
                                END,
                                address = COALESCE(item->>'address', address),
                                description = COALESCE(item->>'description', description),
                                supplier_website = COALESCE(item->>'supplier_website', supplier_website),
                                supplier_tel_no = COALESCE(item->>'supplier_tel_no', supplier_tel_no),
                                supplier_mobile = COALESCE(item->>'supplier_mobile', supplier_mobile),
                                supplier_fax = COALESCE(item->>'supplier_fax', supplier_fax),
                                supplier_city = COALESCE(item->>'supplier_city', supplier_city),
                                supplier_location_latitude = COALESCE(item->>'supplier_location_latitude', supplier_location_latitude),
                                supplier_location_longitude = COALESCE(item->>'supplier_location_longitude', supplier_location_longitude),
                                asset_categories = CASE 
                                    WHEN array_length(asset_categories_json, 1) > 0 
                                    THEN array_to_json(asset_categories_json)
                                    ELSE asset_categories 
                                END,
                                supplier_reg_status = 'APPROVED',
                                isactive = true,
                                deleted_at = NULL,
                                created_by = COALESCE(created_by, _created_by_user_id),
                                is_created_from_imported_csv = true,
                                if_imported_jobs_id = _job_id,
                                updated_at = _current_time
                            WHERE id = existing_supplier_id
                            RETURNING id INTO current_supplier_id;
                            
                            updated_count := updated_count + 1;
                        ELSE
                            -- Supplier doesn't exist, create new one
                            BEGIN
                                SELECT nextval('supplier_id_seq') INTO curr_val;
                                new_supplier_reg_no := 'SUPPLIER-' || LPAD(curr_val::TEXT, 4, '0');

                                INSERT INTO suppliers (
                                    name, 
                                    email,
                                    supplier_primary_email,
                                    supplier_secondary_email,
                                    supplier_type, 
                                    supplier_business_name,
                                    supplier_business_register_no,
                                    contact_no,
                                    address,
                                    description,
                                    supplier_website,
                                    supplier_tel_no,
                                    supplier_mobile,
                                    supplier_fax,
                                    supplier_city,
                                    supplier_location_latitude,
                                    supplier_location_longitude,
                                    asset_categories,
                                    supplier_reg_status,
                                    supplier_reg_no,
                                    isactive,
                                    deleted_at,
                                    tenant_id,
                                    created_by,
                                    is_created_from_imported_csv,
                                    if_imported_jobs_id,
                                    created_at,
                                    updated_at
                                )
                                VALUES (
                                    item->>'name',
                                    current_email,
                                    COALESCE(item->>'supplier_primary_email', current_email),
                                    item->>'supplier_secondary_email',
                                    COALESCE(item->>'supplier_type', 'Individual'),
                                    item->>'supplier_business_name',
                                    item->>'supplier_business_register_no',
                                    CASE 
                                        WHEN item->>'contact_no' IS NOT NULL 
                                        THEN json_build_array(item->>'contact_no')
                                        ELSE NULL 
                                    END,
                                    item->>'address',
                                    item->>'description',
                                    item->>'supplier_website',
                                    item->>'supplier_tel_no',
                                    item->>'supplier_mobile',
                                    item->>'supplier_fax',
                                    item->>'supplier_city',
                                    item->>'supplier_location_latitude',
                                    item->>'supplier_location_longitude',
                                    CASE 
                                        WHEN array_length(asset_categories_json, 1) > 0 
                                        THEN array_to_json(asset_categories_json)
                                        ELSE NULL 
                                    END,
                                    'APPROVED',
                                    new_supplier_reg_no,
                                    true,
                                    NULL,
                                    _tenant_id,
                                    _created_by_user_id,
                                    true,
                                    _job_id,
                                    _current_time,
                                    _current_time
                                )
                                RETURNING id INTO current_supplier_id;
                                
                                -- Update temp table cache with new supplier
                                INSERT INTO temp_existing_suppliers (email, supplier_id)
                                VALUES (current_email, current_supplier_id)
                                ON CONFLICT (email) DO NOTHING;
                                
                                inserted_count := inserted_count + 1;
                                
                            EXCEPTION WHEN unique_violation THEN
                                -- Handle concurrent insertion race condition
                                SELECT id INTO existing_supplier_id 
                                FROM suppliers 
                                WHERE email = current_email 
                                AND tenant_id = _tenant_id
                                LIMIT 1;
                                
                                IF existing_supplier_id IS NOT NULL THEN
                                    -- Update temp table cache
                                    INSERT INTO temp_existing_suppliers (email, supplier_id)
                                    VALUES (current_email, existing_supplier_id)
                                    ON CONFLICT (email) DO UPDATE SET supplier_id = EXCLUDED.supplier_id;
                                    
                                    updated_count := updated_count + 1;
                                ELSE
                                    error_count := error_count + 1;
                                    error_details_array := array_append(error_details_array, 
                                        json_build_object(
                                            'row', processed_count,
                                            'email', current_email,
                                            'error', 'Concurrent modification detected'
                                        )
                                    );
                                END IF;
                            END;
                        END IF;

                        -- Commit in batches for better performance
                        IF batch_counter >= _batch_size THEN
                            -- Reset batch counter
                            batch_counter := 0;
                            -- Force a checkpoint for large batches
                            PERFORM pg_advisory_lock(_tenant_id);
                            PERFORM pg_advisory_unlock(_tenant_id);
                        END IF;

                    EXCEPTION WHEN OTHERS THEN
                        error_count := error_count + 1;
                        error_details_array := array_append(error_details_array, 
                            json_build_object(
                                'row', processed_count,
                                'email', current_email,
                                'error', SQLERRM,
                                'sql_state', SQLSTATE
                            )
                        );
                    END;
                END LOOP;

                -- Return comprehensive results
                RETURN QUERY SELECT
                    'SUCCESS'::TEXT AS status,
                    format('Processed %s suppliers: %s inserted, %s updated, %s errors', 
                        processed_count, inserted_count, updated_count, error_count)::TEXT AS message,
                    processed_count AS total_processed,
                    inserted_count AS total_inserted,
                    updated_count AS total_updated,
                    error_count AS total_errors,
                    json_build_object(
                        'batch_size', _batch_size,
                        'cache_hits', processed_count - error_count,
                        'processing_time_ms', extract(epoch from (clock_timestamp() - _current_time)) * 1000,
                        'duplicate_emails_found', array_length(duplicate_emails, 1),
                        'optimization_level', 'enterprise'
                    ) AS batch_results,
                    array_to_json(error_details_array) AS error_details;

            EXCEPTION
                WHEN OTHERS THEN
                    RETURN QUERY SELECT
                        'ERROR'::TEXT AS status,
                        format('Critical error during supplier bulk processing: %s (SQL State: %s)', SQLERRM, SQLSTATE)::TEXT AS message,
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
        DB::unprepared('DROP FUNCTION IF EXISTS bulk_insert_suppliers_with_relationships(BIGINT, BIGINT, BIGINT, TIMESTAMP WITH TIME ZONE, JSON, INTEGER);');
    }
};