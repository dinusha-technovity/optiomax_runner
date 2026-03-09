<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * PG function: submit_audit_records
     *
     * Upserts per-variable scores into asset_items_audited_record for a given
     * asset_items_audit_sessions row, then automatically calls
     * calculate_and_store_audit_scores to keep the aggregate up-to-date.
     *
     * Expected JSON input (p_records):
     *   [{"variable_id": 1, "score": 4, "notes": "...", "evidence": {...}}, ...]
     *
     * Workflow side-effect:
     *   • audit_status changes from 'start' → 'in_progress' on first submission
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION submit_audit_records(
            p_asset_item_audit_session_id BIGINT,
            p_records                     JSONB,
            p_scored_by                   BIGINT,
            p_tenant_id                   BIGINT
        )
        RETURNS TABLE (
            status             TEXT,
            message            TEXT,
            records_submitted  INT,
            final_score        DECIMAL(5,2),
            grade              TEXT,
            is_passing         BOOLEAN
        )
        LANGUAGE plpgsql AS $$
        DECLARE
            v_record        JSONB;
            v_variable_id   BIGINT;
            v_score         INT;
            v_notes         TEXT;
            v_evidence      JSONB;
            v_existing_id   BIGINT;
            v_count         INT  := 0;
            v_curr_status   TEXT;
            v_calc_status   TEXT;
            v_calc_msg      TEXT;
            v_final_score   DECIMAL(5,2);
            v_grade         TEXT;
            v_is_passing    BOOLEAN;
        BEGIN
            -- Validate the session record exists and belongs to this tenant
            IF NOT EXISTS (
                SELECT 1 FROM asset_items_audit_sessions
                WHERE id        = p_asset_item_audit_session_id
                  AND tenant_id = p_tenant_id
                  AND deleted_at IS NULL
            ) THEN
                RETURN QUERY SELECT
                    'FAILURE'::TEXT, 'Audit session record not found'::TEXT,
                    0::INT, NULL::DECIMAL(5,2), NULL::TEXT, NULL::BOOLEAN;
                RETURN;
            END IF;

            SELECT audit_status
            INTO   v_curr_status
            FROM   asset_items_audit_sessions
            WHERE  id = p_asset_item_audit_session_id AND deleted_at IS NULL;

            IF v_curr_status = 'cancel' THEN
                RETURN QUERY SELECT
                    'FAILURE'::TEXT, 'Cannot submit scores for a cancelled audit session'::TEXT,
                    0::INT, NULL::DECIMAL(5,2), NULL::TEXT, NULL::BOOLEAN;
                RETURN;
            END IF;

            IF v_curr_status = 'complete' THEN
                RETURN QUERY SELECT
                    'FAILURE'::TEXT, 'Audit session is already completed. Use recalculate or reopen first'::TEXT,
                    0::INT, NULL::DECIMAL(5,2), NULL::TEXT, NULL::BOOLEAN;
                RETURN;
            END IF;

            IF p_records IS NULL OR jsonb_array_length(p_records) = 0 THEN
                RETURN QUERY SELECT
                    'FAILURE'::TEXT, 'Scores array must not be empty'::TEXT,
                    0::INT, NULL::DECIMAL(5,2), NULL::TEXT, NULL::BOOLEAN;
                RETURN;
            END IF;

            -- Process each record in the array
            FOR v_record IN SELECT * FROM jsonb_array_elements(p_records)
            LOOP
                v_variable_id := (v_record->>'variable_id')::BIGINT;
                v_score       := (v_record->>'score')::INT;
                v_notes       := v_record->>'notes';
                v_evidence    := v_record->'evidence';

                -- Skip invalid scores
                IF v_score IS NULL OR v_score < 1 OR v_score > 5 THEN
                    CONTINUE;
                END IF;

                -- Skip non-existent variables
                IF NOT EXISTS (
                    SELECT 1 FROM asset_audit_variable
                    WHERE id = v_variable_id AND deleted_at IS NULL
                ) THEN
                    CONTINUE;
                END IF;

                -- Upsert: check for an existing active record
                SELECT id INTO v_existing_id
                FROM   asset_items_audited_record
                WHERE  asset_item_audit_session_id = p_asset_item_audit_session_id
                  AND  asset_audit_variable_id     = v_variable_id
                  AND  deleted_at IS NULL
                LIMIT  1;

                IF v_existing_id IS NULL THEN
                    INSERT INTO asset_items_audited_record (
                        asset_item_audit_session_id,
                        asset_audit_variable_id,
                        score, notes, evidence,
                        scored_by, scored_at,
                        tenant_id, isactive,
                        created_at, updated_at
                    ) VALUES (
                        p_asset_item_audit_session_id,
                        v_variable_id,
                        v_score, v_notes, v_evidence,
                        p_scored_by, NOW(),
                        p_tenant_id, TRUE,
                        NOW(), NOW()
                    );
                ELSE
                    UPDATE asset_items_audited_record
                    SET
                        score      = v_score,
                        notes      = v_notes,
                        evidence   = v_evidence,
                        scored_by  = p_scored_by,
                        scored_at  = NOW(),
                        updated_at = NOW()
                    WHERE id = v_existing_id;
                END IF;

                v_count := v_count + 1;
            END LOOP;

            -- Transition status: start → in_progress on first submission
            IF v_curr_status = 'start' AND v_count > 0 THEN
                UPDATE asset_items_audit_sessions
                SET    audit_status = 'in_progress',
                       updated_at   = NOW()
                WHERE  id = p_asset_item_audit_session_id;
            END IF;

            -- Auto-calculate aggregate scores
            SELECT
                c.status, c.message, c.final_score, c.grade, c.is_passing
            INTO
                v_calc_status, v_calc_msg, v_final_score, v_grade, v_is_passing
            FROM calculate_and_store_audit_scores(
                p_asset_item_audit_session_id, p_tenant_id
            ) c
            LIMIT 1;

            RETURN QUERY SELECT
                'SUCCESS'::TEXT,
                'Scores submitted successfully'::TEXT,
                v_count,
                v_final_score,
                v_grade,
                v_is_passing;
        END;
        $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS submit_audit_records(BIGINT, JSONB, BIGINT, BIGINT)');
    }
};
