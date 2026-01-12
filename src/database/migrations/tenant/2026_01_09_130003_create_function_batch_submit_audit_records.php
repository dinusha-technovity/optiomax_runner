<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Batch function to submit audit records with scores (1-5 scale)
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
                WHERE proname = 'batch_submit_audit_records'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION batch_submit_audit_records(
            IN p_session_id BIGINT,
            IN p_asset_item_id BIGINT,
            IN p_audit_records JSONB, -- Array of {variable_id, score}
            IN p_tenant_id BIGINT,
            IN p_current_time TIMESTAMPTZ,
            IN p_causer_id BIGINT DEFAULT NULL,
            IN p_causer_name TEXT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            records_inserted INT,
            records_updated INT,
            records_failed INT,
            final_score DECIMAL(5,2),
            condition_status TEXT
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_record JSONB;
            v_variable_id BIGINT;
            v_score VARCHAR(50);
            v_score_numeric DECIMAL(5,2);
            v_existing_record_id BIGINT;
            v_inserted INT := 0;
            v_updated INT := 0;
            v_failed INT := 0;
            v_calc_result RECORD;
        BEGIN
            -- Validate inputs
            IF p_session_id IS NULL OR p_session_id = 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 
                    'Session ID is required'::TEXT,
                    0, 0, 0, NULL::DECIMAL(5,2), NULL::TEXT;
                RETURN;
            END IF;

            IF p_tenant_id IS NULL OR p_tenant_id = 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 
                    'Tenant ID is required'::TEXT,
                    0, 0, 0, NULL::DECIMAL(5,2), NULL::TEXT;
                RETURN;
            END IF;

            IF p_audit_records IS NULL OR jsonb_array_length(p_audit_records) = 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 
                    'Audit records are required'::TEXT,
                    0, 0, 0, NULL::DECIMAL(5,2), NULL::TEXT;
                RETURN;
            END IF;

            -- Verify session exists
            IF NOT EXISTS (
                SELECT 1 FROM asset_items_audit_sessions 
                WHERE id = p_session_id 
                AND tenant_id = p_tenant_id 
                AND deleted_at IS NULL
            ) THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 
                    'Audit session not found'::TEXT,
                    0, 0, 0, NULL::DECIMAL(5,2), NULL::TEXT;
                RETURN;
            END IF;

            -- Process each audit record
            FOR v_record IN SELECT * FROM jsonb_array_elements(p_audit_records)
            LOOP
                BEGIN
                    -- Extract values from JSON
                    v_variable_id := (v_record->>'variable_id')::BIGINT;
                    v_score := v_record->>'score';
                    
                    -- Validate score is between 1-5
                    BEGIN
                        v_score_numeric := v_score::DECIMAL(5,2);
                        IF v_score_numeric < 1 OR v_score_numeric > 5 THEN
                            v_failed := v_failed + 1;
                            CONTINUE;
                        END IF;
                    EXCEPTION WHEN OTHERS THEN
                        v_failed := v_failed + 1;
                        CONTINUE;
                    END;

                    -- Verify variable exists and is active
                    IF NOT EXISTS (
                        SELECT 1 FROM asset_audit_variable 
                        WHERE id = v_variable_id 
                        AND tenant_id = p_tenant_id 
                        AND deleted_at IS NULL
                        AND is_active = TRUE
                    ) THEN
                        v_failed := v_failed + 1;
                        CONTINUE;
                    END IF;

                    -- Check if record already exists
                    SELECT id INTO v_existing_record_id
                    FROM asset_items_audited_record
                    WHERE asset_items_audit_sessions_id = p_session_id
                        AND asset_audit_variable_id = v_variable_id
                        AND tenant_id = p_tenant_id
                        AND deleted_at IS NULL;

                    IF v_existing_record_id IS NULL THEN
                        -- Insert new record
                        INSERT INTO asset_items_audited_record (
                            asset_items_audit_sessions_id,
                            asset_audit_variable_id,
                            score,
                            tenant_id,
                            isactive,
                            created_at,
                            updated_at
                        )
                        VALUES (
                            p_session_id,
                            v_variable_id,
                            v_score,
                            p_tenant_id,
                            true,
                            p_current_time,
                            p_current_time
                        );
                        v_inserted := v_inserted + 1;
                    ELSE
                        -- Update existing record
                        UPDATE asset_items_audited_record
                        SET
                            score = v_score,
                            updated_at = p_current_time
                        WHERE id = v_existing_record_id;
                        v_updated := v_updated + 1;
                    END IF;

                EXCEPTION WHEN OTHERS THEN
                    v_failed := v_failed + 1;
                END;
            END LOOP;

            -- Calculate scores after all records are submitted
            IF v_inserted > 0 OR v_updated > 0 THEN
                SELECT * INTO v_calc_result
                FROM calculate_and_store_audit_scores(
                    p_session_id,
                    p_asset_item_id,
                    p_tenant_id,
                    p_current_time,
                    p_causer_id,
                    p_causer_name
                );

                -- Log the batch submission
                BEGIN
                    PERFORM log_activity(
                        'batch_submit_audit_records',
                        format('User %s submitted %s audit records for session %s', 
                               p_causer_name, v_inserted + v_updated, p_session_id),
                        'asset_items_audited_record',
                        p_session_id,
                        'user',
                        p_causer_id,
                        jsonb_build_object(
                            'inserted', v_inserted,
                            'updated', v_updated,
                            'failed', v_failed,
                            'final_score', v_calc_result.final_score,
                            'status', v_calc_result.condition_status
                        ),
                        p_tenant_id
                    );
                EXCEPTION WHEN OTHERS THEN
                    -- Continue even if logging fails
                END;

                RETURN QUERY SELECT 
                    'SUCCESS'::TEXT,
                    format('%s records inserted, %s updated, %s failed. Final score: %.2f (%s)', 
                           v_inserted, v_updated, v_failed, 
                           v_calc_result.final_score, v_calc_result.condition_status)::TEXT,
                    v_inserted,
                    v_updated,
                    v_failed,
                    v_calc_result.final_score,
                    v_calc_result.condition_status;
            ELSE
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT,
                    'No records were successfully processed'::TEXT,
                    0, 0, v_failed, NULL::DECIMAL(5,2), NULL::TEXT;
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
                WHERE proname = 'batch_submit_audit_records'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;
        SQL);
    }
};
