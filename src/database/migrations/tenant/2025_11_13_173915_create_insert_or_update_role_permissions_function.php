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
            CREATE OR REPLACE FUNCTION insert_or_update_role_permissions(
                IN p_role_id BIGINT,
                IN p_permission_ids JSONB,   -- JSON array of permission IDs
                IN p_tenant_id BIGINT,
                IN p_current_time TIMESTAMPTZ DEFAULT now(),
                IN p_user_id BIGINT DEFAULT NULL,
                IN p_user_name VARCHAR DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                role_id BIGINT,
                permission_id BIGINT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_permission_id BIGINT;
                v_old_record JSONB;
                v_new_record JSONB;
                v_log_data JSONB;
                v_log_success BOOLEAN;
                v_error_message TEXT;
            BEGIN
                -- Validate role
                IF p_role_id IS NULL OR p_role_id = 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Role ID cannot be null or zero', NULL::BIGINT, NULL::BIGINT;
                    RETURN;
                END IF;

                -- Loop through JSON array of permission IDs
                FOR v_permission_id IN
                    SELECT value::BIGINT
                    FROM jsonb_array_elements_text(p_permission_ids)
                LOOP
                    -- Validate permission
                    IF v_permission_id IS NULL OR v_permission_id = 0 THEN
                        RETURN QUERY SELECT 'FAILURE', 'Permission ID cannot be null or zero', p_role_id, v_permission_id;
                        CONTINUE;
                    END IF;

                    -- Check if permission already exists for this role (skip)
                    PERFORM 1
                    FROM permission_role pr
                    WHERE pr.role_id = p_role_id
                    AND pr.permission_id = v_permission_id;

                    IF FOUND THEN
                        RETURN QUERY SELECT 'SKIPPED', 'Permission already assigned to role, no changes applied', p_role_id, v_permission_id;

                        -- Log skip
                        IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
                            BEGIN
                                v_log_data := jsonb_build_object(
                                    'role_id', p_role_id,
                                    'permission_id', v_permission_id,
                                    'action', 'skipped'
                                );

                                PERFORM log_activity(
                                    'role_permission.skipped',
                                    'User ' || p_user_name || ' skipped adding permission ' || v_permission_id || ' to role ' || p_role_id,
                                    'permission_role',
                                    v_permission_id,
                                    'user',
                                    p_user_id,
                                    v_log_data,
                                    p_tenant_id
                                );
                            EXCEPTION WHEN OTHERS THEN
                                v_error_message := 'Logging failed: ' || SQLERRM;
                            END;
                        END IF;

                        CONTINUE;
                    END IF;

                    -- Insert new record
                    INSERT INTO permission_role (
                        role_id,
                        permission_id,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        p_role_id,
                        v_permission_id,
                        p_current_time,
                        p_current_time
                    )
                    RETURNING to_jsonb(permission_role.*) INTO v_new_record;

                    RETURN QUERY SELECT 'SUCCESS', 'Role-Permission relation created successfully', p_role_id, v_permission_id;

                    -- Log insert
                    IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
                        BEGIN
                            v_log_data := jsonb_build_object(
                                'role_id', p_role_id,
                                'permission_id', v_permission_id,
                                'new_data', v_new_record
                            );

                            PERFORM log_activity(
                                'role_permission.created',
                                'User ' || p_user_name || ' added permission ' || v_permission_id || ' to role ' || p_role_id,
                                'permission_role',
                                v_permission_id,
                                'user',
                                p_user_id,
                                v_log_data,
                                p_tenant_id
                            );
                        EXCEPTION WHEN OTHERS THEN
                            v_error_message := 'Logging failed: ' || SQLERRM;
                        END;
                    END IF;
                END LOOP;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS insert_or_update_role_permissions(BIGINT, JSONB, BIGINT, TIMESTAMPTZ, BIGINT, VARCHAR);");
    }
};
