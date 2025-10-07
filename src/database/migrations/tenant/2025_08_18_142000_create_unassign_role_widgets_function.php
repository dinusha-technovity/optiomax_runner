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
        CREATE OR REPLACE FUNCTION unassign_role_widgets(
            IN p_role_id BIGINT,
            IN p_widget_ids JSONB, -- JSON array of widget IDs
            IN p_tenant_id BIGINT,
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
            v_error_message TEXT;
        BEGIN
            -- Validate role
            IF p_role_id IS NULL OR p_role_id = 0 THEN
                RETURN QUERY SELECT 'FAILURE', 'Role ID cannot be null or zero', NULL::BIGINT, NULL::BIGINT;
                RETURN;
            END IF;

            -- Loop through JSON array of widget IDs
            FOR v_widget_id IN
                SELECT value::BIGINT FROM jsonb_array_elements_text(p_widget_ids)
            LOOP
                -- Check if record exists and is active
                SELECT to_jsonb(rw.*) INTO v_old_record
                FROM role_widget rw
                WHERE rw.role_id = p_role_id
                AND rw.widget_id = v_widget_id
                AND (rw.tenant_id = p_tenant_id OR p_tenant_id IS NULL)
                AND rw.is_active = true
                AND rw.deleted_at IS NULL
                LIMIT 1;

                IF v_old_record IS NULL THEN
                    RETURN QUERY SELECT 'SKIPPED', 'Widget not currently assigned or already inactive', p_role_id, v_widget_id;
                    CONTINUE;
                END IF;

                -- Perform soft delete
                UPDATE role_widget rw
                SET is_active = false,
                    deleted_at = p_current_time,
                    updated_at = p_current_time
                WHERE rw.role_id = p_role_id
                AND rw.widget_id = v_widget_id
                AND (rw.tenant_id = p_tenant_id OR p_tenant_id IS NULL);

                -- Fetch new record
                SELECT to_jsonb(rw.*) INTO v_new_record
                FROM role_widget rw
                WHERE rw.role_id = p_role_id
                AND rw.widget_id = v_widget_id
                AND (rw.tenant_id = p_tenant_id OR p_tenant_id IS NULL);

                RETURN QUERY SELECT 'SUCCESS', 'Role-Widget relation unassigned (soft deleted)', p_role_id, v_widget_id;

                -- Log unassign
                IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
                    BEGIN
                        v_log_data := jsonb_build_object(
                            'role_id', p_role_id,
                            'widget_id', v_widget_id,
                            'old_data', v_old_record,
                            'new_data', v_new_record,
                            'action', 'unassigned'
                        );

                        PERFORM log_activity(
                            'role_widget.unassigned',
                            'User ' || p_user_name || ' unassigned widget ' || v_widget_id || ' from role ' || p_role_id,
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
        DB::unprepared("DROP FUNCTION IF EXISTS unassign_role_widgets(BIGINT, JSONB, BIGINT, TIMESTAMPTZ, BIGINT, VARCHAR);");
    }
};