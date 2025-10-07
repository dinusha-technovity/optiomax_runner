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
                    WHERE proname = 'create_or_update_customer'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION create_or_update_customer(
                IN p_name TEXT,
                IN p_tenant_id BIGINT,
                IN p_id BIGINT DEFAULT NULL,
                IN p_national_id TEXT DEFAULT NULL,
                IN p_primary_contact_person TEXT DEFAULT NULL,
                IN p_designation TEXT DEFAULT NULL,
                IN p_phone_mobile TEXT DEFAULT NULL,
                IN p_phone_mobile_code_id BIGINT DEFAULT NULL,
                IN p_phone_landline TEXT DEFAULT NULL,
                IN p_phone_landline_code_id BIGINT DEFAULT NULL,
                IN p_phone_office TEXT DEFAULT NULL,
                IN p_phone_office_code_id BIGINT DEFAULT NULL,
                IN p_email TEXT DEFAULT NULL,
                IN p_address TEXT DEFAULT NULL,
                IN p_customer_type_id BIGINT DEFAULT NULL,
                IN p_billing_address TEXT DEFAULT NULL,
                IN p_payment_terms TEXT DEFAULT NULL,
                IN p_customer_rating SMALLINT DEFAULT 0,
                IN p_notes TEXT DEFAULT NULL,
                IN p_status TEXT DEFAULT 'pending',
                IN p_created_by BIGINT DEFAULT NULL,
                IN p_is_active BOOLEAN DEFAULT TRUE,
                IN p_customer_attachments JSONB DEFAULT NULL,
                IN p_customer_thumbnail_image JSONB DEFAULT NULL,
                IN p_customer_location_latitude TEXT DEFAULT NULL,
                IN p_customer_location_longitude TEXT DEFAULT NULL,
                IN p_current_time TIMESTAMPTZ DEFAULT now()
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                customer_id BIGINT,
                customer_code TEXT,
                log_id BIGINT,
                customer_data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_customer_id BIGINT;
                v_customer_code TEXT;
                v_exists BOOLEAN := FALSE;
                v_action_type TEXT;
                v_customer_data JSONB;
                v_log_success BOOLEAN := FALSE;
                v_error_message TEXT;
                v_old_customer_data JSONB;
                v_finalized_log_data JSONB;
                v_log_id BIGINT;
            BEGIN
                -- Validate required fields
                IF p_name IS NULL OR p_name = '' THEN
                    RETURN QUERY SELECT 
                        'ERROR'::TEXT,
                        'Customer name is required'::TEXT,
                        NULL::BIGINT,
                        NULL::TEXT,
                        NULL::BIGINT,
                        NULL::JSONB;
                    RETURN;
                END IF;

                IF p_tenant_id IS NULL THEN
                    RETURN QUERY SELECT 
                        'ERROR'::TEXT,
                        'Tenant ID is required'::TEXT,
                        NULL::BIGINT,
                        NULL::TEXT,
                        NULL::BIGINT,
                        NULL::JSONB;
                    RETURN;
                END IF;

                -- Validate customer rating range
                IF p_customer_rating < 0 OR p_customer_rating > 5 THEN
                    RETURN QUERY SELECT 
                        'ERROR'::TEXT,
                        'Customer rating must be between 0 and 5'::TEXT,
                        NULL::BIGINT,
                        NULL::TEXT,
                        NULL::BIGINT,
                        NULL::JSONB;
                    RETURN;
                END IF;

                -- Check if customer type exists
                IF p_customer_type_id IS NOT NULL THEN
                    IF NOT EXISTS (
                        SELECT 1 FROM customer_types 
                        WHERE id = p_customer_type_id 
                        AND deleted_at IS NULL
                    ) THEN
                        RETURN QUERY SELECT 
                            'ERROR'::TEXT,
                            'Invalid customer type ID'::TEXT,
                            NULL::BIGINT,
                            NULL::TEXT,
                            NULL::BIGINT,
                            NULL::JSONB;
                        RETURN;
                    END IF;
                END IF;

                -- Check if status exists
                IF p_status IS NULL OR p_status = '' THEN
                    RETURN QUERY SELECT 
                        'ERROR'::TEXT,
                        'Status is required'::TEXT,
                        NULL::BIGINT,
                        NULL::TEXT,
                        NULL::BIGINT,
                        NULL::JSONB;
                    RETURN;
                END IF;

                -- Check for duplicate email (if provided)
                IF p_email IS NOT NULL AND p_email != '' THEN
                    IF EXISTS (
                        SELECT 1 FROM customers 
                        WHERE email = p_email 
                        AND tenant_id = p_tenant_id
                        AND deleted_at IS NULL
                        AND (p_id IS NULL OR id != p_id)
                    ) THEN
                        RETURN QUERY SELECT 
                            'ERROR'::TEXT,
                            'Customer with this email already exists'::TEXT,
                            NULL::BIGINT,
                            NULL::TEXT,
                            NULL::BIGINT,
                            NULL::JSONB;
                        RETURN;
                    END IF;
                END IF;

                -- Check for duplicate national ID (if provided)
                IF p_national_id IS NOT NULL AND p_national_id != '' THEN
                    IF EXISTS (
                        SELECT 1 FROM customers 
                        WHERE national_id = p_national_id 
                        AND tenant_id = p_tenant_id
                        AND deleted_at IS NULL
                        AND (p_id IS NULL OR id != p_id)
                    ) THEN
                        RETURN QUERY SELECT 
                            'ERROR'::TEXT,
                            'Customer with this national ID already exists'::TEXT,
                            NULL::BIGINT,
                            NULL::TEXT,
                            NULL::BIGINT,
                            NULL::JSONB;
                        RETURN;
                    END IF;
                END IF;

                -- Check if updating existing customer
                IF p_id IS NOT NULL AND p_id > 0 THEN
                    SELECT EXISTS (
                        SELECT 1 FROM customers 
                        WHERE id = p_id 
                        AND tenant_id = p_tenant_id
                        AND deleted_at IS NULL
                    ) INTO v_exists;
                    
                    IF NOT v_exists THEN
                        RETURN QUERY SELECT 
                            'ERROR'::TEXT,
                            'Customer not found or access denied'::TEXT,
                            NULL::BIGINT,
                            NULL::TEXT,
                            NULL::BIGINT;
                        RETURN;
                    END IF;
                END IF;

                -- Update existing customer
                IF v_exists THEN

                    -- Get old customer data for logging
                    SELECT to_jsonb(customers.*) from customers WHERE id = p_id AND tenant_id = p_tenant_id INTO v_old_customer_data;
                    UPDATE customers SET
                        name = p_name,
                        national_id = p_national_id,
                        primary_contact_person = p_primary_contact_person,
                        designation = p_designation,
                        phone_mobile = p_phone_mobile,
                        phone_mobile_code_id = p_phone_mobile_code_id,
                        phone_landline = p_phone_landline,
                        phone_landline_code_id = p_phone_landline_code_id,
                        phone_office = p_phone_office,
                        phone_office_code_id = p_phone_office_code_id,
                        address = p_address,
                        customer_type_id = p_customer_type_id,
                        billing_address = p_billing_address,
                        payment_terms = p_payment_terms,
                        customer_rating = p_customer_rating,
                        notes = p_notes,
                        status = p_status,
                        created_by = p_created_by,
                        is_active = p_is_active,
                        customer_attachments = p_customer_attachments,
                        thumbnail_image = p_customer_thumbnail_image,
                        location_latitude = p_customer_location_latitude,
                        location_longitude = p_customer_location_longitude,
                        updated_at = p_current_time
                    WHERE id = p_id
                    RETURNING customers.id, customers.customer_code, to_jsonb(customers.*) INTO v_customer_id, v_customer_code, v_customer_data;

                    v_action_type := 'updated';

                    v_finalized_log_data := jsonb_build_object(
                        'old_data', v_old_customer_data,
                        'new_data', v_customer_data
                    );



                    -- Log the activity (with error handling)
                    BEGIN
                        SELECT log_activity(
                            'customer.' || v_action_type,
                            'Customer ' || v_action_type || ': ' || p_name || ' (' || v_customer_code || ')',
                            'customer',
                            v_customer_id,
                            'user',
                            p_created_by,
                            v_finalized_log_data,
                            p_tenant_id
                        ) INTO v_log_id;
                        v_log_success := TRUE;
                    EXCEPTION WHEN OTHERS THEN
                        v_log_success := FALSE;
                        v_error_message := 'Logging failed: ' || SQLERRM;
                    END;

                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT,
                        'Customer updated successfully'::TEXT,
                        v_customer_id,
                        v_customer_code,
                        v_log_id,
                        v_finalized_log_data;

                -- Create new customer
                ELSE
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
                        tenant_id,
                        created_by,
                        is_active,
                        customer_attachments,
                        thumbnail_image,
                        location_latitude,
                        location_longitude,
                        created_at,
                        updated_at
                    ) VALUES (
                        p_name,
                        p_national_id,
                        p_primary_contact_person,
                        p_designation,
                        p_phone_mobile,
                        p_phone_mobile_code_id,
                        p_phone_landline,
                        p_phone_landline_code_id,
                        p_phone_office,
                        p_phone_office_code_id,
                        p_email,
                        p_address,
                        p_customer_type_id,
                        p_billing_address,
                        p_payment_terms,
                        p_customer_rating,
                        p_notes,
                        p_status,
                        p_tenant_id,
                        p_created_by,
                        p_is_active,
                        p_customer_attachments,
                        p_customer_thumbnail_image,
                        p_customer_location_latitude,
                        p_customer_location_longitude,
                        p_current_time,
                        p_current_time
                    ) RETURNING customers.id, customers.customer_code, to_jsonb(customers.*) INTO v_customer_id, v_customer_code, v_customer_data;

                    v_action_type := 'created';

                    -- Log the activity (with error handling)
                    BEGIN
                        SELECT log_activity(
                            'customer.' || v_action_type,
                            'Customer ' || v_action_type || ': ' || p_name || ' (' || v_customer_code || ')',
                            'customer',
                            v_customer_id,
                            'user',
                            p_created_by,
                            v_customer_data,
                            p_tenant_id
                        ) INTO v_log_id;
                        v_log_success := TRUE;
                    EXCEPTION WHEN OTHERS THEN
                        v_log_success := FALSE;
                        v_error_message := 'Logging failed: ' || SQLERRM;
                    END;

                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT,
                        'Customer created successfully'::TEXT,
                        v_customer_id,
                        v_customer_code,
                        v_log_id,
                        v_customer_data;
                END IF;

            EXCEPTION
                WHEN OTHERS THEN
                    RETURN QUERY SELECT 
                        'ERROR'::TEXT,
                        ('Database error: ' || SQLERRM)::TEXT,
                        NULL::BIGINT,
                        NULL::TEXT,
                        NULL::BIGINT,
                        NULL::JSONB;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS create_or_update_customer(TEXT, BIGINT, BIGINT, TEXT, TEXT, TEXT, TEXT, BIGINT, TEXT, BIGINT, TEXT, BIGINT, TEXT, TEXT, BIGINT, TEXT, TEXT, SMALLINT, TEXT, TEXT, BIGINT, BOOLEAN, JSONB, TIMESTAMPTZ);");
    }
};
