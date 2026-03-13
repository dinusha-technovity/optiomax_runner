<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Function: Revoke Disposal Authorization User
     * 
     * This function handles revocation of disposal authorization records.
     * It updates the status to 'revoked', logs the activity, and prepares
     * data for email notification.
     * 
     * ISO 55001:2014 Compliant
     */
    public function up(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS revoke_disposal_auth_user CASCADE');
        
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION revoke_disposal_auth_user(
                p_tenant_id BIGINT,
                p_user_id BIGINT,
                p_current_user_name TEXT,
                p_disposal_auth_id BIGINT,
                p_revocation_reason TEXT DEFAULT NULL,
                p_current_time TIMESTAMPTZ DEFAULT NOW()
            )
            RETURNS JSONB
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_registration_number VARCHAR(50);
                v_old_status VARCHAR;
                v_target_user_name TEXT;
                v_target_user_email TEXT;
                v_log_id BIGINT;
                v_existing_count INTEGER;
            BEGIN
                -- Check if authorization exists
                SELECT COUNT(*) INTO v_existing_count
                FROM disposal_authorization_users
                WHERE id = p_disposal_auth_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;

                IF v_existing_count = 0 THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'Disposal authorization record not found'
                    );
                END IF;
                
                -- Get current status and user details
                SELECT 
                    da.registration_number,
                    da.status,
                    u.name,
                    u.email
                INTO 
                    v_registration_number,
                    v_old_status,
                    v_target_user_name,
                    v_target_user_email
                FROM disposal_authorization_users da
                JOIN users u ON u.id = da.user_id
                WHERE da.id = p_disposal_auth_id;
                
                -- Check if already revoked
                IF v_old_status = 'revoked' THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'Authorization is already revoked'
                    );
                END IF;
                
                -- Update to revoked status
                UPDATE disposal_authorization_users
                SET
                    status = 'revoked',
                    revoked_by = p_user_id,
                    revoked_at = p_current_time,
                    revocation_reason = p_revocation_reason,
                    isactive = FALSE,
                    updated_by = p_user_id,
                    updated_at = p_current_time
                WHERE id = p_disposal_auth_id
                    AND tenant_id = p_tenant_id;
                
                -- Log activity
                BEGIN
                    v_log_id := log_activity(
                        'disposal_auth.revoked',
                        'Disposal authorization revoked: ' || v_registration_number || ' for user ' || v_target_user_name || ' by ' || p_current_user_name,
                        'disposal_authorization_users',
                        p_disposal_auth_id,
                        'user',
                        p_user_id,
                        jsonb_build_object(
                            'registration_number', v_registration_number,
                            'target_user_name', v_target_user_name,
                            'target_user_email', v_target_user_email,
                            'old_status', v_old_status,
                            'new_status', 'revoked',
                            'revocation_reason', p_revocation_reason,
                            'revoked_by', p_current_user_name,
                            'revoked_at', p_current_time
                        ),
                        p_tenant_id
                    );
                EXCEPTION WHEN OTHERS THEN
                    RAISE NOTICE 'Log activity failed: %', SQLERRM;
                END;
                
                RETURN jsonb_build_object(
                    'status', 'SUCCESS',
                    'message', 'Disposal authorization revoked successfully',
                    'disposal_auth_id', p_disposal_auth_id,
                    'registration_number', v_registration_number,
                    'activity_log_id', v_log_id,
                    'target_user_email', v_target_user_email,
                    'target_user_name', v_target_user_name,
                    'send_email', TRUE
                );

            EXCEPTION
                WHEN OTHERS THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'Database error: ' || SQLERRM
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
        DB::unprepared('DROP FUNCTION IF EXISTS revoke_disposal_auth_user CASCADE');
    }
};
