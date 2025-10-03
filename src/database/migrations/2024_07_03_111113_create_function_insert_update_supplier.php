<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // DB::unprepared(
        //     "CREATE OR REPLACE PROCEDURE STORE_PROCEDURE_INSERT_OR_UPDATE_SUPPLIER(
        //         p_name VARCHAR(255),
        //         p_address VARCHAR(255),
        //         p_description TEXT,
        //         p_supplier_type VARCHAR(255),
        //         p_supplier_asset_classes JSON, 
        //         p_supplier_rating BIGINT,
        //         p_supplier_business_name VARCHAR(255),
        //         p_supplier_business_register_no VARCHAR(50),
        //         p_supplier_primary_email VARCHAR(255),
        //         p_supplier_secondary_email VARCHAR(255),
        //         p_supplier_br_attachment VARCHAR(255),
        //         p_supplier_website VARCHAR(255),
        //         p_supplier_tel_no VARCHAR(50),
        //         p_supplier_mobile VARCHAR(50),
        //         p_supplier_fax VARCHAR(50),
        //         p_supplier_city VARCHAR(100),
        //         p_supplier_location_latitude VARCHAR(50),
        //         p_supplier_location_longitude VARCHAR(50),
        //         p_contact_no JSON,
        //         p_tenant_id BIGINT,
        //         p_id BIGINT DEFAULT NULL,
        //         p_supplier_register_status TEXT DEFAULT 'PENDING'
        //     ) LANGUAGE plpgsql
        //     AS $$
        //     DECLARE
        //         curr_val INT;
        //         supplier_id TEXT;
        //         return_supplier_id BIGINT;
        //     BEGIN
        //         DROP TABLE IF EXISTS supplier_add_response_from_store_procedure;
        //         CREATE TEMP TABLE supplier_add_response_from_store_procedure (
        //             status TEXT,
        //             message TEXT,
        //             supplier_id BIGINT DEFAULT 0
        //         );

        //         IF p_supplier_rating IS NULL THEN
        //             RAISE EXCEPTION 'Supplier rating cannot be null';
        //         END IF;

        //         SELECT nextval('supplier_id_seq') INTO curr_val;
        //         supplier_id := 'SUPPLIER-' || LPAD(curr_val::TEXT, 4, '0');

        //         IF p_id IS NULL OR p_id = 0 THEN
        //             INSERT INTO suppliers (
        //                 name, address, description, supplier_type, created_at, updated_at,
        //                 supplier_asset_classes, supplier_rating, supplier_business_name,
        //                 supplier_business_register_no, supplier_primary_email, supplier_secondary_email,
        //                 supplier_br_attachment, supplier_website, supplier_tel_no, supplier_mobile,
        //                 supplier_fax, supplier_city, supplier_location_latitude, supplier_location_longitude,
        //                 contact_no, supplier_reg_no, supplier_reg_status, tenant_id
        //             ) VALUES (
        //                 p_name, p_address, p_description, p_supplier_type, NOW(), NOW(),
        //                 p_supplier_asset_classes, p_supplier_rating, p_supplier_business_name,
        //                 p_supplier_business_register_no, p_supplier_primary_email, p_supplier_secondary_email,
        //                 p_supplier_br_attachment, p_supplier_website, p_supplier_tel_no, p_supplier_mobile,
        //                 p_supplier_fax, p_supplier_city, p_supplier_location_latitude, p_supplier_location_longitude,
        //                 p_contact_no, supplier_id, p_supplier_register_status, p_tenant_id
        //             ) RETURNING id INTO return_supplier_id;

        //             INSERT INTO supplier_add_response_from_store_procedure (status, message, supplier_id)
        //             VALUES ('SUCCESS', 'Supplier Added successfully', return_supplier_id);
        //         ELSE
        //             UPDATE suppliers
        //             SET 
        //                 name = p_name,
        //                 address = p_address,
        //                 description = p_description,
        //                 supplier_type = p_supplier_type,
        //                 updated_at = NOW(),
        //                 supplier_asset_classes = p_supplier_asset_classes,
        //                 supplier_rating = p_supplier_rating,
        //                 supplier_business_name = p_supplier_business_name,
        //                 supplier_business_register_no = p_supplier_business_register_no,
        //                 supplier_primary_email = p_supplier_primary_email,
        //                 supplier_secondary_email = p_supplier_secondary_email,
        //                 supplier_br_attachment = p_supplier_br_attachment,
        //                 supplier_website = p_supplier_website,
        //                 supplier_tel_no = p_supplier_tel_no,
        //                 supplier_mobile = p_supplier_mobile,
        //                 supplier_fax = p_supplier_fax,
        //                 supplier_city = p_supplier_city,
        //                 supplier_location_latitude = p_supplier_location_latitude,
        //                 supplier_location_longitude = p_supplier_location_longitude,
        //                 contact_no = p_contact_no
        //             WHERE id = p_id RETURNING id INTO return_supplier_id;

        //             INSERT INTO supplier_add_response_from_store_procedure (status, message, supplier_id)
        //                 VALUES ('SUCCESS', 'Supplier updated successfully', return_supplier_id);
                    
        //             IF NOT FOUND THEN
        //                 INSERT INTO suppliers (
        //                     name, address, description, supplier_type, created_at, updated_at,
        //                     supplier_asset_classes, supplier_rating, supplier_business_name,
        //                     supplier_business_register_no, supplier_primary_email, supplier_secondary_email,
        //                     supplier_br_attachment, supplier_website, supplier_tel_no, supplier_mobile,
        //                     supplier_fax, supplier_city, supplier_location_latitude, supplier_location_longitude,
        //                     contact_no, supplier_reg_no, supplier_reg_status
        //                 ) VALUES (
        //                     p_name, p_address, p_description, p_supplier_type, NOW(), NOW(),
        //                     p_supplier_asset_classes, p_supplier_rating, p_supplier_business_name,
        //                     p_supplier_business_register_no, p_supplier_primary_email, p_supplier_secondary_email,
        //                     p_supplier_br_attachment, p_supplier_website, p_supplier_tel_no, p_supplier_mobile,
        //                     p_supplier_fax, p_supplier_city, p_supplier_location_latitude, p_supplier_location_longitude,
        //                     p_contact_no, supplier_id, p_supplier_register_status
        //                 ) RETURNING id INTO return_supplier_id;

        //                 INSERT INTO supplier_add_response_from_store_procedure (status, message, supplier_id)
        //                 VALUES ('SUCCESS', 'Supplier Added successfully', return_supplier_id);
        //             END IF;
        //         END IF;
        //     END;
        //     $$;"
        // );

        // DB::unprepared(
        //     "CREATE OR REPLACE PROCEDURE STORE_PROCEDURE_INSERT_OR_UPDATE_SUPPLIER(
        //         p_name VARCHAR(255),
        //         p_address VARCHAR(255),
        //         p_description TEXT,
        //         p_supplier_type VARCHAR(255),
        //         p_supplier_asset_classes JSON, 
        //         p_supplier_rating BIGINT,
        //         p_supplier_business_name VARCHAR(255),
        //         p_supplier_business_register_no VARCHAR(50),
        //         p_supplier_primary_email VARCHAR(255),
        //         p_supplier_secondary_email VARCHAR(255),
        //         p_supplier_br_attachment VARCHAR(255),
        //         p_supplier_website VARCHAR(255),
        //         p_supplier_tel_no VARCHAR(50),
        //         p_supplier_mobile VARCHAR(50),
        //         p_supplier_fax VARCHAR(50),
        //         p_supplier_city VARCHAR(100),
        //         p_supplier_location_latitude VARCHAR(50),
        //         p_supplier_location_longitude VARCHAR(50),
        //         p_contact_no JSON,
        //         p_tenant_id BIGINT,
        //         p_id BIGINT DEFAULT NULL,
        //         p_supplier_register_status TEXT DEFAULT 'PENDING'
        //     ) LANGUAGE plpgsql
        //     AS $$
        //     DECLARE
        //         curr_val INT;
        //         supplier_id TEXT;
        //         return_supplier_id BIGINT;
        //         supplier_reg_no VARCHAR(50);
        //     BEGIN
        //         DROP TABLE IF EXISTS supplier_add_response_from_store_procedure;
        //         CREATE TEMP TABLE supplier_add_response_from_store_procedure (
        //             status TEXT,
        //             message TEXT,
        //             supplier_id BIGINT DEFAULT 0,
        //             supplier_reg_no VARCHAR(50)
        //         );

        //         IF p_supplier_rating IS NULL THEN
        //             RAISE EXCEPTION 'Supplier rating cannot be null';
        //         END IF;

        //         SELECT nextval('supplier_id_seq') INTO curr_val;
        //         supplier_id := 'SUPPLIER-' || LPAD(curr_val::TEXT, 4, '0');

        //         IF p_id IS NULL OR p_id = 0 THEN
        //             INSERT INTO suppliers (
        //                 name, address, description, supplier_type, created_at, updated_at,
        //                 supplier_asset_classes, supplier_rating, supplier_business_name,
        //                 supplier_business_register_no, supplier_primary_email, supplier_secondary_email,
        //                 supplier_br_attachment, supplier_website, supplier_tel_no, supplier_mobile,
        //                 supplier_fax, supplier_city, supplier_location_latitude, supplier_location_longitude,
        //                 contact_no, supplier_reg_no, supplier_reg_status, tenant_id
        //             ) VALUES (
        //                 p_name, p_address, p_description, p_supplier_type, NOW(), NOW(),
        //                 p_supplier_asset_classes, p_supplier_rating, p_supplier_business_name,
        //                 p_supplier_business_register_no, p_supplier_primary_email, p_supplier_secondary_email,
        //                 p_supplier_br_attachment, p_supplier_website, p_supplier_tel_no, p_supplier_mobile,
        //                 p_supplier_fax, p_supplier_city, p_supplier_location_latitude, p_supplier_location_longitude,
        //                 p_contact_no, supplier_id, p_supplier_register_status, p_tenant_id
        //             ) RETURNING id, suppliers.supplier_reg_no INTO return_supplier_id, supplier_reg_no;

        //             INSERT INTO supplier_add_response_from_store_procedure (status, message, supplier_id, supplier_reg_no)
        //             VALUES ('SUCCESS', 'Supplier Added successfully', return_supplier_id, supplier_reg_no);
        //         ELSE
        //             UPDATE suppliers
        //             SET 
        //                 name = p_name,
        //                 address = p_address,
        //                 description = p_description,
        //                 supplier_type = p_supplier_type,
        //                 updated_at = NOW(),
        //                 supplier_asset_classes = p_supplier_asset_classes,
        //                 supplier_rating = p_supplier_rating,
        //                 supplier_business_name = p_supplier_business_name,
        //                 supplier_business_register_no = p_supplier_business_register_no,
        //                 supplier_primary_email = p_supplier_primary_email,
        //                 supplier_secondary_email = p_supplier_secondary_email,
        //                 supplier_br_attachment = p_supplier_br_attachment,
        //                 supplier_website = p_supplier_website,
        //                 supplier_tel_no = p_supplier_tel_no,
        //                 supplier_mobile = p_supplier_mobile,
        //                 supplier_fax = p_supplier_fax,
        //                 supplier_city = p_supplier_city,
        //                 supplier_location_latitude = p_supplier_location_latitude,
        //                 supplier_location_longitude = p_supplier_location_longitude,
        //                 contact_no = p_contact_no
        //             WHERE id = p_id RETURNING id, suppliers.supplier_reg_no INTO return_supplier_id, supplier_reg_no;

        //             INSERT INTO supplier_add_response_from_store_procedure (status, message, supplier_id, supplier_reg_no)
        //                 VALUES ('SUCCESS', 'Supplier updated successfully', return_supplier_id, supplier_reg_no);
                    
        //             IF NOT FOUND THEN
        //                 INSERT INTO suppliers (
        //                     name, address, description, supplier_type, created_at, updated_at,
        //                     supplier_asset_classes, supplier_rating, supplier_business_name,
        //                     supplier_business_register_no, supplier_primary_email, supplier_secondary_email,
        //                     supplier_br_attachment, supplier_website, supplier_tel_no, supplier_mobile,
        //                     supplier_fax, supplier_city, supplier_location_latitude, supplier_location_longitude,
        //                     contact_no, supplier_reg_no, supplier_reg_status
        //                 ) VALUES (
        //                     p_name, p_address, p_description, p_supplier_type, NOW(), NOW(),
        //                     p_supplier_asset_classes, p_supplier_rating, p_supplier_business_name,
        //                     p_supplier_business_register_no, p_supplier_primary_email, p_supplier_secondary_email,
        //                     p_supplier_br_attachment, p_supplier_website, p_supplier_tel_no, p_supplier_mobile,
        //                     p_supplier_fax, p_supplier_city, p_supplier_location_latitude, p_supplier_location_longitude,
        //                     p_contact_no, supplier_id, p_supplier_register_status
        //                 ) RETURNING id, suppliers.supplier_reg_no INTO return_supplier_id, supplier_reg_no;

        //                 INSERT INTO supplier_add_response_from_store_procedure (status, message, supplier_id, supplier_reg_no)
        //                 VALUES ('SUCCESS', 'Supplier Added successfully', return_supplier_id, supplier_reg_no);
        //             END IF;
        //         END IF;
        //     END;
        //     $$;"
        // ); 

         DB::unprepared(<<<SQL
                CREATE OR REPLACE FUNCTION insert_or_update_supplier(
                    IN p_name VARCHAR(255),
                    IN p_address VARCHAR(255),
                    IN p_description TEXT,
                    IN p_supplier_type VARCHAR(255),
                    IN p_supplier_asset_classes JSON, 
                    IN p_supplier_rating BIGINT,
                    IN p_supplier_business_name VARCHAR(255),
                    IN p_supplier_business_register_no VARCHAR(50),
                    IN p_supplier_primary_email VARCHAR(255),
                    IN p_supplier_secondary_email VARCHAR(255),
                    IN p_supplier_br_attachment VARCHAR(255),
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
                    IN p_contact_no_code BIGINT DEFAULT NULL, -- New parameter for contact_no_code
                    IN p_mobile_no_code BIGINT DEFAULT NULL,
                    IN p_country BIGINT DEFAULT NULL, -- New parameter for country
                    IN p_city VARCHAR(100) DEFAULT NULL, -- New parameter for city
                    IN p_email VARCHAR(255) DEFAULT NULL -- New parameter for city
                )
                RETURNS TABLE (
                    status TEXT,
                    message TEXT,
                    supplier_id BIGINT,
                    supplier_reg_no VARCHAR(50)
                )
                LANGUAGE plpgsql
                AS $$
                DECLARE
                    curr_val INT;
                    generated_supplier_id BIGINT;
                    new_supplier_reg_no VARCHAR(50);
                BEGIN
                    -- Validate input
                    IF p_supplier_rating IS NULL THEN
                        RETURN QUERY SELECT 
                            'FAILURE'::TEXT AS status,
                            'Supplier rating cannot be null'::TEXT AS message,
                            NULL::BIGINT AS supplier_id,
                            NULL::VARCHAR AS supplier_reg_no;
                        RETURN;
                    END IF;

                    IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                        RETURN QUERY SELECT 
                            'FAILURE'::TEXT AS status,
                            'Invalid tenant ID provided'::TEXT AS message,
                            NULL::BIGINT AS supplier_id,
                            NULL::VARCHAR AS supplier_reg_no;
                        RETURN;
                    END IF;

                    -- Generate supplier registration number if needed
                    IF p_id IS NULL OR p_id = 0 THEN
                        SELECT nextval('supplier_id_seq') INTO curr_val;
                        new_supplier_reg_no := 'SUPPLIER-' || LPAD(curr_val::TEXT, 4, '0');
                    END IF;

                    -- Insert or update the supplier
                    IF p_id IS NULL OR p_id = 0 THEN
                        INSERT INTO suppliers (
                            name, address, description, supplier_type, created_at, updated_at,
                            supplier_asset_classes, supplier_rating, supplier_business_name,
                            supplier_business_register_no, supplier_primary_email, supplier_secondary_email,
                            supplier_br_attachment, supplier_website, supplier_tel_no, supplier_mobile,
                            supplier_fax, supplier_city, supplier_location_latitude, supplier_location_longitude,
                            contact_no, supplier_reg_no, supplier_reg_status, tenant_id,
                            mobile_no_code, contact_no_code, country, city, email -- Add new columns here
                        )
                        VALUES (
                            p_name, p_address, p_description, p_supplier_type, p_current_time, p_current_time,
                            p_supplier_asset_classes, p_supplier_rating, p_supplier_business_name,
                            p_supplier_business_register_no, p_supplier_primary_email, p_supplier_secondary_email,
                            p_supplier_br_attachment, p_supplier_website, p_supplier_tel_no, p_supplier_mobile,
                            p_supplier_fax, p_supplier_city, p_supplier_location_latitude, p_supplier_location_longitude,
                            p_contact_no, new_supplier_reg_no, p_supplier_register_status, p_tenant_id,
                            p_mobile_no_code, p_contact_no_code, p_country, p_city, p_email -- Add new column values here
                        )
                        RETURNING id, suppliers.supplier_reg_no INTO generated_supplier_id, new_supplier_reg_no;

                        RETURN QUERY SELECT 
                            'SUCCESS'::TEXT AS status,
                            'Supplier added successfully'::TEXT AS message,
                            generated_supplier_id AS supplier_id,
                            new_supplier_reg_no AS supplier_reg_no;
                    ELSE
                        UPDATE suppliers
                        SET 
                            name = p_name,
                            address = p_address,
                            description = p_description,
                            supplier_type = p_supplier_type,
                            updated_at = p_current_time,
                            supplier_asset_classes = p_supplier_asset_classes,
                            supplier_rating = p_supplier_rating,
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
                            contact_no_code = p_contact_no_code, -- Update the new columns here
                            mobile_no_code = p_mobile_no_code,
                            country = p_country, 
                            city = p_city,
                            supplier_reg_status = p_supplier_register_status,
                            email = p_email
                        WHERE id = p_id
                        RETURNING id, suppliers.supplier_reg_no INTO generated_supplier_id, new_supplier_reg_no;

                        IF NOT FOUND THEN
                            RETURN QUERY SELECT 
                                'FAILURE'::TEXT AS status,
                                'Supplier not found for update'::TEXT AS message,
                                NULL::BIGINT AS supplier_id,
                                NULL::VARCHAR AS supplier_reg_no;
                        ELSE
                            RETURN QUERY SELECT 
                                'SUCCESS'::TEXT AS status,
                                'Supplier updated successfully'::TEXT AS message,
                                generated_supplier_id AS supplier_id,
                                new_supplier_reg_no AS supplier_reg_no;
                        END IF;
                    END IF;
                END;
                $$;
                SQL);

    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS insert_or_update_supplier');
    }
};