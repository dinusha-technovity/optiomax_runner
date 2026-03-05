<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * PostgreSQL function to soft delete audit period
     */
    public function up(): void
    {
        DB::unprepared("
            CREATE OR REPLACE FUNCTION delete_audit_period(
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
            BEGIN
                -- Check if audit period exists
                SELECT period_name
                INTO v_period_name
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
                
                -- Soft delete the audit period
                UPDATE audit_periods
                SET deleted_at = p_current_time,
                    isactive = FALSE,
                    updated_by = p_user_id,
                    updated_at = p_current_time
                WHERE id = p_period_id
                    AND tenant_id = p_tenant_id;
                
                -- Log activity
                PERFORM log_activity(
                    'audit_period_deleted',
                    p_user_name || ' deleted audit period: ' || v_period_name,
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
                    'message', 'Audit period deleted successfully',
                    'data', jsonb_build_object('id', p_period_id)
                );
                
            EXCEPTION
                WHEN OTHERS THEN
                    RETURN jsonb_build_object(
                        'success', FALSE,
                        'status', 500,
                        'message', 'Error deleting audit period: ' || SQLERRM,
                        'data', NULL
                    );
            END;
            \$\$;
            
            COMMENT ON FUNCTION delete_audit_period IS 'Soft delete audit period with activity logging';
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS delete_audit_period');
    }
};
