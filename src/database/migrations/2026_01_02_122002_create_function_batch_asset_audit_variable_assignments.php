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
        DB::unprepared(<<<'SQL'

        DO $$
        DECLARE
            r RECORD;
        BEGIN
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'batch_insert_asset_audit_variable_assignments'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION batch_insert_asset_audit_variable_assignments(
            IN p_assignments JSONB,
            IN p_tenant_id BIGINT,
            IN p_current_time TIMESTAMPTZ,
            IN p_assigned_by BIGINT DEFAULT NULL,
            IN p_causer_id BIGINT DEFAULT NULL,
            IN p_causer_name TEXT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            total_assignments INTEGER,
            successful_assignments INTEGER,
            failed_assignments INTEGER,
            results JSONB
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_assignment JSONB;
            v_result JSONB;
            v_results JSONB := '[]'::JSONB;
            v_total INTEGER := 0;
            v_successful INTEGER := 0;
            v_failed INTEGER := 0;
            v_single_result RECORD;
        BEGIN
            -- Validate tenant_id
            IF p_tenant_id IS NULL OR p_tenant_id = 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 
                    'Tenant ID cannot be null or zero'::TEXT, 
                    0::INTEGER,
                    0::INTEGER,
                    0::INTEGER,
                    '[]'::JSONB;
                RETURN;
            END IF;

            -- Validate assignments array is not empty
            IF p_assignments IS NULL OR jsonb_array_length(p_assignments) = 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 
                    'No assignments provided'::TEXT, 
                    0::INTEGER,
                    0::INTEGER,
                    0::INTEGER,
                    '[]'::JSONB;
                RETURN;
            END IF;

            -- Loop through each assignment in the array
            FOR v_assignment IN SELECT * FROM jsonb_array_elements(p_assignments)
            LOOP
                v_total := v_total + 1;

                -- Call the single assignment function for each item
                SELECT * INTO v_single_result
                FROM insert_or_update_asset_audit_variable_assignment(
                    NULL, -- p_assignment_id (always insert for batch)
                    (v_assignment->>'asset_audit_variable_id')::BIGINT,
                    (v_assignment->>'assignable_type_id')::BIGINT,
                    (v_assignment->>'assignable_id')::BIGINT,
                    p_tenant_id,
                    p_current_time,
                    COALESCE((v_assignment->>'is_active')::BOOLEAN, TRUE),
                    p_assigned_by,
                    p_causer_id,
                    p_causer_name
                );

                -- Build result for this assignment
                v_result := jsonb_build_object(
                    'index', v_total,
                    'asset_audit_variable_id', v_assignment->>'asset_audit_variable_id',
                    'assignable_type_id', v_assignment->>'assignable_type_id',
                    'assignable_id', v_assignment->>'assignable_id',
                    'status', v_single_result.status,
                    'message', v_single_result.message,
                    'assignment_id', v_single_result.assignment_id
                );

                -- Add to results array
                v_results := v_results || v_result;

                -- Count successes and failures
                IF v_single_result.status = 'SUCCESS' THEN
                    v_successful := v_successful + 1;
                ELSE
                    v_failed := v_failed + 1;
                END IF;
            END LOOP;

            -- Return aggregated results
            IF v_failed = 0 THEN
                RETURN QUERY SELECT 
                    'SUCCESS'::TEXT,
                    format('Successfully assigned %s audit variable(s)', v_successful)::TEXT,
                    v_total,
                    v_successful,
                    v_failed,
                    v_results;
            ELSIF v_successful = 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT,
                    format('All %s assignment(s) failed', v_failed)::TEXT,
                    v_total,
                    v_successful,
                    v_failed,
                    v_results;
            ELSE
                RETURN QUERY SELECT 
                    'PARTIAL'::TEXT,
                    format('%s succeeded, %s failed out of %s assignment(s)', v_successful, v_failed, v_total)::TEXT,
                    v_total,
                    v_successful,
                    v_failed,
                    v_results;
            END IF;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DO $$
        DECLARE
            r RECORD;
        BEGIN
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'batch_insert_asset_audit_variable_assignments'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;
        SQL);
    }
};