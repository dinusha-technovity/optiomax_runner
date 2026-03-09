<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Function: delete_audit_session - FIXED for actual table structure
     */
    public function up(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS delete_audit_session CASCADE');
        
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION delete_audit_session(
                p_tenant_id BIGINT,
                p_user_id BIGINT,
                p_session_id BIGINT,
                p_user_name TEXT,
                p_current_time TIMESTAMPTZ DEFAULT NOW()
            )
            RETURNS JSONB
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_session_code VARCHAR(50);
                v_session_status VARCHAR(50);
                v_old_data JSONB;
                v_log_id BIGINT;
            BEGIN
                -- Check if session exists and get current status
                SELECT session_code, status, to_jsonb(s.*)
                INTO v_session_code, v_session_status, v_old_data
                FROM audit_sessions s
                WHERE id = p_session_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;

                IF v_session_code IS NULL THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'Audit session not found or already deleted'
                    );
                END IF;

                -- Business rule: Only allow deletion of draft or cancelled sessions
                IF v_session_status NOT IN ('draft', 'cancelled') THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'Cannot delete audit session with status: ' || v_session_status || '. Only draft or cancelled sessions can be deleted.'
                    );
                END IF;

                -- Soft delete the session
                UPDATE audit_sessions
                SET deleted_at = p_current_time,
                    updated_by = p_user_id,
                    updated_at = p_current_time,
                    isactive = FALSE
                WHERE id = p_session_id
                    AND tenant_id = p_tenant_id;

                -- Soft delete related records (cascade)
                UPDATE audit_sessions_auditors
                SET deleted_at = p_current_time
                WHERE audit_session_id = p_session_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;

                UPDATE audit_sessions_groups
                SET deleted_at = p_current_time
                WHERE audit_session_id = p_session_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;

                -- Log deletion activity
                BEGIN
                    v_log_id := log_activity(
                        'audit_session.deleted',
                        'Audit session deleted: ' || v_session_code || ' by ' || p_user_name,
                        'audit_sessions',
                        p_session_id,
                        'user',
                        p_user_id,
                        jsonb_build_object(
                            'session_code', v_session_code,
                            'action', 'delete',
                            'old_data', v_old_data,
                            'deleted_by', p_user_name,
                            'deleted_at', p_current_time
                        ),
                        p_tenant_id
                    );
                EXCEPTION WHEN OTHERS THEN
                    RAISE NOTICE 'Log activity failed: %', SQLERRM;
                END;

                RETURN jsonb_build_object(
                    'status', 'SUCCESS',
                    'message', 'Audit session deleted successfully',
                    'session_code', v_session_code,
                    'activity_log_id', v_log_id
                );

            EXCEPTION
                WHEN OTHERS THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'Failed to delete audit session: ' || SQLERRM
                    );
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS delete_audit_session CASCADE');
    }
};
