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
            DO \$\$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_designations'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END\$\$;
            
            CREATE OR REPLACE FUNCTION get_designations(
                p_tenant_id BIGINT,
                p_designation_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                designation VARCHAR,
                description TEXT,
                isactive BOOLEAN,
                tenant_id BIGINT,
                created_at TIMESTAMP,
                updated_at TIMESTAMP
            ) 
            LANGUAGE plpgsql
            AS \$\$
            BEGIN
                -- Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Invalid tenant ID provided'::TEXT,
                        NULL::BIGINT,
                        NULL::VARCHAR,
                        NULL::TEXT,
                        NULL::BOOLEAN,
                        NULL::BIGINT,
                        NULL::TIMESTAMP,
                        NULL::TIMESTAMP;
                    RETURN;
                END IF;
            
                -- Validate designation ID if provided
                IF p_designation_id IS NOT NULL AND p_designation_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Invalid designation ID provided'::TEXT,
                        NULL::BIGINT,
                        NULL::VARCHAR,
                        NULL::TEXT,
                        NULL::BOOLEAN,
                        NULL::BIGINT,
                        NULL::TIMESTAMP,
                        NULL::TIMESTAMP;
                    RETURN;
                END IF;
            
                -- Return matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT,
                    'Designations retrieved successfully'::TEXT,
                    d.id,
                    d.designation,
                    d.description,
                    d.isactive,
                    d.tenant_id,
                    d.created_at,
                    d.updated_at
                FROM
                    designations d
                WHERE
                    (p_designation_id IS NULL OR d.id = p_designation_id)
                    AND d.tenant_id = p_tenant_id
                    AND d.deleted_at IS NULL
                ORDER BY
                    d.designation ASC;
            END;
            \$\$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_designations(BIGINT, BIGINT)');
    }
};
