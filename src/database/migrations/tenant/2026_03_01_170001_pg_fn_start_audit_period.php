<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * PostgreSQL function to start an audit period (active → in-progress)
     */
    public function up(): void
    {
        DB::unprepared("
            CREATE OR REPLACE FUNCTION start_audit_period(
                p_tenant_id BIGINT,
                p_user_id BIGINT,
                p_period_id BIGINT,
                p_user_name TEXT DEFAULT NULL,
                p_current_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
            RETURNS JSONB
            LANGUAGE plpgsql
            AS \$\$
            DECLARE
                v_period_name TEXT;
                v_current_status TEXT;
            BEGIN
                -- Fetch the period
                SELECT period_name, status
                INTO v_period_name, v_current_status
                FROM audit_periods
                WHERE id = p_period_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;

                IF v_period_name IS NULL THEN
                    RETURN jsonb_build_object(
                        'success', FALSE,
                        'status', 404,
                        'message', 'Audit period not found',
                        'data', NULL
                    );
                END IF;

                -- Validate status transition: only 'active' can move to 'in-progress'
                IF v_current_status != 'active' THEN
                    RETURN jsonb_build_object(
                        'success', FALSE,
                        'status', 422,
                        'message', 'Cannot start audit period. Only periods with status ''active'' can be started. Current status: ' || v_current_status,
                        'data', NULL
                    );
                END IF;

                -- Update status to in-progress
                UPDATE audit_periods
                SET status = 'in-progress',
                    updated_by = p_user_id,
                    updated_at = p_current_time
                WHERE id = p_period_id
                    AND tenant_id = p_tenant_id;

                -- Log activity
                PERFORM log_activity(
                    'audit_period_started',
                    p_user_name || ' started audit period: ' || v_period_name,
                    'Audit Period',
                    p_period_id,
                    'User',
                    p_user_id,
                    NULL,
                    p_tenant_id
                );

                -- Return success response
                RETURN jsonb_build_object(
                    'success', TRUE,
                    'status', 200,
                    'message', 'Audit period started successfully',
                    'data', jsonb_build_object(
                        'id', p_period_id,
                        'period_name', v_period_name,
                        'previous_status', v_current_status,
                        'new_status', 'in-progress'
                    )
                );

            EXCEPTION
                WHEN OTHERS THEN
                    RETURN jsonb_build_object(
                        'success', FALSE,
                        'status', 500,
                        'message', 'Error starting audit period: ' || SQLERRM,
                        'data', NULL
                    );
            END;
            \$\$;

            COMMENT ON FUNCTION start_audit_period IS 'Transition audit period status from active to in-progress with activity logging';
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS start_audit_period');
    }
};
