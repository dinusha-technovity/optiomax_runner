<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
                WHERE proname = 'cancel_supplier_invitation'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION cancel_supplier_invitation(
            IN p_invite_supplier_id BIGINT,
            IN p_tenant_id BIGINT,
            IN p_current_time TIMESTAMP WITH TIME ZONE,
            IN p_user_id BIGINT,
            IN p_user_name VARCHAR,
            IN p_cancelled_reason VARCHAR
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            supplier_data JSONB,
            invite_data JSONB
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_supplier suppliers%ROWTYPE;
            v_invite supplier_invites%ROWTYPE;
            v_existing_data JSON;
            v_updated_supplier JSON;
            v_updated_invite JSON;
            v_reason TEXT;
            v_log_success BOOLEAN := TRUE;
            v_supplier_rows INT;
            v_invite_rows INT;
        BEGIN
            ----------------------------------------------------------------------
            -- BASIC VALIDATIONS
            ----------------------------------------------------------------------
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT
                    'FAILURE'::TEXT,
                    'Invalid tenant ID provided.'::TEXT,
                    NULL::JSONB,
                    NULL::JSONB;
                RETURN;
            END IF;

            IF p_invite_supplier_id IS NULL OR p_invite_supplier_id <= 0 THEN
                RETURN QUERY SELECT
                    'FAILURE'::TEXT,
                    'Invalid supplier ID provided.'::TEXT,
                    NULL::JSONB,
                    NULL::JSONB;
                RETURN;
            END IF;

            ----------------------------------------------------------------------
            -- FETCH SUPPLIER SNAPSHOT AND VALIDATE
            ----------------------------------------------------------------------
            SELECT * INTO v_supplier
            FROM suppliers
            WHERE id = p_invite_supplier_id
              AND tenant_id = p_tenant_id
              AND created_by = p_user_id --im setting this to ensure only the inviter can cancel the invite
            LIMIT 1;

            IF NOT FOUND THEN
                RETURN QUERY SELECT
                    'FAILURE'::TEXT,
                    'Supplier not found for the provided tenant or user is not creator.'::TEXT,
                    NULL::JSONB,
                    NULL::JSONB;
                RETURN;
            END IF;

            v_existing_data := json_build_object('supplier', row_to_json(v_supplier));

            ----------------------------------------------------------------------
            -- FETCH SUPPLIER INVITE SNAPSHOT
            ----------------------------------------------------------------------
            SELECT * INTO v_invite
            FROM supplier_invites
            WHERE suppliers_id = p_invite_supplier_id
              AND tenant_id = p_tenant_id
            ORDER BY created_at DESC
            LIMIT 1;

            IF NOT FOUND THEN
                RETURN QUERY SELECT
                    'FAILURE'::TEXT,
                    'No invitation found for the supplier.'::TEXT,
                    NULL::JSONB,
                    NULL::JSONB;
                RETURN;
            END IF;

            v_existing_data := json_build_object(
                'supplier', row_to_json(v_supplier),
                'invite', row_to_json(v_invite)
            );

            v_reason := COALESCE(NULLIF(btrim(p_cancelled_reason), ''), 'Invitation cancelled by user');

            ----------------------------------------------------------------------
            -- UPDATE SUPPLIER STATUS
            ----------------------------------------------------------------------
            UPDATE suppliers
            SET supplier_reg_status = 'CANCELLED',
                updated_at = COALESCE(p_current_time, NOW())
            WHERE id = p_invite_supplier_id
              AND tenant_id = p_tenant_id;

            GET DIAGNOSTICS v_supplier_rows = ROW_COUNT;

            IF v_supplier_rows = 0 THEN
                RETURN QUERY SELECT
                    'FAILURE'::TEXT,
                    'Unable to update supplier status.'::TEXT,
                    NULL::JSONB,
                    NULL::JSONB;
                RETURN;
            END IF;

            ----------------------------------------------------------------------
            -- UPDATE INVITE STATUS AND COMMENT
            ----------------------------------------------------------------------
            UPDATE supplier_invites
            SET status = 'cancelled',
                comment_for_action = v_reason,
                isactive = false,
                updated_at = COALESCE(p_current_time, NOW())
            WHERE id = v_invite.id;

            GET DIAGNOSTICS v_invite_rows = ROW_COUNT;

            IF v_invite_rows = 0 THEN
                RETURN QUERY SELECT
                    'FAILURE'::TEXT,
                    'Unable to update supplier invite.'::TEXT,
                    NULL::JSONB,
                    NULL::JSONB;
                RETURN;
            END IF;

            ----------------------------------------------------------------------
            -- CAPTURE UPDATED SNAPSHOTS
            ----------------------------------------------------------------------
            SELECT row_to_json(s.*) INTO v_updated_supplier
            FROM suppliers s
            WHERE s.id = p_invite_supplier_id
              AND s.tenant_id = p_tenant_id;

            SELECT row_to_json(si.*) INTO v_updated_invite
            FROM supplier_invites si
            WHERE si.id = v_invite.id;

            ----------------------------------------------------------------------
            -- LOG ACTIVITY (BEST-EFFORT)
            ----------------------------------------------------------------------
            BEGIN
                PERFORM log_activity(
                    'cancel_supplier_invitation',
                    format('User %s cancelled supplier invitation %s', p_user_name, p_invite_supplier_id),
                    'suppliers',
                    p_invite_supplier_id,
                    'user',
                    p_user_id,
                    v_existing_data,
                    p_tenant_id
                );
            EXCEPTION WHEN OTHERS THEN
                v_log_success := FALSE;
            END;

            ----------------------------------------------------------------------
            -- RETURN SUCCESS RESPONSE
            ----------------------------------------------------------------------
            RETURN QUERY SELECT
                'SUCCESS'::TEXT,
                'Supplier invitation cancelled successfully.'::TEXT,
                v_updated_supplier::JSONB,
                v_updated_invite::JSONB;
        END;
        $$;

        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(
            <<<'SQL'
            DO $$
        DECLARE
            r RECORD;
        BEGIN
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'cancel_supplier_invitation'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;
        SQL
        );
    }
};
