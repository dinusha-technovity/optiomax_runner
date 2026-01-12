<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * ISO 19011:2018 Compliant - Single Variable Audit Score Submission
     * Supports incremental scoring workflow for real-time audit progress
     */
    public function up(): void
    {
        // Drop existing function first if it exists
        DB::unprepared('DROP FUNCTION IF EXISTS submit_single_audit_score(BIGINT, BIGINT, INTEGER, TEXT, BIGINT, BIGINT);');
        
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION submit_single_audit_score(
                p_session_id BIGINT,
                p_variable_id BIGINT,
                p_score INTEGER,
                p_remarks TEXT DEFAULT NULL,
                p_auditor_id BIGINT DEFAULT NULL,
                p_tenant_id BIGINT DEFAULT NULL
            )
            RETURNS JSON
            LANGUAGE plpgsql
            AS $BODY$
            DECLARE
                v_result JSON;
                v_existing_id BIGINT;
            BEGIN
                -- Validate score is 1-5
                IF p_score NOT IN (1, 2, 3, 4, 5) THEN
                    RAISE EXCEPTION 'Score must be between 1 and 5';
                END IF;

                -- Check if record exists
                SELECT id INTO v_existing_id
                FROM asset_items_audited_record
                WHERE asset_items_audit_sessions_id = p_session_id
                  AND asset_audit_variable_id = p_variable_id
                  AND tenant_id = p_tenant_id
                  AND deleted_at IS NULL;

                IF v_existing_id IS NOT NULL THEN
                    -- Update existing record
                    UPDATE asset_items_audited_record
                    SET score = p_score::VARCHAR,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = v_existing_id;
                ELSE
                    -- Insert new record
                    INSERT INTO asset_items_audited_record (
                        asset_items_audit_sessions_id,
                        asset_audit_variable_id,
                        score,
                        tenant_id,
                        isactive,
                        created_at,
                        updated_at
                    ) VALUES (
                        p_session_id,
                        p_variable_id,
                        p_score::VARCHAR,
                        p_tenant_id,
                        true,
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                    );
                END IF;

                v_result := json_build_object(
                    'success', true,
                    'message', 'Score submitted successfully',
                    'data', json_build_object(
                        'session_id', p_session_id,
                        'variable_id', p_variable_id,
                        'score', p_score
                    )
                );

                RETURN v_result;

            EXCEPTION
                WHEN OTHERS THEN
                    RETURN json_build_object(
                        'success', false,
                        'message', 'Error: ' || SQLERRM
                    );
            END;
            $BODY$
SQL
        );

        DB::statement("COMMENT ON FUNCTION submit_single_audit_score IS 'ISO 19011 compliant function for incremental audit variable scoring'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS submit_single_audit_score(BIGINT, BIGINT, INTEGER, TEXT, BIGINT, BIGINT);');
    }
};