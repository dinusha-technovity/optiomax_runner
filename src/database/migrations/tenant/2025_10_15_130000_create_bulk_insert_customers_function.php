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
                    WHERE proname = 'bulk_insert_customers_with_relationships'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            -- Function to handle customer bulk import with relationship management (Enterprise Optimized)
            CREATE OR REPLACE FUNCTION bulk_insert_customers_with_relationships(
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
                
                current_customer_id BIGINT;
                existing_customer_id BIGINT;
                
                -- Lookup variables
                customer_type_id_val BIGINT;
                phone_mobile_code_id_val BIGINT;
                phone_landline_code_id_val BIGINT;
                phone_office_code_id_val BIGINT;
                
                -- Batch processing variables
                current_email TEXT;
                batch_counter INTEGER := 0;
                
                -- Lookup variables for optimization
                emails_in_batch TEXT[] := '{}';
                duplicate_emails TEXT[] := '{}';
            BEGIN
                -- Create temporary tables for performance optimization
                CREATE TEMP TABLE temp_existing_customers (
                    email TEXT PRIMARY KEY,
                    customer_id BIGINT
                ) ON COMMIT DROP;
                
                CREATE TEMP TABLE temp_existing_customer_types (
                    type_name_lower TEXT PRIMARY KEY,
                    type_id BIGINT
                ) ON COMMIT DROP;

                -- Pre-load existing customers for this tenant
                INSERT INTO temp_existing_customers (email, customer_id)
                SELECT email, id
                FROM customers 
                WHERE tenant_id = _tenant_id 
                AND email IS NOT NULL 
                AND is_active = true;

                -- Pre-load existing customer types (remove tenant filter since they're global)
                INSERT INTO temp_existing_customer_types (type_name_lower, type_id)
                SELECT LOWER(name), id
                FROM customer_types;

                -- Create indexes on temp tables for better performance
                CREATE INDEX idx_temp_customers_email ON temp_existing_customers(email);
                CREATE INDEX idx_temp_customer_types_name ON temp_existing_customer_types(type_name_lower);

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

                -- Process each customer item in batches
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
                        current_customer_id := NULL;
                        existing_customer_id := NULL;
                        customer_type_id_val := NULL;
                        phone_mobile_code_id_val := NULL;
                        phone_landline_code_id_val := NULL;
                        phone_office_code_id_val := NULL;

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

                        -- Handle Customer Type lookup (only existing types, no creation)
                        IF (item->>'customer_type_name') IS NOT NULL AND (item->>'customer_type_name') != '' THEN
                            SELECT type_id INTO customer_type_id_val
                            FROM temp_existing_customer_types
                            WHERE type_name_lower = LOWER(item->>'customer_type_name')
                            LIMIT 1;
                            
                            -- If customer type not found, add error instead of creating
                            IF customer_type_id_val IS NULL THEN
                                error_count := error_count + 1;
                                error_details_array := array_append(error_details_array, 
                                    json_build_object(
                                        'row', processed_count,
                                        'email', current_email,
                                        'error', format('Invalid customer type: %s. Valid types are: Individual, Company, Department, Government', item->>'customer_type_name')
                                    )
                                );
                                CONTINUE;
                            END IF;
                        END IF;

                        -- Handle Country lookups for phone numbers (direct database lookup using phone_code)
                        IF (item->>'phone_mobile_country_code') IS NOT NULL AND (item->>'phone_mobile_country_code') != '' THEN
                            SELECT id INTO phone_mobile_code_id_val
                            FROM countries
                            WHERE phone_code = item->>'phone_mobile_country_code'
                            LIMIT 1;
                        END IF;

                        IF (item->>'phone_landline_country_code') IS NOT NULL AND (item->>'phone_landline_country_code') != '' THEN
                            SELECT id INTO phone_landline_code_id_val
                            FROM countries
                            WHERE phone_code = item->>'phone_landline_country_code'
                            LIMIT 1;
                        END IF;

                        IF (item->>'phone_office_country_code') IS NOT NULL AND (item->>'phone_office_country_code') != '' THEN
                            SELECT id INTO phone_office_code_id_val
                            FROM countries
                            WHERE phone_code = item->>'phone_office_country_code'
                            LIMIT 1;
                        END IF;

                        -- Check if customer exists using temp table
                        SELECT customer_id INTO existing_customer_id
                        FROM temp_existing_customers
                        WHERE email = current_email
                        LIMIT 1;
                        
                        IF existing_customer_id IS NOT NULL THEN
                            -- Customer exists, update it
                            UPDATE customers SET
                                name = COALESCE(item->>'name', name),
                                national_id = COALESCE(item->>'national_id', national_id),
                                primary_contact_person = COALESCE(item->>'primary_contact_person', primary_contact_person),
                                designation = COALESCE(item->>'designation', designation),
                                phone_mobile = COALESCE(item->>'phone_mobile', phone_mobile),
                                phone_mobile_code_id = COALESCE(phone_mobile_code_id_val, phone_mobile_code_id),
                                phone_landline = COALESCE(item->>'phone_landline', phone_landline),
                                phone_landline_code_id = COALESCE(phone_landline_code_id_val, phone_landline_code_id),
                                phone_office = COALESCE(item->>'phone_office', phone_office),
                                phone_office_code_id = COALESCE(phone_office_code_id_val, phone_office_code_id),
                                address = COALESCE(item->>'address', address),
                                customer_type_id = COALESCE(customer_type_id_val, customer_type_id),
                                billing_address = COALESCE(item->>'billing_address', billing_address),
                                payment_terms = COALESCE(item->>'payment_terms', payment_terms),
                                customer_rating = COALESCE((item->>'customer_rating')::INT, customer_rating),
                                notes = COALESCE(item->>'notes', notes),
                                status = COALESCE(item->>'status', status),
                                location_latitude = COALESCE(item->>'location_latitude', location_latitude),
                                location_longitude = COALESCE(item->>'location_longitude', location_longitude),
                                is_active = true,
                                deleted_at = NULL,
                                is_created_from_imported_csv = true,
                                if_imported_jobs_id = _job_id,
                                updated_at = _current_time
                            WHERE id = existing_customer_id
                            RETURNING id INTO current_customer_id;
                            
                            updated_count := updated_count + 1;
                        ELSE
                            -- Customer doesn't exist, create new one
                            BEGIN
                                INSERT INTO customers (
                                    name,
                                    national_id,
                                    primary_contact_person,
                                    designation,
                                    phone_mobile,
                                    phone_mobile_code_id,
                                    phone_landline,
                                    phone_landline_code_id,
                                    phone_office,
                                    phone_office_code_id,
                                    email,
                                    address,
                                    customer_type_id,
                                    billing_address,
                                    payment_terms,
                                    customer_rating,
                                    notes,
                                    status,
                                    location_latitude,
                                    location_longitude,
                                    deleted_at,
                                    created_by,
                                    is_active,
                                    is_created_from_imported_csv,
                                    if_imported_jobs_id,
                                    tenant_id,
                                    created_at,
                                    updated_at
                                )
                                VALUES (
                                    item->>'name',
                                    item->>'national_id',
                                    item->>'primary_contact_person',
                                    item->>'designation',
                                    item->>'phone_mobile',
                                    phone_mobile_code_id_val,
                                    item->>'phone_landline',
                                    phone_landline_code_id_val,
                                    item->>'phone_office',
                                    phone_office_code_id_val,
                                    current_email,
                                    item->>'address',
                                    customer_type_id_val,
                                    item->>'billing_address',
                                    item->>'payment_terms',
                                    COALESCE((item->>'customer_rating')::INT, 0),
                                    item->>'notes',
                                    COALESCE(item->>'status', 'active'),
                                    item->>'location_latitude',
                                    item->>'location_longitude',
                                    NULL,
                                    _created_by_user_id,
                                    true,
                                    true,
                                    _job_id,
                                    _tenant_id,
                                    _current_time,
                                    _current_time
                                )
                                RETURNING id INTO current_customer_id;
                                
                                -- Update temp table cache with new customer
                                INSERT INTO temp_existing_customers (email, customer_id)
                                VALUES (current_email, current_customer_id)
                                ON CONFLICT (email) DO NOTHING;
                                
                                inserted_count := inserted_count + 1;
                                
                            EXCEPTION WHEN unique_violation THEN
                                -- Handle concurrent insertion race condition
                                SELECT id INTO existing_customer_id 
                                FROM customers 
                                WHERE email = current_email 
                                AND tenant_id = _tenant_id
                                LIMIT 1;
                                
                                IF existing_customer_id IS NOT NULL THEN
                                    -- Update temp table cache
                                    INSERT INTO temp_existing_customers (email, customer_id)
                                    VALUES (current_email, existing_customer_id)
                                    ON CONFLICT (email) DO UPDATE SET customer_id = EXCLUDED.customer_id;
                                    
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
                            batch_counter := 0;
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
                    format('Processed %s customers: %s inserted, %s updated, %s errors', 
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
                        format('Critical error during customer bulk processing: %s (SQL State: %s)', SQLERRM, SQLSTATE)::TEXT AS message,
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
        DB::unprepared('DROP FUNCTION IF EXISTS bulk_insert_customers_with_relationships(BIGINT, BIGINT, BIGINT, TIMESTAMP WITH TIME ZONE, JSON, INTEGER);');
    }
};