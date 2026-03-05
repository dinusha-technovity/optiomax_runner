<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * PG function: finish_audit_session
     *
     * Atomically saves audit detail fields (document, location, remarks,
     * follow-up) and marks the session as 'complete' with completion
     * timestamp and calculated duration.
     *
     * Allowed transitions:  start | in_progress  →  complete
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION finish_audit_session(
            p_id                           BIGINT,
            p_tenant_id                    BIGINT,
            p_document                     JSONB    DEFAULT NULL,
            p_auditing_location_latitude   TEXT     DEFAULT NULL,
            p_auditing_location_longitude  TEXT     DEFAULT NULL,
            p_location_description         TEXT     DEFAULT NULL,
            p_remarks                      TEXT     DEFAULT NULL,
            p_follow_up_required           BOOLEAN  DEFAULT NULL,
            p_follow_up_notes              TEXT     DEFAULT NULL,
            p_follow_up_due_date           DATE     DEFAULT NULL
        )
        RETURNS TABLE (
            status                 TEXT,
            message                TEXT,
            audit_status           TEXT,
            audit_completed_at     TIMESTAMP,
            audit_duration_minutes INT,
            final_score            DECIMAL(5,2),
            grade                  TEXT
        )
        LANGUAGE plpgsql AS $$
        DECLARE
            v_curr_status TEXT;
            v_started_at  TIMESTAMP;
            v_duration    INT;
            v_final_score DECIMAL(5,2);
            v_grade       TEXT;
        BEGIN
            -- Lock + fetch current state
            SELECT ais.audit_status, ais.audit_started_at
            INTO   v_curr_status, v_started_at
            FROM   asset_items_audit_sessions ais
            WHERE  ais.id        = p_id
              AND  ais.tenant_id = p_tenant_id
              AND  ais.deleted_at IS NULL
            FOR UPDATE;

            IF NOT FOUND THEN
                RETURN QUERY SELECT
                    'FAILURE'::TEXT, 'Audit session record not found'::TEXT,
                    NULL::TEXT, NULL::TIMESTAMP, NULL::INT,
                    NULL::DECIMAL(5,2), NULL::TEXT;
                RETURN;
            END IF;

            IF v_curr_status = 'complete' THEN
                RETURN QUERY SELECT
                    'FAILURE'::TEXT, 'Audit session is already completed'::TEXT,
                    v_curr_status, NULL::TIMESTAMP, NULL::INT,
                    NULL::DECIMAL(5,2), NULL::TEXT;
                RETURN;
            END IF;

            IF v_curr_status = 'cancel' THEN
                RETURN QUERY SELECT
                    'FAILURE'::TEXT, 'Cannot finish a cancelled audit session'::TEXT,
                    v_curr_status, NULL::TIMESTAMP, NULL::INT,
                    NULL::DECIMAL(5,2), NULL::TEXT;
                RETURN;
            END IF;

            -- Calculate elapsed minutes (0 if never started)
            IF v_started_at IS NOT NULL THEN
                v_duration := GREATEST(0, EXTRACT(EPOCH FROM (NOW() - v_started_at))::INT / 60);
            ELSE
                v_duration := 0;
            END IF;

            -- Update detail fields + mark complete
            UPDATE asset_items_audit_sessions
            SET
                document                     = COALESCE(p_document,                    document),
                auditing_location_latitude   = COALESCE(p_auditing_location_latitude,  auditing_location_latitude),
                auditing_location_longitude  = COALESCE(p_auditing_location_longitude, auditing_location_longitude),
                location_description         = COALESCE(p_location_description,        location_description),
                remarks                      = COALESCE(p_remarks,                     remarks),
                follow_up_required           = COALESCE(p_follow_up_required,          follow_up_required),
                follow_up_notes              = COALESCE(p_follow_up_notes,             follow_up_notes),
                follow_up_due_date           = COALESCE(p_follow_up_due_date,          follow_up_due_date),
                audit_status                 = 'complete',
                audit_completed_at           = NOW(),
                audit_duration_minutes       = v_duration,
                updated_at                   = NOW()
            WHERE  id        = p_id
              AND  tenant_id = p_tenant_id;

            -- Retrieve aggregate score if already submitted
            SELECT s.final_score, s.grade
            INTO   v_final_score, v_grade
            FROM   asset_items_audit_score s
            WHERE  s.asset_item_audit_session_id = p_id
              AND  s.deleted_at IS NULL
            LIMIT  1;

            RETURN QUERY SELECT
                'SUCCESS'::TEXT,
                'Audit session finished successfully'::TEXT,
                'complete'::TEXT,
                NOW()::TIMESTAMP,
                v_duration,
                v_final_score,
                v_grade;
        END;
        $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS finish_audit_session(BIGINT,BIGINT,JSONB,TEXT,TEXT,TEXT,TEXT,BOOLEAN,TEXT,DATE)');
    }
};
