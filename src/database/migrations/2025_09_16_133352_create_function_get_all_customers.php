<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            -- Create function to get all customers
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_all_customers'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION get_all_customers(
                IN p_user_id BIGINT DEFAULT NULL,
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_count INT DEFAULT 0, -- if 0 then return all
                IN p_offset INT DEFAULT 0,
                IN p_search TEXT DEFAULT NULL,
                IN p_status TEXT DEFAULT NULL,
                IN p_customer_type_id BIGINT DEFAULT NULL,
                IN p_is_active BOOLEAN DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                total_count BIGINT,
                returned_count BIGINT,
                remaining_count BIGINT,
                customer_list JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                all_customers JSONB;
                v_total_count BIGINT := 0;
                v_returned_count BIGINT := 0;
                v_remaining_count BIGINT := 0;
                v_limit INT;
            BEGIN
                -- Validate tenant_id is provided
                IF p_tenant_id IS NULL THEN
                    RETURN QUERY SELECT 
                        'ERROR'::TEXT,
                        'Tenant ID is required'::TEXT,
                        0::BIGINT,
                        0::BIGINT,
                        0::BIGINT,
                        '[]'::JSONB;
                    RETURN;
                END IF;

                -- Set limit (0 means no limit)
                v_limit := CASE WHEN p_count = 0 THEN NULL ELSE p_count END;

                -- Get total count first
                SELECT COUNT(*)
                INTO v_total_count
                FROM customers c
                LEFT JOIN customer_types ct ON c.customer_type_id = ct.id
                WHERE c.tenant_id = p_tenant_id
                AND c.deleted_at IS NULL
                AND (p_user_id IS NULL OR c.created_by = p_user_id)
                AND (p_search IS NULL OR (
                    c.name ILIKE '%' || p_search || '%' OR
                    c.customer_code ILIKE '%' || p_search || '%' OR
                    c.email ILIKE '%' || p_search || '%' OR
                    c.primary_contact_person ILIKE '%' || p_search || '%'
                ))
                AND (p_status IS NULL OR c.status = p_status)
                AND (p_customer_type_id IS NULL OR c.customer_type_id = p_customer_type_id)
                AND (p_is_active IS NULL OR c.is_active = p_is_active);

                -- Get customers with pagination and filters
                SELECT COALESCE(jsonb_agg(customer_data), '[]'::jsonb)
                INTO all_customers
                FROM (
                    SELECT jsonb_build_object(
                        'id', c.id,
                        'name', c.name,
                        'customer_code', c.customer_code,
                        'national_id', c.national_id,
                        'primary_contact_person', c.primary_contact_person,
                        'designation', c.designation,
                        'phone_mobile', c.phone_mobile,
                        'phone_mobile_code_id', c.phone_mobile_code_id,
                        'phone_landline', c.phone_landline,
                        'phone_landline_code_id', c.phone_landline_code_id,
                        'phone_office', c.phone_office,
                        'phone_office_code_id', c.phone_office_code_id,
                        'email', c.email,
                        'address', c.address,
                        'billing_address', c.billing_address,
                        'payment_terms', c.payment_terms,
                        'customer_rating', c.customer_rating,
                        'notes', c.notes,
                        'is_active', c.is_active,
                        'customer_attachments', c.customer_attachments,
                        'customer_type', jsonb_build_object(
                            'id', ct.id,
                            'name', ct.name,
                            'description', ct.description
                        ),
                        'status', c.status,
                        'created_at', c.created_at,
                        'created_by', json_build_object(
                            'id', u.id,
                            'name', u.name,
                            'email', u.email
                        ),
                        'is_updated', c.is_updated,
                        'approver_id', c.approver_id,
                        'thumbnail_images', c.thumbnail_image,
                        'supplier_location_latitude', c.location_latitude,
                        'supplier_location_longitude', c.location_longitude,
                        'updated_at', c.updated_at
                    ) AS customer_data
                    FROM customers c
                    LEFT JOIN customer_types ct ON c.customer_type_id = ct.id AND ct.deleted_at IS NULL
                    LEFT JOIN users u ON c.created_by = u.id
                    WHERE c.tenant_id = p_tenant_id
                    AND c.deleted_at IS NULL
                    AND c.is_active = TRUE
                    AND (p_user_id IS NULL OR c.created_by = p_user_id)
                    AND (p_search IS NULL OR (
                        c.name ILIKE '%' || p_search || '%' OR
                        c.customer_code ILIKE '%' || p_search || '%' OR
                        c.email ILIKE '%' || p_search || '%' OR
                        c.primary_contact_person ILIKE '%' || p_search || '%'
                    ))
                    AND (p_status IS NULL OR c.status = p_status)
                    AND (p_customer_type_id IS NULL OR c.customer_type_id = p_customer_type_id)
                    AND (p_is_active IS NULL OR c.is_active = p_is_active)
                    ORDER BY c.created_at DESC
                    OFFSET p_offset
                    LIMIT v_limit
                ) subquery;

                -- Calculate returned count
                v_returned_count := jsonb_array_length(all_customers);
                
                -- Calculate remaining count
                v_remaining_count := GREATEST(0, v_total_count - p_offset - v_returned_count);

                -- Return success response
                RETURN QUERY SELECT 
                    'SUCCESS'::TEXT,
                    'Customers retrieved successfully'::TEXT,
                    v_total_count,
                    v_returned_count,
                    v_remaining_count,
                    all_customers;

            EXCEPTION
                WHEN OTHERS THEN
                    RETURN QUERY SELECT 
                        'ERROR'::TEXT,
                        ('Database error: ' || SQLERRM)::TEXT,
                        0::BIGINT,
                        0::BIGINT,
                        0::BIGINT,
                        '[]'::JSONB;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_all_customers(BIGINT, BIGINT, INT, INT, TEXT, TEXT, BIGINT, BOOLEAN);");
    }
};
