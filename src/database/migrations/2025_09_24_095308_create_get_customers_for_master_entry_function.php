<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_customers_for_master_entry(
                p_tenant_id BIGINT DEFAULT NULL,
                p_customer_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                customer_code TEXT,
                name TEXT,
                national_id TEXT,
                email TEXT,
                customer_type_id BIGINT,
                customer_type_name TEXT,
                customer_rating SMALLINT,
                notes TEXT,
                thumbnail_image JSONB
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Tenant ID validation
                IF p_tenant_id IS NOT NULL AND p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT, 
                        'Invalid tenant ID provided'::TEXT,
                        NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::SMALLINT, NULL::TEXT, NULL::JSONB;
                    RETURN;
                END IF;

                -- No customers found check
                IF NOT EXISTS (
                    SELECT 1 FROM customers c
                    WHERE (p_customer_id IS NULL OR c.id = p_customer_id)
                    AND (p_tenant_id IS NULL OR c.tenant_id = p_tenant_id)
                    AND c.status = 'APPROVED'
                    AND c.deleted_at IS NULL
                ) THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT, 
                        'No matching customers found'::TEXT,
                        NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::SMALLINT, NULL::TEXT, NULL::JSONB;
                    RETURN;
                END IF;

                -- Return matching customers with all columns and customer type name
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Customers fetched successfully'::TEXT AS message,
                    c.id,
                    c.customer_code::TEXT,
                    c.name::TEXT,
                    c.national_id::TEXT,
                    c.email::TEXT,
                    c.customer_type_id,
                    ct.name::TEXT AS customer_type_name,
                    c.customer_rating,
                    c.notes::TEXT,
                    c.thumbnail_image
                FROM customers c
                LEFT JOIN customer_types ct ON c.customer_type_id = ct.id
                WHERE (p_customer_id IS NULL OR c.id = p_customer_id)
                AND (p_tenant_id IS NULL OR c.tenant_id = p_tenant_id)
                AND c.status = 'APPROVED'
                AND c.deleted_at IS NULL;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_customers_for_master_entry(BIGINT, BIGINT);");
    }
};