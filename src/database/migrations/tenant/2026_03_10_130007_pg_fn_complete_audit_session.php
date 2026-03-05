<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * PG function: complete_audit_session
     *
     * Marks an asset_items_audit_sessions row as 'complete', records the
     * completion timestamp and computes the duration in minutes from when
     * the audit was started.
     *
     * Allowed transitions:  start | in_progress  →  complete
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION complete_audit_session(
            p_asset_item_audit_session_id BIGINT,
            p_tenant_id                   BIGINT,
            p_remarks                     TEXT DEFAULT NULL
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
            v_started_at  TIMESTAMP;
            v_duration    INT;
            v_curr_status TEXT;
            v_final_score DECIMAL(5,2);
            v_grade       TEXT;
        BEGIN
            -- Fetch current status and start time
            SELECT ais.audit_status, ais.audit_started_at
            INTO   v_curr_status, v_started_at
            FROM   asset_items_audit_sessions ais
            WHERE  ais.id        = p_asset_item_audit_session_id
              AND  ais.tenant_id = p_tenant_id
              AND  ais.deleted_at IS NULL;

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
                    'FAILURE'::TEXT, 'Cannot complete a cancelled audit session'::TEXT,
                    v_curr_status, NULL::TIMESTAMP, NULL::INT,
                    NULL::DECIMAL(5,2), NULL::TEXT;
                RETURN;
            END IF;

            -- Calculate elapsed time in minutes
            IF v_started_at IS NOT NULL THEN
                v_duration := GREATEST(0, EXTRACT(EPOCH FROM (NOW() - v_started_at))::INT / 60);
            ELSE
                v_duration := 0;
            END IF;

            -- Mark as complete
            UPDATE asset_items_audit_sessions
            SET
                audit_status           = 'complete',
                audit_completed_at     = NOW(),
                audit_duration_minutes = v_duration,
                remarks                = COALESCE(p_remarks, remarks),
                updated_at             = NOW()
            WHERE id = p_asset_item_audit_session_id;

            -- Retrieve the latest aggregate score if available
            SELECT s.final_score, s.grade
            INTO   v_final_score, v_grade
            FROM   asset_items_audit_score s
            WHERE  s.asset_item_audit_session_id = p_asset_item_audit_session_id
              AND  s.deleted_at IS NULL
            LIMIT  1;

            RETURN QUERY SELECT
                'SUCCESS'::TEXT,
                'Audit session completed successfully'::TEXT,
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
        DB::unprepared('DROP FUNCTION IF EXISTS complete_audit_session(BIGINT, BIGINT, TEXT)');
    }
};
