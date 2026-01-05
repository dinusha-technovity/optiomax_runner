<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'insert_or_update_designation'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION insert_or_update_designation(
            IN p_designation_id BIGINT DEFAULT NULL,
            IN p_name VARCHAR DEFAULT NULL,
            IN p_description TEXT DEFAULT NULL,
            IN p_tenant_id BIGINT DEFAULT NULL,
            IN p_is_active BOOLEAN DEFAULT TRUE,
            IN p_current_time TIMESTAMPTZ DEFAULT now()
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            designation_id BIGINT,
            designation_name VARCHAR
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_id BIGINT;
            v_name VARCHAR;
            v_existing_id BIGINT;
        BEGIN
            -- Validate required fields
            IF p_name IS NULL OR TRIM(p_name) = '' THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT,
                    'Designation name is required'::TEXT,
                    NULL::BIGINT,
                    NULL::VARCHAR;
                RETURN;
            END IF;

            -- Check for duplicate designation name (case-insensitive)
            IF p_designation_id IS NULL THEN
                -- For new designations, check if name already exists
                SELECT id INTO v_existing_id
                FROM designations
                WHERE LOWER(TRIM(designation)) = LOWER(TRIM(p_name))
                AND (p_tenant_id IS NULL OR tenant_id = p_tenant_id)
                AND deleted_at IS NULL
                LIMIT 1;

                IF v_existing_id IS NOT NULL THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        format('Designation name "%s" already exists', p_name)::TEXT,
                        v_existing_id,
                        NULL::VARCHAR;
                    RETURN;
                END IF;
            ELSE
                -- For updates, check if name exists for other records
                SELECT id INTO v_existing_id
                FROM designations
                WHERE LOWER(TRIM(designation)) = LOWER(TRIM(p_name))
                AND id != p_designation_id
                AND (p_tenant_id IS NULL OR tenant_id = p_tenant_id)
                AND deleted_at IS NULL
                LIMIT 1;

                IF v_existing_id IS NOT NULL THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        format('Designation name "%s" already exists', p_name)::TEXT,
                        v_existing_id,
                        NULL::VARCHAR;
                    RETURN;
                END IF;
            END IF;

            -- Insert or Update
            IF p_designation_id IS NULL THEN
                -- Insert new designation
                INSERT INTO designations (
                    designation,
                    description,
                    isactive,
                    tenant_id,
                    created_at,
                    updated_at
                ) VALUES (
                    TRIM(p_name),
                    p_description,
                    p_is_active,
                    p_tenant_id,
                    p_current_time,
                    p_current_time
                )
                RETURNING id, designation INTO v_id, v_name;

                RETURN QUERY SELECT 
                    'SUCCESS'::TEXT,
                    'Designation created successfully'::TEXT,
                    v_id,
                    v_name;
            ELSE
                -- Update existing designation
                UPDATE designations
                SET
                    designation = TRIM(p_name),
                    description = p_description,
                    isactive = p_is_active,
                    updated_at = p_current_time
                WHERE id = p_designation_id
                AND (p_tenant_id IS NULL OR tenant_id = p_tenant_id)
                AND deleted_at IS NULL
                RETURNING id, designation INTO v_id, v_name;

                IF v_id IS NULL THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Designation not found or already deleted'::TEXT,
                        NULL::BIGINT,
                        NULL::VARCHAR;
                ELSE
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT,
                        'Designation updated successfully'::TEXT,
                        v_id,
                        v_name;
                END IF;
            END IF;

        EXCEPTION
            WHEN OTHERS THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT,
                    format('Error: %s', SQLERRM)::TEXT,
                    NULL::BIGINT,
                    NULL::VARCHAR;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS insert_or_update_designation(BIGINT, VARCHAR, TEXT, BIGINT, BOOLEAN, TIMESTAMPTZ);');
    }
};
