<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Create assign audit staff to groups function
     * 
     * Assigns one or more audit group(s) to an audit staff member
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- Drop existing function if exists
        DROP FUNCTION IF EXISTS assign_audit_staff_to_groups(BIGINT, BIGINT[], BIGINT, BIGINT, VARCHAR, TIMESTAMPTZ) CASCADE;

        CREATE OR REPLACE FUNCTION assign_audit_staff_to_groups(
            p_staff_id BIGINT,
            p_group_ids BIGINT[],
            p_tenant_id BIGINT,
            p_user_id BIGINT,
            p_user_name VARCHAR DEFAULT NULL,
            p_current_time TIMESTAMPTZ DEFAULT now()
        ) RETURNS JSONB LANGUAGE plpgsql AS $$
        DECLARE
            v_group_id BIGINT;
            v_added_count INT := 0;
            v_skipped_count INT := 0;
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

            -- Loop through group IDs and assign
            FOREACH v_group_id IN ARRAY p_group_ids
            LOOP
                -- Check if group exists
                SELECT name INTO v_group_name
                FROM audit_groups
                WHERE id = v_group_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;

                IF v_group_name IS NULL THEN
                    v_skipped_count := v_skipped_count + 1;
                    CONTINUE;
                END IF;

                -- Check if assignment already exists
                IF EXISTS(
                    SELECT 1 FROM audit_staff_group_assignments
                    WHERE audit_staff_id = p_staff_id
                        AND audit_group_id = v_group_id
                        AND tenant_id = p_tenant_id
                        AND deleted_at IS NULL
                ) THEN
                    v_skipped_count := v_skipped_count + 1;
                    CONTINUE;
                END IF;

                -- Insert assignment
                INSERT INTO audit_staff_group_assignments (
                    audit_staff_id,
                    audit_group_id,
                    tenant_id,
                    isactive,
                    created_at,
                    updated_at
                ) VALUES (
                    p_staff_id,
                    v_group_id,
                    p_tenant_id,
                    TRUE,
                    p_current_time,
                    p_current_time
                );

                v_added_count := v_added_count + 1;
                v_group_names := v_group_names || v_group_name || ', ';
            END LOOP;

            -- Remove trailing comma
            v_group_names := TRIM(TRAILING ', ' FROM v_group_names);

            -- Log activity
            IF v_added_count > 0 THEN
                BEGIN
                    PERFORM log_activity(
                        'audit_staff.groups_assigned',
                        'Assigned ' || v_added_count || ' audit group(s) to "' || v_auditor_name || '" (Code: ' || v_auditor_code || ')',
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
                            'added_count', v_added_count,
                            'skipped_count', v_skipped_count,
                            'assigned_by', p_user_name,
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
                'message', v_added_count || ' audit group(s) assigned successfully',
                'added_count', v_added_count,
                'skipped_count', v_skipped_count,
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
        DB::unprepared('DROP FUNCTION IF EXISTS assign_audit_staff_to_groups');
    }
};
