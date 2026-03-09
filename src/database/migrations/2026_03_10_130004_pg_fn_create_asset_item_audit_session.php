<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * PG function: create_asset_item_audit_session
     * Inserts one record into asset_items_audit_sessions with:
     *   - audit_status = 'start'
     *   - audit_started_at = NOW()
     * Returns the full row. VARCHAR(50) lat/lng cast to TEXT for return type match.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION create_asset_item_audit_session(
            p_audit_session_id  BIGINT,
            p_audit_period_id   BIGINT,
            p_asset_item_id     BIGINT,
            p_audit_by          BIGINT,
            p_tenant_id         BIGINT
        )
        RETURNS TABLE (
            id                           BIGINT,
            audit_session_id             BIGINT,
            audit_period_id              BIGINT,
            asset_item_id                BIGINT,
            audit_status                 TEXT,
            audit_by                     BIGINT,
            audit_started_at             TIMESTAMP,
            audit_completed_at           TIMESTAMP,
            audit_duration_minutes       INTEGER,
            asset_available              BOOLEAN,
            availability_notes           TEXT,
            availability_checked_at      TIMESTAMP,
            document                     JSONB,
            auditing_location_latitude   TEXT,
            auditing_location_longitude  TEXT,
            location_description         TEXT,
            remarks                      TEXT,
            follow_up_required           BOOLEAN,
            follow_up_notes              TEXT,
            follow_up_due_date           DATE,
            approved_by                  BIGINT,
            approved_at                  TIMESTAMP,
            approval_notes               TEXT,
            isactive                     BOOLEAN,
            tenant_id                    BIGINT,
            created_at                   TIMESTAMP,
            updated_at                   TIMESTAMP
        )
        LANGUAGE plpgsql AS $$
        DECLARE
            v_new_id BIGINT;
        BEGIN
            INSERT INTO asset_items_audit_sessions (
                audit_session_id,
                audit_period_id,
                asset_item_id,
                audit_by,
                audit_status,
                audit_started_at,
                tenant_id,
                isactive,
                created_at,
                updated_at
            ) VALUES (
                p_audit_session_id,
                p_audit_period_id,
                p_asset_item_id,
                p_audit_by,
                'start',
                NOW(),
                p_tenant_id,
                TRUE,
                NOW(),
                NOW()
            )
            RETURNING asset_items_audit_sessions.id INTO v_new_id;

            RETURN QUERY
            SELECT
                r.id,
                r.audit_session_id,
                r.audit_period_id,
                r.asset_item_id,
                r.audit_status::TEXT,
                r.audit_by,
                r.audit_started_at,
                r.audit_completed_at,
                r.audit_duration_minutes,
                r.asset_available,
                r.availability_notes,
                r.availability_checked_at,
                r.document,
                r.auditing_location_latitude::TEXT,
                r.auditing_location_longitude::TEXT,
                r.location_description,
                r.remarks,
                r.follow_up_required,
                r.follow_up_notes,
                r.follow_up_due_date,
                r.approved_by,
                r.approved_at,
                r.approval_notes,
                r.isactive,
                r.tenant_id,
                r.created_at,
                r.updated_at
            FROM asset_items_audit_sessions r
            WHERE r.id = v_new_id;
        END;
        $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS create_asset_item_audit_session(BIGINT, BIGINT, BIGINT, BIGINT, BIGINT);');
    }
};
