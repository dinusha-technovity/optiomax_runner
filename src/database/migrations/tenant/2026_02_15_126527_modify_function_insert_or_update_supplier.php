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
                    WHERE proname = 'insert_or_update_supplier'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION insert_or_update_supplier(
                IN p_name VARCHAR(255),
                IN p_address VARCHAR(255),
                IN p_description TEXT,
                IN p_supplier_type VARCHAR(255),
                IN p_supplier_asset_classes JSON, 
                IN p_asset_categories JSON, 
                IN p_supplier_rating BIGINT,
                IN p_supplier_business_name VARCHAR(255),
                IN p_supplier_business_register_no VARCHAR(50),
                IN p_supplier_primary_email VARCHAR(255),
                IN p_supplier_secondary_email VARCHAR(255),
                IN p_supplier_br_attachment JSON,
                IN p_supplier_website VARCHAR(255),
                IN p_supplier_tel_no VARCHAR(50),
                IN p_supplier_mobile VARCHAR(50),
                IN p_supplier_fax VARCHAR(50),
                IN p_supplier_city VARCHAR(100),
                IN p_supplier_location_latitude VARCHAR(50),
                IN p_supplier_location_longitude VARCHAR(50),
                IN p_contact_no JSON,
                IN p_tenant_id BIGINT,
                IN p_current_time TIMESTAMP WITH TIME ZONE,
                IN p_id BIGINT DEFAULT NULL,
                IN p_supplier_register_status TEXT DEFAULT 'PENDING',
                IN p_contact_no_code BIGINT DEFAULT NULL,
                IN p_mobile_no_code BIGINT DEFAULT NULL,
                IN p_country BIGINT DEFAULT NULL,
                IN p_city VARCHAR(100) DEFAULT NULL,
                IN p_email VARCHAR(255) DEFAULT NULL,
                IN p_user_id BIGINT DEFAULT NULL,
                IN p_user_name VARCHAR(255) DEFAULT NULL,
                IN p_is_invited BOOLEAN DEFAULT FALSE

            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                supplier_id BIGINT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                curr_val INT;
                generated_supplier_id BIGINT;
                v_action_type TEXT;
                v_log_success BOOLEAN;
                v_error_message TEXT;
                v_old_data JSONB;
                v_new_data JSONB;
                v_log_data JSONB;
            BEGIN
                -- Determine action type and collect old data
                IF p_id IS NULL OR p_id = 0 THEN
                    v_action_type := 'created';
                    v_old_data := NULL;
                ELSE
                    v_action_type := 'updated';
                    SELECT jsonb_build_object(
                        'supplier_id', id,
                        'name', suppliers.name,
                        'address', suppliers.address,
                        'description', suppliers.description,
                        'supplier_type', suppliers.supplier_type,
                        'supplier_asset_classes', suppliers.supplier_asset_classes,
                        'supplier_rating', suppliers.supplier_rating,
                        'supplier_business_name', suppliers.supplier_business_name,
                        'supplier_business_register_no', suppliers.supplier_business_register_no,
                        'supplier_primary_email', suppliers.supplier_primary_email,
                        'supplier_secondary_email', suppliers.supplier_secondary_email,
                        'supplier_br_attachment', suppliers.supplier_br_attachment,
                        'supplier_website', suppliers.supplier_website,
                        'supplier_tel_no', suppliers.supplier_tel_no,
                        'supplier_mobile', suppliers.supplier_mobile,
                        'supplier_fax', suppliers.supplier_fax,
                        'supplier_city', suppliers.supplier_city,
                        'supplier_location_latitude', suppliers.supplier_location_latitude,
                        'supplier_location_longitude', suppliers.supplier_location_longitude,
                        'contact_no', suppliers.contact_no,
                        'supplier_reg_no', suppliers.supplier_reg_no,
                        'supplier_reg_status', suppliers.supplier_reg_status,
                        'tenant_id', suppliers.tenant_id,
                        'mobile_no_code', suppliers.mobile_no_code,
                        'contact_no_code', suppliers.contact_no_code,
                        'country', suppliers.country,
                        'city', suppliers.city,
                        'email', suppliers.email,
                        'asset_categories', suppliers.asset_categories
                    ) INTO v_old_data
                    FROM suppliers WHERE id = p_id;
                END IF;

                -- Validate input
                IF p_supplier_rating IS NULL THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Supplier rating cannot be null'::TEXT AS message,
                        NULL::BIGINT AS supplier_id;
                   
                    RETURN;
                END IF;

                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT AS supplier_id;
                       
                    RETURN;
                END IF;

                -- Check for duplicate email
                IF p_email IS NOT NULL AND p_email <> '' THEN
                    IF EXISTS (
                        SELECT 1 FROM suppliers 
                        WHERE email = p_email
                        AND id <> COALESCE(NULLIF(p_id, 0), 0)
                        AND tenant_id = p_tenant_id
                        AND email IS NOT NULL
                    ) THEN
                        RETURN QUERY SELECT 
                            'FAILURE'::TEXT AS status,
                            'Email address already exists'::TEXT AS message,
                            NULL::BIGINT AS supplier_id;
                        RETURN;
                    END IF;
                END IF;

               
                -- Insert or update the supplier
                IF p_id IS NULL OR p_id = 0 THEN
                    INSERT INTO suppliers (
                        name, address, description, supplier_type, created_at, updated_at,
                        supplier_asset_classes, supplier_rating, supplier_business_name,
                        supplier_business_register_no, supplier_primary_email, supplier_secondary_email,
                        supplier_br_attachment, supplier_website, supplier_tel_no, supplier_mobile,
                        supplier_fax, supplier_city, supplier_location_latitude, supplier_location_longitude,
                        contact_no, supplier_reg_status, tenant_id,
                        mobile_no_code, contact_no_code, country, city, email, asset_categories
                    )
                    VALUES (
                        p_name, p_address, p_description, p_supplier_type, p_current_time, p_current_time,
                        p_supplier_asset_classes, p_supplier_rating, p_supplier_business_name,
                        p_supplier_business_register_no, p_supplier_primary_email, p_supplier_secondary_email,
                        p_supplier_br_attachment, p_supplier_website, p_supplier_tel_no, p_supplier_mobile,
                        p_supplier_fax, p_supplier_city, p_supplier_location_latitude, p_supplier_location_longitude,
                        p_contact_no, p_supplier_register_status, p_tenant_id,
                        p_mobile_no_code, p_contact_no_code, p_country, p_city, p_email, p_asset_categories
                    )
                    RETURNING id INTO generated_supplier_id;

                    -- Build new data with supplier_id
                    v_new_data := jsonb_build_object(
                        'supplier_id', generated_supplier_id,
                        'name', p_name,
                        'address', p_address,
                        'description', p_description,
                        'supplier_type', p_supplier_type,
                        'supplier_asset_classes', p_supplier_asset_classes,
                        'supplier_rating', p_supplier_rating,
                        'supplier_business_name', p_supplier_business_name,
                        'supplier_business_register_no', p_supplier_business_register_no,
                        'supplier_primary_email', p_supplier_primary_email,
                        'supplier_secondary_email', p_supplier_secondary_email,
                        'supplier_br_attachment', p_supplier_br_attachment,
                        'supplier_website', p_supplier_website,
                        'supplier_tel_no', p_supplier_tel_no,
                        'supplier_mobile', p_supplier_mobile,
                        'supplier_fax', p_supplier_fax,
                        'supplier_city', p_supplier_city,
                        'supplier_location_latitude', p_supplier_location_latitude,
                        'supplier_location_longitude', p_supplier_location_longitude,
                        'contact_no', p_contact_no,
                        'supplier_reg_status', p_supplier_register_status,
                        'tenant_id', p_tenant_id,
                        'mobile_no_code', p_mobile_no_code,
                        'contact_no_code', p_contact_no_code,
                        'country', p_country,
                        'city', p_city,
                        'email', p_email,
                        'asset_categories', p_asset_categories
                    );

                    -- Build log data with supplier_id
                    v_log_data := jsonb_build_object(
                        'supplier_id', generated_supplier_id,
                        'new_data', v_new_data
                    );

                    -- Log activity only if insert was successful and user_id/name are not null
                    IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
                        BEGIN
                            PERFORM log_activity(
                                'supplier.created',
                                'Supplier created by ' || p_user_name || ': ' || p_name,
                                'supplier',
                                generated_supplier_id,
                                'user',
                                p_user_id,
                                v_log_data,
                                p_tenant_id
                            );
                            v_log_success := TRUE;
                        EXCEPTION WHEN OTHERS THEN
                            v_log_success := FALSE;
                            v_error_message := 'Logging failed: ' || SQLERRM;
                        END;
                    END IF;

                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status,
                        'Supplier added successfully'::TEXT AS message,
                        generated_supplier_id AS supplier_id;
                       
                ELSE
                    UPDATE suppliers
                    SET 
                        name = p_name,
                        address = p_address,
                        description = p_description,
                        supplier_type = p_supplier_type,
                        updated_at = p_current_time,
                        supplier_asset_classes = p_supplier_asset_classes,
                        supplier_business_name = p_supplier_business_name,
                        supplier_business_register_no = p_supplier_business_register_no,
                        supplier_primary_email = p_supplier_primary_email,
                        supplier_secondary_email = p_supplier_secondary_email,
                        supplier_br_attachment = p_supplier_br_attachment,
                        supplier_website = p_supplier_website,
                        supplier_tel_no = p_supplier_tel_no,
                        supplier_mobile = p_supplier_mobile,
                        supplier_fax = p_supplier_fax,
                        supplier_city = p_supplier_city,
                        supplier_location_latitude = p_supplier_location_latitude,
                        supplier_location_longitude = p_supplier_location_longitude,
                        contact_no = p_contact_no,
                        contact_no_code = p_contact_no_code,
                        mobile_no_code = p_mobile_no_code,
                        country = p_country, 
                        city = p_city,
                        supplier_reg_status = p_supplier_register_status,
                        email = p_email,
                        asset_categories = p_asset_categories
                    WHERE id = p_id
                    RETURNING id INTO generated_supplier_id;

                    IF NOT FOUND THEN
                        RETURN QUERY SELECT 
                            'FAILURE'::TEXT AS status,
                            'Supplier not found for update'::TEXT AS message,
                            NULL::BIGINT AS supplier_id;
                    ELSE
                        -- Build new data with supplier_id
                        v_new_data := jsonb_build_object(
                            'supplier_id', generated_supplier_id,
                            'name', p_name,
                            'address', p_address,
                            'description', p_description,
                            'supplier_type', p_supplier_type,
                            'supplier_asset_classes', p_supplier_asset_classes,
                            'supplier_rating', p_supplier_rating,
                            'supplier_business_name', p_supplier_business_name,
                            'supplier_business_register_no', p_supplier_business_register_no,
                            'supplier_primary_email', p_supplier_primary_email,
                            'supplier_secondary_email', p_supplier_secondary_email,
                            'supplier_br_attachment', p_supplier_br_attachment,
                            'supplier_website', p_supplier_website,
                            'supplier_tel_no', p_supplier_tel_no,
                            'supplier_mobile', p_supplier_mobile,
                            'supplier_fax', p_supplier_fax,
                            'supplier_city', p_supplier_city,
                            'supplier_location_latitude', p_supplier_location_latitude,
                            'supplier_location_longitude', p_supplier_location_longitude,
                            'contact_no', p_contact_no,
                            'supplier_reg_status', p_supplier_register_status,
                            'tenant_id', p_tenant_id,
                            'mobile_no_code', p_mobile_no_code,
                            'contact_no_code', p_contact_no_code,
                            'country', p_country,
                            'city', p_city,
                            'email', p_email,
                            'asset_categories', p_asset_categories
                        );

                        -- if user is invited supplire then it must be change the status of supplier_invites table
                        IF p_is_invited THEN
                            UPDATE supplier_invites
                            SET status = 'accepted',
                                updated_at = NOW()
                            WHERE supplier_invites.tenant_id = p_tenant_id
                            AND supplier_invites.email = p_email
                            AND supplier_invites.status = 'pending';
                        END IF;

                        -- Build log data with supplier_id
                        v_log_data := jsonb_build_object(
                            'supplier_id', generated_supplier_id,
                            'old_data', v_old_data,
                            'new_data', v_new_data
                        );

                        -- Log activity only if update was successful and user_id/name are not null
                        IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
                            BEGIN
                                PERFORM log_activity(
                                    'supplier.updated',
                                    'Supplier updated by ' || p_user_name || ': ' || p_name,
                                    'supplier',
                                    generated_supplier_id,
                                    'user',
                                    p_user_id,
                                    v_log_data,
                                    p_tenant_id
                                );
                                v_log_success := TRUE;
                            EXCEPTION WHEN OTHERS THEN
                                v_log_success := FALSE;
                                v_error_message := 'Logging failed: ' || SQLERRM;
                            END;
                        END IF;

                        RETURN QUERY SELECT 
                            'SUCCESS'::TEXT AS status,
                            'Supplier updated successfully'::TEXT AS message,
                            generated_supplier_id AS supplier_id;
                    END IF;
                END IF;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS insert_or_update_supplier(TEXT, BIGINT, BIGINT, BIGINT, BIGINT, TIMESTAMPTZ, BIGINT, TEXT);");
    }
};
