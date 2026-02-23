<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Create delete audit staff function
     * 
     * Soft deletes an audit staff member and all their assignments
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- Drop existing function if exists
        DROP FUNCTION IF EXISTS delete_audit_staff(BIGINT, BIGINT, BIGINT, VARCHAR, TIMESTAMPTZ) CASCADE;

        CREATE OR REPLACE FUNCTION delete_audit_staff(
            p_staff_id BIGINT,
            p_tenant_id BIGINT,
            p_user_id BIGINT,
            p_user_name VARCHAR DEFAULT NULL,
            p_current_time TIMESTAMPTZ DEFAULT now()
        ) RETURNS JSONB LANGUAGE plpgsql AS $$
        DECLARE
            v_staff_data JSONB;
            v_user_name VARCHAR;
            v_auditor_code VARCHAR;
            v_assignment_count INT;
        BEGIN
            -- Get staff details before deletion
            SELECT 
                jsonb_build_object(
                    'id', ast.id,
                    'user_id', ast.user_id,
                    'auditor_code', ast.auditor_code
                ),
                u.name,
                ast.auditor_code
            INTO v_staff_data, v_user_name, v_auditor_code
            FROM audit_staff ast
            INNER JOIN users u ON u.id = ast.user_id
            WHERE ast.id = p_staff_id
                AND ast.tenant_id = p_tenant_id
                AND ast.deleted_at IS NULL;

            IF v_staff_data IS NULL THEN
                RETURN jsonb_build_object(
                    'status', 'ERROR',
                    'message', 'Audit staff not found'
                );
            END IF;

            -- Count existing assignments
            SELECT COUNT(*) INTO v_assignment_count
            FROM audit_staff_group_assignments
            WHERE audit_staff_id = p_staff_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

            -- Soft delete all assignments
            UPDATE audit_staff_group_assignments
            SET 
                deleted_at = p_current_time,
                isactive = FALSE,
                updated_at = p_current_time
            WHERE audit_staff_id = p_staff_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

            -- Soft delete audit staff
            UPDATE audit_staff
            SET 
                deleted_at = p_current_time,
                isactive = FALSE,
                updated_at = p_current_time
            WHERE id = p_staff_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

            -- Log activity
            BEGIN
                PERFORM log_activity(
                    'audit_staff.deleted',
                    'Audit staff deleted for "' || v_user_name || '" (Code: ' || v_auditor_code || ')',
                    'audit_staff',
                    p_staff_id,
                    'user',
                    p_user_id,
                    jsonb_build_object(
                        'staff_data', v_staff_data,
                        'user_name', v_user_name,
                        'auditor_code', v_auditor_code,
                        'deleted_assignments', v_assignment_count,
                        'deleted_by', p_user_name,
                        'action_time', p_current_time
                    ),
                    p_tenant_id
                );
            EXCEPTION WHEN OTHERS THEN
                RAISE NOTICE 'Log activity failed: %', SQLERRM;
            END;

            RETURN jsonb_build_object(
                'status', 'SUCCESS',
                'message', 'Audit staff deleted successfully',
                'staff_id', p_staff_id,
                'auditor_code', v_auditor_code,
                'deleted_assignments', v_assignment_count
            );

        EXCEPTION
            WHEN OTHERS THEN
                RETURN jsonb_build_object(
                    'status', 'ERROR',
                    'message', 'An error occurred: ' || SQLERRM
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
        DB::unprepared('DROP FUNCTION IF EXISTS delete_audit_staff');
    }
};
