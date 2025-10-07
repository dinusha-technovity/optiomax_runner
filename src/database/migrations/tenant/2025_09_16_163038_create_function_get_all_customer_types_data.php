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
            -- Create function to get all customer types
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_all_customer_types'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION get_all_customer_types(
                IN p_tenant_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                customer_types_list JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                all_customer_types JSONB;
            BEGIN
                -- If tenant_id is NULL, return all records (for global/system types)
                IF p_tenant_id IS NULL THEN
                    SELECT COALESCE(jsonb_agg(customer_type_data), '[]'::jsonb)
                    INTO all_customer_types
                    FROM (
                        SELECT jsonb_build_object(
                            'id', ct.id,
                            'name', ct.name,
                            'description', ct.description,
                            'tenant_id', ct.tenant_id,
                            'created_at', ct.created_at,
                            'updated_at', ct.updated_at
                        ) AS customer_type_data
                        FROM customer_types ct
                        WHERE ct.deleted_at IS NULL
                        ORDER BY ct.name
                    ) subquery;

                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT,
                        'All customer types fetched successfully'::TEXT,
                        all_customer_types;
                    RETURN;
                END IF;

                -- Validate tenant ID
                IF p_tenant_id < 0 THEN
                    RETURN QUERY SELECT 
                        'ERROR'::TEXT,
                        'Invalid tenant ID provided'::TEXT,
                        '[]'::JSONB;
                    RETURN;
                END IF;

                -- Return customer types for specific tenant (including global ones)
                SELECT COALESCE(jsonb_agg(customer_type_data), '[]'::jsonb)
                INTO all_customer_types
                FROM (
                    SELECT jsonb_build_object(
                        'id', ct.id,
                        'name', ct.name,
                        'description', ct.description,
                        'tenant_id', ct.tenant_id,
                        'created_at', ct.created_at,
                        'updated_at', ct.updated_at
                    ) AS customer_type_data
                    FROM customer_types ct
                    WHERE (ct.tenant_id = p_tenant_id OR ct.tenant_id IS NULL)
                    AND ct.deleted_at IS NULL
                    ORDER BY ct.name
                ) subquery;

                RETURN QUERY SELECT 
                    'SUCCESS'::TEXT,
                    'Customer types fetched successfully'::TEXT,
                    all_customer_types;

            EXCEPTION
                WHEN OTHERS THEN
                    RETURN QUERY SELECT 
                        'ERROR'::TEXT,
                        ('Database error: ' || SQLERRM)::TEXT,
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
        DB::unprepared('DROP FUNCTION IF EXISTS get_all_customer_types(BIGINT);');
    }
};
