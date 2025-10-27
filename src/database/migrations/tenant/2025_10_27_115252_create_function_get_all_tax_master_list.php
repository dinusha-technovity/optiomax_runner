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
            -- Drop all existing versions of the function before recreating
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'get_tax_master_data'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;


        CREATE OR REPLACE FUNCTION get_tax_master_data(
            p_tenant_id BIGINT DEFAULT NULL,
            p_status TEXT DEFAULT 'ACTIVE'
        )
        RETURNS TABLE (
            response_status TEXT,
            message TEXT,
            tax_id BIGINT,
            tax_code VARCHAR,
            tax_name VARCHAR,
            tax_type VARCHAR,
            rate NUMERIC,
            amount NUMERIC,
            is_compound BOOLEAN,
            compound_on JSONB,
            applicable_to VARCHAR,
            jurisdiction VARCHAR,
            tax_authority VARCHAR,
            calculation_order INTEGER,
            effective_to DATE,
            tax_status VARCHAR,
            isactive BOOLEAN,
            created_by BIGINT,
            this_tenant_id BIGINT,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_record_count INTEGER := 0;
        BEGIN
            -- Count available tax records for tenant (and status)
            SELECT COUNT(*) INTO v_record_count
            FROM tax_master
            WHERE (p_tenant_id IS NULL OR tenant_id = p_tenant_id)
            AND (p_status IS NULL OR status = p_status);

            IF v_record_count = 0 THEN
                RETURN QUERY SELECT
                    'FAILURE'::TEXT AS response_status,
                    'No tax records found for the specified filters'::TEXT AS message,
                    NULL::BIGINT AS tax_id,
                    NULL::VARCHAR AS tax_code,
                    NULL::VARCHAR AS tax_name,
                    NULL::VARCHAR AS tax_type,
                    NULL::NUMERIC AS rate,
                    NULL::NUMERIC AS amount,
                    NULL::BOOLEAN AS is_compound,
                    NULL::JSONB AS compound_on,
                    NULL::VARCHAR AS applicable_to,
                    NULL::VARCHAR AS jurisdiction,
                    NULL::VARCHAR AS tax_authority,
                    NULL::INTEGER AS calculation_order,
                    NULL::DATE AS effective_to,
                    NULL::VARCHAR AS tax_status,
                    NULL::BOOLEAN AS isactive,
                    NULL::BIGINT AS created_by,
                    NULL::BIGINT AS this_tenant_id,
                    NULL::TIMESTAMP AS created_at,
                    NULL::TIMESTAMP AS updated_at;
                RETURN;
            END IF;

            -- Return the actual tax records
            RETURN QUERY
            SELECT
                'SUCCESS'::TEXT AS response_status,
                'Tax records fetched successfully'::TEXT AS message,
                t.id AS tax_id,
                t.tax_code,
                t.tax_name,
                t.tax_type,
                t.rate,
                t.amount,
                t.is_compound,
                CASE 
                    WHEN t.compound_on IS NULL THEN '[]'::JSONB
                    ELSE t.compound_on::JSONB 
                END AS compound_on,
                t.applicable_to,
                t.jurisdiction,
                t.tax_authority,
                t.calculation_order,
                t.effective_to,
                t.status AS tax_status,
                t.isactive,
                t.created_by,
                t.tenant_id AS this_tenant_id,
                t.created_at,
                t.updated_at
            FROM tax_master t
            WHERE (p_tenant_id IS NULL OR t.tenant_id = p_tenant_id)
            AND (p_status IS NULL OR t.status = p_status)
            ORDER BY t.calculation_order ASC;

        EXCEPTION
            WHEN OTHERS THEN
                RETURN QUERY SELECT
                    'ERROR'::TEXT AS response_status,
                    ('Database error: ' || SQLERRM)::TEXT AS message,
                    NULL::BIGINT AS tax_id,
                    NULL::VARCHAR AS tax_code,
                    NULL::VARCHAR AS tax_name,
                    NULL::VARCHAR AS tax_type,
                    NULL::NUMERIC AS rate,
                    NULL::NUMERIC AS amount,
                    NULL::BOOLEAN AS is_compound,
                    NULL::JSONB AS compound_on,
                    NULL::VARCHAR AS applicable_to,
                    NULL::VARCHAR AS jurisdiction,
                    NULL::VARCHAR AS tax_authority,
                    NULL::INTEGER AS calculation_order,
                    NULL::DATE AS effective_to,
                    NULL::VARCHAR AS tax_status,
                    NULL::BOOLEAN AS isactive,
                    NULL::BIGINT AS created_by,
                    NULL::BIGINT AS this_tenant_id,
                    NULL::TIMESTAMP AS created_at,
                    NULL::TIMESTAMP AS updated_at;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        DB::unprepared(<<<SQL
        DO $$
        DECLARE
            r RECORD;
        BEGIN
            -- Drop all existing versions of the function
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'get_tax_master_data'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;
        SQL);
    }
};
