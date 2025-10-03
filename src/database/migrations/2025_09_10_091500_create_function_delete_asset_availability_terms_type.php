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
                    WHERE proname = 'delete_asset_availability_terms_type'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION delete_asset_availability_terms_type(
                IN p_id BIGINT,
                IN p_tenant_id BIGINT,
                IN p_current_time TIMESTAMPTZ DEFAULT now()
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                affected_rows INTEGER
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                affected_count INTEGER := 0;
            BEGIN
                -- Validate input parameters
                IF p_id IS NULL OR p_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid terms type ID provided'::TEXT AS message,
                        0::INTEGER AS affected_rows;
                    RETURN;
                END IF;

                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        0::INTEGER AS affected_rows;
                    RETURN;
                END IF;

                -- Check if the record exists and is active
                IF NOT EXISTS (
                    SELECT 1 FROM asset_availability_term_types 
                    WHERE id = p_id 
                    AND tenant_id = p_tenant_id 
                    AND isactive = true 
                    AND deleted_at IS NULL
                ) THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Terms type not found or already deleted'::TEXT AS message,
                        0::INTEGER AS affected_rows;
                    RETURN;
                END IF;

                -- Soft delete by updating isactive and deleted_at
                UPDATE asset_availability_term_types
                SET 
                    isactive = false,
                    deleted_at = p_current_time,
                    updated_at = p_current_time
                WHERE id = p_id 
                AND tenant_id = p_tenant_id;

                GET DIAGNOSTICS affected_count = ROW_COUNT;

                IF affected_count > 0 THEN
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status,
                        'Terms type deleted successfully'::TEXT AS message,
                        affected_count::INTEGER AS affected_rows;
                ELSE
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Failed to delete terms type'::TEXT AS message,
                        0::INTEGER AS affected_rows;
                END IF;
            END;
            $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS delete_asset_availability_terms_type(
            BIGINT, BIGINT, TIMESTAMPTZ
        );");
    }
};
