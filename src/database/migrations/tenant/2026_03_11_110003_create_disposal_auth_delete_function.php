<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Function: Delete (Soft Delete) Disposal Authorization User
     * 
     * This function handles soft deletion of disposal authorization records.
     * It sets the deleted_at timestamp and logs the activity.
     * 
     * ISO 55001:2014 Compliant
     */
    public function up(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS delete_disposal_auth_user CASCADE');
        
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION delete_disposal_auth_user(
                p_tenant_id BIGINT,
                p_user_id BIGINT,
                p_current_user_name TEXT,
                p_disposal_auth_id BIGINT,
                p_current_time TIMESTAMPTZ DEFAULT NOW()
            )
            RETURNS JSONB
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_registration_number VARCHAR(50);
                v_target_user_name TEXT;
                v_log_id BIGINT;
                v_existing_count INTEGER;
            BEGIN
                -- Check if authorization exists and not already deleted
                SELECT COUNT(*) INTO v_existing_count
                FROM disposal_authorization_users
                WHERE id = p_disposal_auth_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;

                IF v_existing_count = 0 THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'Disposal authorization record not found or already deleted'
                    );
                END IF;
                
                -- Get details before deletion
                SELECT 
                    da.registration_number,
                    u.name
                INTO 
                    v_registration_number,
                    v_target_user_name
                FROM disposal_authorization_users da
                JOIN users u ON u.id = da.user_id
                WHERE da.id = p_disposal_auth_id;
                
                -- Soft delete
                UPDATE disposal_authorization_users
                SET
                    deleted_at = p_current_time,
                    isactive = FALSE,
                    updated_by = p_user_id,
                    updated_at = p_current_time
                WHERE id = p_disposal_auth_id
                    AND tenant_id = p_tenant_id;
                
                -- Log activity
                BEGIN
                    v_log_id := log_activity(
                        'disposal_auth.deleted',
                        'Disposal authorization deleted: ' || v_registration_number || ' for user ' || v_target_user_name || ' by ' || p_current_user_name,
                        'disposal_authorization_users',
                        p_disposal_auth_id,
                        'user',
                        p_user_id,
                        jsonb_build_object(
                            'registration_number', v_registration_number,
                            'target_user_name', v_target_user_name,
                            'deleted_by', p_current_user_name,
                            'deleted_at', p_current_time
                        ),
                        p_tenant_id
                    );
                EXCEPTION WHEN OTHERS THEN
                    RAISE NOTICE 'Log activity failed: %', SQLERRM;
                END;
                
                RETURN jsonb_build_object(
                    'status', 'SUCCESS',
                    'message', 'Disposal authorization deleted successfully',
                    'disposal_auth_id', p_disposal_auth_id,
                    'registration_number', v_registration_number,
                    'activity_log_id', v_log_id
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
        DB::unprepared('DROP FUNCTION IF EXISTS delete_disposal_auth_user CASCADE');
    }
};
