<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION insert_or_update_role_widgets(
                IN p_role_id BIGINT,
                IN p_widget_ids JSONB,   -- JSON array of widget IDs
                IN p_tenant_id BIGINT,
                IN p_settings JSONB DEFAULT '{}'::jsonb,
                IN p_is_active BOOLEAN DEFAULT true,
                IN p_current_time TIMESTAMPTZ DEFAULT now(),
                IN p_user_id BIGINT DEFAULT NULL,
                IN p_user_name VARCHAR DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                role_id BIGINT,
                widget_id BIGINT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_widget_id BIGINT;
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

                -- Loop through JSON array of widget IDs
                FOR v_widget_id IN
                    SELECT value::BIGINT
                    FROM jsonb_array_elements_text(p_widget_ids)
                LOOP
                    -- Validate widget
                    IF v_widget_id IS NULL OR v_widget_id = 0 THEN
                        RETURN QUERY SELECT 'FAILURE', 'Widget ID cannot be null or zero', p_role_id, v_widget_id;
                        CONTINUE;
                    END IF;

                    -- Check if already active (skip)
                    PERFORM 1
                    FROM role_widget rw
                    WHERE rw.role_id = p_role_id
                    AND rw.widget_id = v_widget_id
                    AND (rw.tenant_id = p_tenant_id OR p_tenant_id IS NULL)
                    AND rw.is_active = true
                    AND rw.deleted_at IS NULL;

                    IF FOUND THEN
                        RETURN QUERY SELECT 'SKIPPED', 'Widget already active for role, no changes applied', p_role_id, v_widget_id;

                        -- Log skip
                        IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
                            BEGIN
                                v_log_data := jsonb_build_object(
                                    'role_id', p_role_id,
                                    'widget_id', v_widget_id,
                                    'action', 'skipped'
                                );

                                PERFORM log_activity(
                                    'role_widget.skipped',
                                    'User ' || p_user_name || ' skipped adding widget ' || v_widget_id || ' to role ' || p_role_id,
                                    'role_widget',
                                    v_widget_id,
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

                    -- Try to fetch old record
                    SELECT to_jsonb(rw.*) INTO v_old_record
                    FROM role_widget rw
                    WHERE rw.role_id = p_role_id
                    AND rw.widget_id = v_widget_id
                    AND (rw.tenant_id = p_tenant_id OR p_tenant_id IS NULL)
                    AND rw.deleted_at IS NULL
                    AND rw.is_active = TRUE
                    LIMIT 1;

                    IF v_old_record IS NULL THEN
                        -- Insert new record
                        INSERT INTO role_widget (
                            role_id,
                            widget_id,
                            tenant_id,
                            settings,
                            is_active,
                            created_at,
                            updated_at
                        )
                        VALUES (
                            p_role_id,
                            v_widget_id,
                            p_tenant_id,
                            p_settings,
                            p_is_active,
                            p_current_time,
                            p_current_time
                        )
                        RETURNING to_jsonb(role_widget.*) INTO v_new_record;

                        RETURN QUERY SELECT 'SUCCESS', 'Role-Widget relation created successfully', p_role_id, v_widget_id;

                        -- Log insert
                        IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
                            BEGIN
                                v_log_data := jsonb_build_object(
                                    'role_id', p_role_id,
                                    'widget_id', v_widget_id,
                                    'new_data', v_new_record
                                );

                                PERFORM log_activity(
                                    'role_widget.created',
                                    'User ' || p_user_name || ' added widget ' || v_widget_id || ' to role ' || p_role_id,
                                    'role_widget',
                                    v_widget_id,
                                    'user',
                                    p_user_id,
                                    v_log_data,
                                    p_tenant_id
                                );
                            EXCEPTION WHEN OTHERS THEN
                                v_error_message := 'Logging failed: ' || SQLERRM;
                            END;
                        END IF;

                    ELSE
                        -- Update existing record
                        UPDATE role_widget
                        SET
                            settings   = p_settings,
                            is_active  = p_is_active,
                            deleted_at = NULL,
                            updated_at = p_current_time
                        WHERE role_id = p_role_id
                        AND widget_id = v_widget_id
                        AND (tenant_id = p_tenant_id OR p_tenant_id IS NULL);

                        SELECT to_jsonb(rw.*) INTO v_new_record
                        FROM role_widget rw
                        WHERE rw.role_id = p_role_id
                        AND rw.widget_id = v_widget_id
                        AND (rw.tenant_id = p_tenant_id OR p_tenant_id IS NULL);

                        RETURN QUERY SELECT 'SUCCESS', 'Role-Widget relation updated successfully', p_role_id, v_widget_id;

                        -- Log update
                        IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
                            BEGIN
                                v_log_data := jsonb_build_object(
                                    'role_id', p_role_id,
                                    'widget_id', v_widget_id,
                                    'old_data', v_old_record,
                                    'new_data', v_new_record
                                );

                                PERFORM log_activity(
                                    'role_widget.updated',
                                    'User ' || p_user_name || ' updated widget ' || v_widget_id || ' for role ' || p_role_id,
                                    'role_widget',
                                    v_widget_id,
                                    'user',
                                    p_user_id,
                                    v_log_data,
                                    p_tenant_id
                                );
                            EXCEPTION WHEN OTHERS THEN
                                v_error_message := 'Logging failed: ' || SQLERRM;
                            END;
                        END IF;
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
        DB::unprepared("DROP FUNCTION IF EXISTS insert_or_update_role_widgets(BIGINT, JSONB, BIGINT, JSONB, BOOLEAN, TIMESTAMPTZ, BIGINT, VARCHAR);");
    }
};