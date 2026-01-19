<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
                    WHERE proname = 'delete_employee'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
            CREATE OR REPLACE FUNCTION delete_employee(
                p_employee_id BIGINT,
                p_tenant_id BIGINT,
                p_user_id BIGINT,
                p_current_time TIMESTAMPTZ
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                rows_updated INT;
                v_log_data JSONB;
                v_log_success BOOLEAN;
                v_error_message TEXT;
                v_employee_exists BOOLEAN;
                v_is_current_user BOOLEAN := FALSE;
            BEGIN
                -- Check if user exists and has employee account enabled
                SELECT TRUE INTO v_employee_exists
                FROM users
                WHERE id = p_employee_id
                AND tenant_id = p_tenant_id
                AND employee_account_enabled = TRUE
                LIMIT 1;

                IF NOT FOUND THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT AS status,
                        'Employee not found or already disabled'::TEXT AS message;
                    RETURN;
                END IF;

                -- Check if this is the current user
                v_is_current_user := (p_employee_id = p_user_id);

                -- Disable employee account (set employee_account_enabled to false)
                -- If it's the current user, also disable user account
                UPDATE users
                SET employee_account_enabled = FALSE,
                    user_account_enabled = CASE WHEN v_is_current_user THEN FALSE ELSE user_account_enabled END,
                    updated_at = p_current_time
                WHERE id = p_employee_id
                AND tenant_id = p_tenant_id
                AND employee_account_enabled = TRUE;

                GET DIAGNOSTICS rows_updated = ROW_COUNT;

                IF rows_updated > 0 THEN
                    -- Build log data
                    v_log_data := jsonb_build_object(
                        'employee_id', p_employee_id,
                        'employee_account_disabled', TRUE,
                        'user_account_disabled', v_is_current_user,
                        'disabled_at', p_current_time,
                        'tenant_id', p_tenant_id,
                        'disabled_by', p_user_id
                    );

                    -- Log activity if user info provided
                    IF p_user_id IS NOT NULL THEN
                        BEGIN
                            PERFORM log_activity(
                                'employee.disabled',
                                'Employee account disabled: ' || p_employee_id,
                                'users',
                                p_employee_id,
                                'user',
                                p_user_id,
                                v_log_data,
                                p_tenant_id
                            );
                            v_log_success := TRUE;
                        EXCEPTION WHEN OTHERS THEN
                            v_log_success := FALSE;
                            v_error_message := 'Logging failed: ' || SQLERRM;
                        END;
                    END IF;

                    RETURN QUERY SELECT
                        'SUCCESS'::TEXT AS status,
                        'Employee account disabled successfully'::TEXT AS message;
                ELSE
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT AS status,
                        'No rows updated. Employee not found or already disabled.'::TEXT AS message;
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
        DB::unprepared("DROP FUNCTION IF EXISTS delete_employee(BIGINT, BIGINT, BIGINT, TIMESTAMPTZ);");
    }
};
