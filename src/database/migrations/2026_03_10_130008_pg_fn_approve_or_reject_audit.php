<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * PG function: approve_or_reject_audit
     *
     * Approve – sets approved_by / approved_at / approval_notes.
     * Reject  – clears approval fields and reverts status to 'in_progress'
     *           so the auditor can revise and resubmit.
     *
     * Pre-condition: audit_status = 'complete' (only completed audits can be reviewed).
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION approve_or_reject_audit(
            p_asset_item_audit_session_id BIGINT,
            p_action                      TEXT,    -- 'approve' | 'reject'
            p_approved_by                 BIGINT,
            p_notes                       TEXT,
            p_tenant_id                   BIGINT
        )
        RETURNS TABLE (
            status       TEXT,
            message      TEXT,
            audit_status TEXT,
            approved_by  BIGINT,
            approved_at  TIMESTAMP
        )
        LANGUAGE plpgsql AS $$
        DECLARE
            v_curr_status TEXT;
        BEGIN
            -- Validate action
            IF p_action NOT IN ('approve', 'reject') THEN
                RETURN QUERY SELECT
                    'FAILURE'::TEXT, 'Action must be approve or reject'::TEXT,
                    NULL::TEXT, NULL::BIGINT, NULL::TIMESTAMP;
                RETURN;
            END IF;

            -- Validate the session record
            SELECT ais.audit_status
            INTO   v_curr_status
            FROM   asset_items_audit_sessions ais
            WHERE  ais.id        = p_asset_item_audit_session_id
              AND  ais.tenant_id = p_tenant_id
              AND  ais.deleted_at IS NULL;

            IF NOT FOUND THEN
                RETURN QUERY SELECT
                    'FAILURE'::TEXT, 'Audit session record not found'::TEXT,
                    NULL::TEXT, NULL::BIGINT, NULL::TIMESTAMP;
                RETURN;
            END IF;

            IF v_curr_status != 'complete' THEN
                RETURN QUERY SELECT
                    'FAILURE'::TEXT,
                    'Only completed audit sessions can be approved or rejected (current status: ' || v_curr_status || ')'::TEXT,
                    v_curr_status, NULL::BIGINT, NULL::TIMESTAMP;
                RETURN;
            END IF;

            IF p_action = 'approve' THEN
                UPDATE asset_items_audit_sessions
                SET
                    approved_by    = p_approved_by,
                    approved_at    = NOW(),
                    approval_notes = p_notes,
                    updated_at     = NOW()
                WHERE id = p_asset_item_audit_session_id;

                RETURN QUERY SELECT
                    'SUCCESS'::TEXT,
                    'Audit approved successfully'::TEXT,
                    'complete'::TEXT,
                    p_approved_by,
                    NOW()::TIMESTAMP;
            ELSE
                -- Reject: clear approval and return to in_progress for revision
                UPDATE asset_items_audit_sessions
                SET
                    approved_by    = NULL,
                    approved_at    = NULL,
                    approval_notes = p_notes,
                    audit_status   = 'in_progress',
                    updated_at     = NOW()
                WHERE id = p_asset_item_audit_session_id;

                RETURN QUERY SELECT
                    'SUCCESS'::TEXT,
                    'Audit rejected and returned for revision'::TEXT,
                    'in_progress'::TEXT,
                    NULL::BIGINT,
                    NULL::TIMESTAMP;
            END IF;
        END;
        $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS approve_or_reject_audit(BIGINT, TEXT, BIGINT, TEXT, BIGINT)');
    }
};
