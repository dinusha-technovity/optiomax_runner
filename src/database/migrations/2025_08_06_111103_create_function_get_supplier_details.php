<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<SQL
            DROP FUNCTION IF EXISTS get_supplier_by_id(
                p_tenant_id BIGINT,
                p_supplier_id BIGINT
            );

            CREATE OR REPLACE FUNCTION get_supplier_by_id(
                p_tenant_id BIGINT,
                p_supplier_id BIGINT
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                name TEXT,
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
                supplier_br_attachment JSON,
                supplier_website TEXT,
                supplier_tel_no TEXT,
                contact_no_code BIGINT,
                supplier_mobile TEXT,
                mobile_no_code BIGINT,
                supplier_fax TEXT,
                supplier_city TEXT,
                supplier_location_latitude TEXT,
                supplier_location_longitude TEXT,
                contact_no JSON,
                email TEXT,
                asset_categories JSON
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Validation
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT, 'Invalid tenant ID provided'::TEXT,
                        NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::JSON, NULL::BIGINT,
                        NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::JSON, NULL::TEXT, NULL::TEXT,
                        NULL::BIGINT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::JSON, NULL::TEXT, NULL::JSON;
                    RETURN;
                END IF;

                IF p_supplier_id IS NULL OR p_supplier_id <= 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT, 'Invalid supplier ID provided'::TEXT,
                        NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::JSON, NULL::BIGINT,
                        NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::JSON, NULL::TEXT, NULL::TEXT,
                        NULL::BIGINT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::JSON, NULL::TEXT, NULL::JSON;
                    RETURN;
                END IF;

                -- Check existence
                IF NOT EXISTS (
                    SELECT 1 FROM suppliers s
                    WHERE s.id = p_supplier_id AND s.tenant_id = p_tenant_id
                        AND s.deleted_at IS NULL
                ) THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT, 'Supplier not found'::TEXT,
                        NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::JSON, NULL::BIGINT,
                        NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::JSON, NULL::TEXT, NULL::TEXT,
                        NULL::BIGINT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::JSON, NULL::TEXT, NULL::JSON;
                    RETURN;
                END IF;

                -- Return the supplier
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT,
                    'Supplier details fetched successfully'::TEXT,
                    s.id,
                    s.name::TEXT,
                    s.address::TEXT,
                    s.description::TEXT,
                    s.supplier_type::TEXT,
                    s.supplier_reg_no::TEXT,
                    s.supplier_reg_status::TEXT,
                    s.supplier_asset_classes::JSON,
                    s.supplier_rating,
                    s.supplier_business_name::TEXT,
                    s.supplier_business_register_no::TEXT,
                    s.supplier_primary_email::TEXT,
                    s.supplier_secondary_email::TEXT,
                    s.supplier_br_attachment::JSON,
                    s.supplier_website::TEXT,
                    s.supplier_tel_no::TEXT,
                    s.contact_no_code,
                    s.supplier_mobile::TEXT,
                    s.mobile_no_code,
                    s.supplier_fax::TEXT,
                    s.supplier_city::TEXT,
                    s.supplier_location_latitude::TEXT,
                    s.supplier_location_longitude::TEXT,
                    s.contact_no::JSON,
                    s.email::TEXT,
                    s.asset_categories::JSON
                FROM suppliers s
                WHERE s.id = p_supplier_id
                    AND s.tenant_id = p_tenant_id
                    AND s.deleted_at IS NULL;
            END;
            $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_supplier_by_id');
    }
};
