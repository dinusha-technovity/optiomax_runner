<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<SQL
                CREATE OR REPLACE FUNCTION get_suppliers(
                    p_tenant_id BIGINT,
                    p_supplier_id INT DEFAULT NULL
                )
                RETURNS TABLE (
                    status TEXT,
                    message TEXT,
                    id BIGINT,
                    name TEXT,
                    contact_no JSON,
                    address TEXT,
                    description TEXT,
                    supplier_type TEXT,
                    supplier_reg_no TEXT,
                    supplier_reg_status TEXT,
                    supplier_asset_classes JSON,
                    supplier_rating BIGINT,
                    supplier_business_name TEXT,
                    supplier_business_register_no TEXT,
                    supplier_primary_email TEXT,
                    supplier_secondary_email TEXT,
                    supplier_br_attachment TEXT,
                    supplier_website TEXT,
                    supplier_tel_no TEXT,
                    contact_no_code BIGINT,
                    supplier_mobile TEXT,
                    mobile_no_code BIGINT,
                    supplier_fax TEXT,
                    supplier_city TEXT,
                    supplier_location_latitude TEXT,
                    supplier_location_longitude TEXT,
                    email TEXT
                )
                LANGUAGE plpgsql
                AS $$
            
        DECLARE
            supplier_count INT;
        BEGIN
            -- Validate tenant ID
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT AS status,
                    'Invalid tenant ID provided'::TEXT AS message,
                    NULL::BIGINT AS id,
                    NULL::TEXT AS name,
                    NULL::JSON AS contact_no,
                    NULL::TEXT AS address,
                    NULL::TEXT AS description,
                    NULL::TEXT AS supplier_type,
                    NULL::TEXT AS supplier_reg_no,
                    NULL::TEXT AS supplier_reg_status,
                    NULL::JSON AS supplier_asset_classes,
                    NULL::BIGINT AS supplier_rating,
                    NULL::TEXT AS supplier_business_name,
                    NULL::TEXT AS supplier_business_register_no,
                    NULL::TEXT AS supplier_primary_email,
                    NULL::TEXT AS supplier_secondary_email,
                    NULL::TEXT AS supplier_br_attachment,
                    NULL::TEXT AS supplier_website,
                    NULL::TEXT AS supplier_tel_no,
                    NULL::BIGINT AS contact_no_code,
                    NULL::TEXT AS supplier_mobile,
                    NULL::BIGINT AS mobile_no_code,
                    NULL::TEXT AS supplier_fax,
                    NULL::TEXT AS supplier_city,
                    NULL::TEXT AS supplier_location_latitude,
                    NULL::TEXT AS supplier_location_longitude,
                    NULL::TEXT AS email;
                RETURN;
            END IF;
        
            -- Validate supplier ID (optional)
            IF p_supplier_id IS NOT NULL AND p_supplier_id < 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT AS status,
                    'Invalid supplier ID provided'::TEXT AS message,
                    NULL::BIGINT AS id,
                    NULL::TEXT AS name,
                    NULL::JSON AS contact_no,
                    NULL::TEXT AS address,
                    NULL::TEXT AS description,
                    NULL::TEXT AS supplier_type,
                    NULL::TEXT AS supplier_reg_no,
                    NULL::TEXT AS supplier_reg_status,
                    NULL::JSON AS supplier_asset_classes,
                    NULL::BIGINT AS supplier_rating,
                    NULL::TEXT AS supplier_business_name,
                    NULL::TEXT AS supplier_business_register_no,
                    NULL::TEXT AS supplier_primary_email,
                    NULL::TEXT AS supplier_secondary_email,
                    NULL::TEXT AS supplier_br_attachment,
                    NULL::TEXT AS supplier_website,
                    NULL::TEXT AS supplier_tel_no,
                    NULL::BIGINT AS contact_no_code,
                    NULL::TEXT AS supplier_mobile,
                    NULL::BIGINT AS mobile_no_code,
                    NULL::TEXT AS supplier_fax,
                    NULL::TEXT AS supplier_city,
                    NULL::TEXT AS supplier_location_latitude,
                    NULL::TEXT AS supplier_location_longitude,
                    NULL::TEXT AS email;
                RETURN;
            END IF;
        
            -- Check if any matching suppliers exist
            SELECT COUNT(*) INTO supplier_count
            FROM suppliers
            WHERE (p_supplier_id IS NULL OR suppliers.id = p_supplier_id)
            AND suppliers.tenant_id = p_tenant_id
            AND suppliers.deleted_at IS NULL
            AND suppliers.isactive = TRUE;
        
            IF supplier_count = 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT AS status,
                    'No matching suppliers found'::TEXT AS message,
                    NULL::BIGINT AS id,
                    NULL::TEXT AS name,
                    NULL::JSON AS contact_no,
                    NULL::TEXT AS address,
                    NULL::TEXT AS description,
                    NULL::TEXT AS supplier_type,
                    NULL::TEXT AS supplier_reg_no,
                    NULL::TEXT AS supplier_reg_status,
                    NULL::JSON AS supplier_asset_classes,
                    NULL::BIGINT AS supplier_rating,
                    NULL::TEXT AS supplier_business_name,
                    NULL::TEXT AS supplier_business_register_no,
                    NULL::TEXT AS supplier_primary_email,
                    NULL::TEXT AS supplier_secondary_email,
                    NULL::TEXT AS supplier_br_attachment,
                    NULL::TEXT AS supplier_website,
                    NULL::TEXT AS supplier_tel_no,
                    NULL::BIGINT AS contact_no_code,
                    NULL::TEXT AS supplier_mobile,
                    NULL::BIGINT AS mobile_no_code,
                    NULL::TEXT AS supplier_fax,
                    NULL::TEXT AS supplier_city,
                    NULL::TEXT AS supplier_location_latitude,
                    NULL::TEXT AS supplier_location_longitude,
                    NULL::TEXT AS email;
                RETURN;
            END IF;
        
            -- Return the matching records
            RETURN QUERY
            SELECT
                'SUCCESS'::TEXT AS status,
                'Suppliers fetched successfully'::TEXT AS message,
                suppliers.id,
                suppliers.name::TEXT,
                suppliers.contact_no::JSON,
                suppliers.address::TEXT,
                suppliers.description::TEXT,
                suppliers.supplier_type::TEXT,
                suppliers.supplier_reg_no::TEXT,
                suppliers.supplier_reg_status::TEXT,
                suppliers.supplier_asset_classes::JSON,
                suppliers.supplier_rating,
                suppliers.supplier_business_name::TEXT,
                suppliers.supplier_business_register_no::TEXT,
                suppliers.supplier_primary_email::TEXT,
                suppliers.supplier_secondary_email::TEXT,
                suppliers.supplier_br_attachment::TEXT,
                suppliers.supplier_website::TEXT,
                suppliers.supplier_tel_no::TEXT,
                suppliers.contact_no_code,
                suppliers.supplier_mobile::TEXT,
                suppliers.mobile_no_code,
                suppliers.supplier_fax::TEXT,
                suppliers.supplier_city::TEXT,
                suppliers.supplier_location_latitude::TEXT,
                suppliers.supplier_location_longitude::TEXT,
                suppliers.email::TEXT
            FROM suppliers
            WHERE 
                (p_supplier_id IS NULL OR suppliers.id = p_supplier_id)
                AND suppliers.tenant_id = p_tenant_id
                AND suppliers.supplier_reg_status = 'APPROVED'
                AND suppliers.deleted_at IS NULL
                AND suppliers.isactive = TRUE; 

                -- last update: email retreival -: sachin karunarathna-: 25/04/22
            END;
        $$;
        SQL);        

    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_suppliers');
    }
};