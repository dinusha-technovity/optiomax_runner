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
         DB::unprepared(<<<SQL
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'delete_asset_audit_variable_assignment'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION delete_asset_audit_variable_assignment(
                p_tenant_id BIGINT,
                p_user_id BIGINT,
                p_current_time TIMESTAMPTZ,
                p_assignment_id BIGINT DEFAULT NULL,
                p_assignable_type_id BIGINT DEFAULT NULL,
                p_assignable_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                deleted_count INT
            )
            LANGUAGE plpgsql
            AS \$\$
            DECLARE
                rows_updated INT;
                v_log_data JSONB;
                v_log_success BOOLEAN;
                v_error_message TEXT;
                v_assignment_exists BOOLEAN;
            BEGIN
                -- Validate inputs: must provide either assignment_id OR (assignable_type_id + assignable_id)
                IF p_assignment_id IS NULL AND (p_assignable_type_id IS NULL OR p_assignable_id IS NULL) THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT AS status,
                        'Must provide either assignment_id or both assignable_type_id and assignable_id'::TEXT AS message,
                        0::INT AS deleted_count;
                    RETURN;
                END IF;

                -- Validate tenant_id
                IF p_tenant_id IS NULL OR p_tenant_id < 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        0::INT AS deleted_count;
                    RETURN;
                END IF;

                -- Case 1: Delete single assignment by assignment_id
                IF p_assignment_id IS NOT NULL THEN
                    -- Check if record exists and is not already deleted
                    SELECT TRUE INTO v_assignment_exists
                    FROM asset_audit_variable_assignments
                    WHERE id = p_assignment_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL
                    LIMIT 1;

                    IF NOT FOUND THEN
                        RETURN QUERY SELECT
                            'FAILURE'::TEXT AS status,
                            'Assignment not found or already deleted'::TEXT AS message,
                            0::INT AS deleted_count;
                        RETURN;
                    END IF;

                    -- Soft delete the assignment
                    UPDATE asset_audit_variable_assignments
                    SET deleted_at = p_current_time,
                        is_active = FALSE
                    WHERE id = p_assignment_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;

                    GET DIAGNOSTICS rows_updated = ROW_COUNT;

                    IF rows_updated > 0 THEN
                        -- Build log data
                        v_log_data := jsonb_build_object(
                            'assignment_id', p_assignment_id,
                            'deleted_at', p_current_time,
                            'tenant_id', p_tenant_id,
                            'deleted_by', p_user_id,
                            'delete_type', 'single'
                        );

                        -- Log activity if user info provided
                        IF p_user_id IS NOT NULL THEN
                            BEGIN
                                PERFORM log_activity(
                                    'asset_audit_variable_assignment.deleted',
                                    'Asset audit variable assignment deleted: ' || p_assignment_id,
                                    'asset_audit_variable_assignment',
                                    p_assignment_id,
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
                            'Assignment deleted successfully'::TEXT AS message,
                            rows_updated::INT AS deleted_count;
                    ELSE
                        RETURN QUERY SELECT
                            'FAILURE'::TEXT AS status,
                            'No rows updated. Assignment not found or already deleted.'::TEXT AS message,
                            0::INT AS deleted_count;
                    END IF;

                -- Case 2: Delete all assignments for a specific assignable entity
                ELSE
                    -- Check if any records exist for the given assignable
                    SELECT TRUE INTO v_assignment_exists
                    FROM asset_audit_variable_assignments
                    WHERE assignable_type_id = p_assignable_type_id
                    AND assignable_id = p_assignable_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL
                    LIMIT 1;

                    IF NOT FOUND THEN
                        RETURN QUERY SELECT
                            'FAILURE'::TEXT AS status,
                            'No assignments found for the given criteria'::TEXT AS message,
                            0::INT AS deleted_count;
                        RETURN;
                    END IF;

                    -- Soft delete all assignments for the assignable entity
                    UPDATE asset_audit_variable_assignments
                    SET deleted_at = p_current_time,
                        is_active = FALSE
                    WHERE assignable_type_id = p_assignable_type_id
                    AND assignable_id = p_assignable_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;

                    GET DIAGNOSTICS rows_updated = ROW_COUNT;

                    IF rows_updated > 0 THEN
                        -- Build log data
                        v_log_data := jsonb_build_object(
                            'assignable_type_id', p_assignable_type_id,
                            'assignable_id', p_assignable_id,
                            'deleted_count', rows_updated,
                            'deleted_at', p_current_time,
                            'tenant_id', p_tenant_id,
                            'deleted_by', p_user_id,
                            'delete_type', 'bulk'
                        );

                        -- Log activity if user info provided
                        IF p_user_id IS NOT NULL THEN
                            BEGIN
                                PERFORM log_activity(
                                    'asset_audit_variable_assignment.bulk_deleted',
                                    'Bulk deleted ' || rows_updated || ' assignments for assignable_type_id: ' || p_assignable_type_id || ', assignable_id: ' || p_assignable_id,
                                    'asset_audit_variable_assignment',
                                    p_assignable_id,
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
                            'Deleted ' || rows_updated || ' assignment(s) successfully'::TEXT AS message,
                            rows_updated::INT AS deleted_count;
                    ELSE
                        RETURN QUERY SELECT
                            'FAILURE'::TEXT AS status,
                            'No rows updated. Assignments not found or already deleted.'::TEXT AS message,
                            0::INT AS deleted_count;
                    END IF;
                END IF;
            END;
            \$\$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS delete_asset_audit_variable_assignment(BIGINT, BIGINT, TIMESTAMPTZ, BIGINT, BIGINT, BIGINT);");
    }
};