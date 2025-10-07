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
            -- Create function to get customer data by ID
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_customer_data_by_id'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION get_customer_data_by_id(
                IN p_user_id BIGINT DEFAULT NULL,
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_customer_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                customer_data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                customer_single_data JSONB;
                v_log_data JSONB;
                v_log_success BOOLEAN;
                v_error_message TEXT;
            BEGIN
                -- Validate required parameters
                IF p_tenant_id IS NULL THEN
                    RETURN QUERY SELECT 
                        'ERROR'::TEXT,
                        'Tenant ID is required'::TEXT,
                        '{}'::JSONB;
                    RETURN;
                END IF;

                IF p_customer_id IS NULL THEN
                    RETURN QUERY SELECT 
                        'ERROR'::TEXT,
                        'Customer ID is required'::TEXT,
                        '{}'::JSONB;
                    RETURN;
                END IF;

                -- Get customer data by ID
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
                )
                INTO customer_single_data
                FROM customers c
                LEFT JOIN customer_types ct ON c.customer_type_id = ct.id AND ct.deleted_at IS NULL
                LEFT JOIN users u ON c.created_by = u.id
                WHERE c.id = p_customer_id
                AND c.tenant_id = p_tenant_id
                AND c.deleted_at IS NULL;

                -- Check if customer was found
                IF customer_single_data IS NULL THEN
                    RETURN QUERY SELECT 
                        'ERROR'::TEXT,
                        'Customer not found or has been deleted'::TEXT,
                        '{}'::JSONB;
                    RETURN;
                END IF;

                -- Build log data for activity logging
                v_log_data := jsonb_build_object(
                    'customer_id', p_customer_id,
                    'tenant_id', p_tenant_id,
                    'accessed_by', p_user_id,
                    'accessed_at', NOW()
                );

                -- Log activity if user info provided
                IF p_user_id IS NOT NULL THEN
                    BEGIN
                        PERFORM log_activity(
                            'customer.read',
                            'Customer data accessed: ' || p_customer_id,
                            'customer',
                            p_customer_id,
                            'user',
                            p_user_id,
                            v_log_data,
                            p_tenant_id
                        );
                        v_log_success := TRUE;
                    EXCEPTION WHEN OTHERS THEN
                        v_log_success := FALSE;
                        v_error_message := 'Logging failed: ' || SQLERRM;
                        -- Log the error but don't fail the main operation
                    END;
                END IF;

                -- Return success response with customer data
                RETURN QUERY SELECT 
                    'SUCCESS'::TEXT,
                    'Customer data retrieved successfully'::TEXT,
                    customer_single_data;

            EXCEPTION
                WHEN OTHERS THEN
                    RETURN QUERY SELECT 
                        'ERROR'::TEXT,
                        ('Database error: ' || SQLERRM)::TEXT,
                        '{}'::JSONB;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_customer_data_by_id(BIGINT, BIGINT, BIGINT);");
    }
};
