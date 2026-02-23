<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Create unassign audit staff from groups function
     * 
     * Removes audit staff assignments from one or more audit group(s)
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- Drop existing function if exists
        DROP FUNCTION IF EXISTS unassign_audit_staff_from_groups(BIGINT, BIGINT[], BIGINT, BIGINT, VARCHAR, TIMESTAMPTZ) CASCADE;

        CREATE OR REPLACE FUNCTION unassign_audit_staff_from_groups(
            p_staff_id BIGINT,
            p_group_ids BIGINT[],
            p_tenant_id BIGINT,
            p_user_id BIGINT,
            p_user_name VARCHAR DEFAULT NULL,
            p_current_time TIMESTAMPTZ DEFAULT now()
        ) RETURNS JSONB LANGUAGE plpgsql AS $$
        DECLARE
            v_group_id BIGINT;
            v_removed_count INT := 0;
            v_not_found_count INT := 0;
            v_auditor_code VARCHAR;
            v_auditor_name VARCHAR;
            v_group_names TEXT := '';
            v_group_name VARCHAR;
        BEGIN
            -- Validate inputs
            IF p_staff_id IS NULL THEN
                RETURN jsonb_build_object(
                    'status', 'ERROR',
                    'message', 'Staff ID is required'
                );
            END IF;

            IF p_group_ids IS NULL OR array_length(p_group_ids, 1) IS NULL THEN
                RETURN jsonb_build_object(
                    'status', 'ERROR',
                    'message', 'At least one audit group ID is required'
                );
            END IF;

            -- Check if audit staff exists and get details
            SELECT 
                ast.auditor_code,
                u.name
            INTO v_auditor_code, v_auditor_name
            FROM audit_staff ast
            INNER JOIN users u ON u.id = ast.user_id
            WHERE ast.id = p_staff_id
                AND ast.tenant_id = p_tenant_id
                AND ast.deleted_at IS NULL;

            IF v_auditor_code IS NULL THEN
                RETURN jsonb_build_object(
                    'status', 'ERROR',
                    'message', 'Audit staff not found'
                );
            END IF;

            -- Loop through group IDs and unassign
            FOREACH v_group_id IN ARRAY p_group_ids
            LOOP
                -- Get group name
                SELECT name INTO v_group_name
                FROM audit_groups
                WHERE id = v_group_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;

                -- Check if assignment exists
                IF NOT EXISTS(
                    SELECT 1 FROM audit_staff_group_assignments
                    WHERE audit_staff_id = p_staff_id
                        AND audit_group_id = v_group_id
                        AND tenant_id = p_tenant_id
                        AND deleted_at IS NULL
                ) THEN
                    v_not_found_count := v_not_found_count + 1;
                    CONTINUE;
                END IF;

                -- Soft delete the assignment
                UPDATE audit_staff_group_assignments
                SET 
                    deleted_at = p_current_time,
                    isactive = FALSE,
                    updated_at = p_current_time
                WHERE audit_staff_id = p_staff_id
                    AND audit_group_id = v_group_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;

                v_removed_count := v_removed_count + 1;
                IF v_group_name IS NOT NULL THEN
                    v_group_names := v_group_names || v_group_name || ', ';
                END IF;
            END LOOP;

            -- Remove trailing comma
            v_group_names := TRIM(TRAILING ', ' FROM v_group_names);

            -- Log activity
            IF v_removed_count > 0 THEN
                BEGIN
                    PERFORM log_activity(
                        'audit_staff.groups_unassigned',
                        'Removed ' || v_removed_count || ' audit group(s) from "' || v_auditor_name || '" (Code: ' || v_auditor_code || ')',
                        'audit_staff_group_assignments',
                        p_staff_id,
                        'user',
                        p_user_id,
                        jsonb_build_object(
                            'staff_id', p_staff_id,
                            'auditor_code', v_auditor_code,
                            'auditor_name', v_auditor_name,
                            'group_ids', p_group_ids,
                            'group_names', v_group_names,
                            'removed_count', v_removed_count,
                            'not_found_count', v_not_found_count,
                            'unassigned_by', p_user_name,
                            'action_time', p_current_time
                        ),
                        p_tenant_id
                    );
                EXCEPTION WHEN OTHERS THEN
                    RAISE NOTICE 'Log activity failed: %', SQLERRM;
                END;
            END IF;

            RETURN jsonb_build_object(
                'status', 'SUCCESS',
                'message', v_removed_count || ' audit group(s) unassigned successfully',
                'removed_count', v_removed_count,
                'not_found_count', v_not_found_count,
                'total_requested', array_length(p_group_ids, 1)
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
        DB::unprepared('DROP FUNCTION IF EXISTS unassign_audit_staff_from_groups');
    }
};
