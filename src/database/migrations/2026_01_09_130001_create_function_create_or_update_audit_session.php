<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates PostgreSQL function for audit session management with score calculation
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
                WHERE proname = 'create_or_update_audit_session'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION create_or_update_audit_session(
            IN p_session_id BIGINT,
            IN p_asset_item_id BIGINT,
            IN p_document JSONB,
            IN p_audit_by BIGINT,
            IN p_auditing_location_latitude VARCHAR(50),
            IN p_auditing_location_longitude VARCHAR(50),
            IN p_tenant_id BIGINT,
            IN p_current_time TIMESTAMPTZ,
            IN p_causer_id BIGINT DEFAULT NULL,
            IN p_causer_name TEXT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            session_id BIGINT,
            old_data JSONB,
            new_data JSONB
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            old_record JSONB;
            new_record JSONB;
            log_success BOOLEAN;
            v_session_id BIGINT;
            v_audit_sessions_number VARCHAR(100);
        BEGIN
            -- Validate required fields
            IF p_asset_item_id IS NULL OR p_asset_item_id = 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Asset item ID is required'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;

            IF p_tenant_id IS NULL OR p_tenant_id = 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Tenant ID cannot be null or zero'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;

            -- Verify asset item exists
            IF NOT EXISTS (
                SELECT 1 FROM asset_items 
                WHERE id = p_asset_item_id 
                AND tenant_id = p_tenant_id 
                AND deleted_at IS NULL
            ) THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Asset item not found'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;

            -- Check if this is an insert or update
            IF p_session_id IS NULL OR p_session_id = 0 THEN
                -- INSERT operation
                
                -- Auto-generate audit session number (ISO-compliant)
                v_audit_sessions_number := generate_audit_session_number();

                INSERT INTO asset_items_audit_sessions (
                    asset_item_id,
                    audit_sessions_number,
                    document,
                    audit_by,
                    auditing_location_latitude,
                    auditing_location_longitude,
                    tenant_id,
                    isactive,
                    created_at,
                    updated_at
                )
                VALUES (
                    p_asset_item_id,
                    v_audit_sessions_number,
                    p_document,
                    p_audit_by,
                    p_auditing_location_latitude,
                    p_auditing_location_longitude,
                    p_tenant_id,
                    true,
                    p_current_time,
                    p_current_time
                )
                RETURNING id INTO v_session_id;

                -- Get the inserted record
                SELECT to_jsonb(s) INTO new_record
                FROM asset_items_audit_sessions s
                WHERE id = v_session_id;

                -- Log the activity
                BEGIN
                    PERFORM log_activity(
                        'create_audit_session',
                        format('User %s created audit session for asset item %s', p_causer_name, p_asset_item_id),
                        'asset_items_audit_sessions',
                        v_session_id,
                        'user',
                        p_causer_id,
                        new_record,
                        p_tenant_id
                    );
                    log_success := TRUE;
                EXCEPTION WHEN OTHERS THEN
                    log_success := FALSE;
                END;

                RETURN QUERY SELECT 'SUCCESS'::TEXT, 'Audit session created successfully'::TEXT, v_session_id, NULL::JSONB, new_record;
                
            ELSE
                -- UPDATE operation
                
                -- Check if session exists
                IF NOT EXISTS (
                    SELECT 1 FROM asset_items_audit_sessions 
                    WHERE id = p_session_id 
                    AND tenant_id = p_tenant_id 
                    AND deleted_at IS NULL
                ) THEN
                    RETURN QUERY SELECT 'FAILURE'::TEXT, 'Audit session not found'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                -- Get old record before update
                SELECT to_jsonb(s) INTO old_record
                FROM asset_items_audit_sessions s
                WHERE id = p_session_id;

                -- Update the session (audit_sessions_number is immutable after creation)
                UPDATE asset_items_audit_sessions
                SET
                    asset_item_id = p_asset_item_id,
                    document = p_document,
                    audit_by = p_audit_by,
                    auditing_location_latitude = p_auditing_location_latitude,
                    auditing_location_longitude = p_auditing_location_longitude,
                    updated_at = p_current_time
                WHERE id = p_session_id;

                -- Get updated record
                SELECT to_jsonb(s) INTO new_record
                FROM asset_items_audit_sessions s
                WHERE id = p_session_id;

                -- Log the activity
                BEGIN
                    PERFORM log_activity(
                        'update_audit_session',
                        format('User %s updated audit session %s', p_causer_name, p_session_id),
                        'asset_items_audit_sessions',
                        p_session_id,
                        'user',
                        p_causer_id,
                        new_record,
                        p_tenant_id
                    );
                    log_success := TRUE;
                EXCEPTION WHEN OTHERS THEN
                    log_success := FALSE;
                END;

                RETURN QUERY SELECT 'SUCCESS'::TEXT, 'Audit session updated successfully'::TEXT, p_session_id, old_record, new_record;
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
                WHERE proname = 'create_or_update_audit_session'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;
        SQL);
    }
};