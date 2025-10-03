<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
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
                    WHERE proname = 'get_asset_availability_terms_types_detailed'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION get_asset_availability_terms_types_detailed(
                IN p_tenant_id BIGINT,
                IN p_terms_type_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                name TEXT,
                description TEXT,
                isactive BOOLEAN,
                created_at TIMESTAMPTZ,
                updated_at TIMESTAMPTZ
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::TEXT AS description,
                        NULL::BOOLEAN AS isactive,
                        NULL::TIMESTAMPTZ AS created_at,
                        NULL::TIMESTAMPTZ AS updated_at;
                    RETURN;
                END IF;

                -- Validate terms type ID (optional)
                IF p_terms_type_id IS NOT NULL AND p_terms_type_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid terms type ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::TEXT AS description,
                        NULL::BOOLEAN AS isactive,
                        NULL::TIMESTAMPTZ AS created_at,
                        NULL::TIMESTAMPTZ AS updated_at;
                    RETURN;
                END IF;

                -- Return matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Terms types fetched successfully'::TEXT AS message,
                    a.id,
                    a.name::TEXT,
                    COALESCE(a.description, '')::TEXT,
                    a.isactive,
                    a.created_at,
                    a.updated_at
                FROM
                    asset_availability_term_types a
                WHERE 
                    a.tenant_id = p_tenant_id
                    AND (p_terms_type_id IS NULL OR a.id = p_terms_type_id)
                    AND a.deleted_at IS NULL
                    AND a.isactive = TRUE
                ORDER BY a.created_at DESC;

                -- If no records found, return success with empty result message
                IF NOT FOUND THEN
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status,
                        'No terms types found'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::TEXT AS description,
                        NULL::BOOLEAN AS isactive,
                        NULL::TIMESTAMPTZ AS created_at,
                        NULL::TIMESTAMPTZ AS updated_at;
                END IF;
            END;
            $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_asset_availability_terms_types_detailed(
            BIGINT, BIGINT
        );");
    }
};
