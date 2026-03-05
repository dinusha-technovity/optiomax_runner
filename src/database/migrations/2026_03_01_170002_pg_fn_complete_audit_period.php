<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * PostgreSQL function to complete an audit period (in-progress → completed)
     */
    public function up(): void
    {
        DB::unprepared("
            CREATE OR REPLACE FUNCTION complete_audit_period(
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

                -- Validate status transition: only 'in-progress' can move to 'completed'
                IF v_current_status != 'in-progress' THEN
                    RETURN jsonb_build_object(
                        'success', FALSE,
                        'status', 422,
                        'message', 'Cannot complete audit period. Only periods with status ''in-progress'' can be completed. Current status: ' || v_current_status,
                        'data', NULL
                    );
                END IF;

                -- Update status to completed
                UPDATE audit_periods
                SET status = 'completed',
                    updated_by = p_user_id,
                    updated_at = p_current_time
                WHERE id = p_period_id
                    AND tenant_id = p_tenant_id;

                -- Log activity
                PERFORM log_activity(
                    'audit_period_completed',
                    p_user_name || ' completed audit period: ' || v_period_name,
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
                    'message', 'Audit period completed successfully',
                    'data', jsonb_build_object(
                        'id', p_period_id,
                        'period_name', v_period_name,
                        'previous_status', v_current_status,
                        'new_status', 'completed'
                    )
                );

            EXCEPTION
                WHEN OTHERS THEN
                    RETURN jsonb_build_object(
                        'success', FALSE,
                        'status', 500,
                        'message', 'Error completing audit period: ' || SQLERRM,
                        'data', NULL
                    );
            END;
            \$\$;

            COMMENT ON FUNCTION complete_audit_period IS 'Transition audit period status from in-progress to completed with activity logging';
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS complete_audit_period');
    }
};
