<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Create audit group create/update function
     * 
     * This function handles both creating new audit groups and updating existing ones.
     * Includes validation, duplicate name checking, and comprehensive activity logging.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION create_or_update_audit_group(
            p_name VARCHAR,
            p_tenant_id BIGINT,
            p_user_id BIGINT,
            p_group_id BIGINT DEFAULT NULL,
            p_description TEXT DEFAULT NULL,
            p_user_name VARCHAR DEFAULT NULL,
            p_current_time TIMESTAMPTZ DEFAULT now()
        ) RETURNS JSONB LANGUAGE plpgsql AS $$
        DECLARE
            v_group_id BIGINT;
            v_is_update BOOLEAN := FALSE;
            v_old_data JSONB;
            v_new_data JSONB;
            v_name_exists BOOLEAN;
        BEGIN
            -- Validate input
            IF p_name IS NULL OR TRIM(p_name) = '' THEN
                RETURN jsonb_build_object(
                    'status', 'ERROR',
                    'message', 'Audit group name is required'
                );
            END IF;

            IF p_tenant_id IS NULL THEN
                RETURN jsonb_build_object(
                    'status', 'ERROR',
                    'message', 'Tenant ID is required'
                );
            END IF;

            -- Check for duplicate name (excluding current group if updating)
            SELECT EXISTS(
                SELECT 1 FROM audit_groups 
                WHERE name = p_name 
                    AND tenant_id = p_tenant_id 
                    AND deleted_at IS NULL
                    AND (p_group_id IS NULL OR id != p_group_id)
            ) INTO v_name_exists;

            IF v_name_exists THEN
                RETURN jsonb_build_object(
                    'status', 'ERROR',
                    'message', 'An audit group with this name already exists'
                );
            END IF;

            -- Update existing group
            IF p_group_id IS NOT NULL THEN
                v_is_update := TRUE;
                
                -- Get old data for logging
                SELECT jsonb_build_object(
                    'name', name,
                    'description', description,
                    'isactive', isactive
                ) INTO v_old_data
                FROM audit_groups
                WHERE id = p_group_id 
                    AND tenant_id = p_tenant_id 
                    AND deleted_at IS NULL;

                IF v_old_data IS NULL THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'Audit group not found'
                    );
                END IF;

                -- Update the group
                UPDATE audit_groups
                SET 
                    name = p_name,
                    description = p_description,
                    updated_at = p_current_time
                WHERE id = p_group_id 
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;

                v_group_id := p_group_id;

                -- Get new data for logging
                v_new_data := jsonb_build_object(
                    'name', p_name,
                    'description', p_description,
                    'isactive', TRUE
                );

                -- Log activity
                BEGIN
                    PERFORM log_activity(
                        'audit_group.updated',
                        'Audit group "' || p_name || '" updated',
                        'audit_groups',
                        v_group_id,
                        'user',
                        p_user_id,
                        jsonb_build_object(
                            'group_name', p_name,
                            'old_data', v_old_data,
                            'new_data', v_new_data,
                            'user_name', p_user_name,
                            'action_time', p_current_time
                        ),
                        p_tenant_id
                    );
                EXCEPTION WHEN OTHERS THEN
                    RAISE NOTICE 'Log activity failed: %', SQLERRM;
                END;

            ELSE
                -- Insert new group
                INSERT INTO audit_groups (
                    name,
                    description,
                    tenant_id,
                    isactive,
                    created_at,
                    updated_at
                ) VALUES (
                    p_name,
                    p_description,
                    p_tenant_id,
                    TRUE,
                    p_current_time,
                    p_current_time
                ) RETURNING id INTO v_group_id;

                -- Log activity
                BEGIN
                    PERFORM log_activity(
                        'audit_group.created',
                        'Audit group "' || p_name || '" created',
                        'audit_groups',
                        v_group_id,
                        'user',
                        p_user_id,
                        jsonb_build_object(
                            'group_name', p_name,
                            'description', p_description,
                            'user_name', p_user_name,
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
                'message', CASE 
                    WHEN v_is_update THEN 'Audit group updated successfully'
                    ELSE 'Audit group created successfully'
                END,
                'group_id', v_group_id,
                'group_name', p_name,
                'is_update', v_is_update
            );

        EXCEPTION
            WHEN unique_violation THEN
                RETURN jsonb_build_object(
                    'status', 'ERROR',
                    'message', 'An audit group with this name already exists'
                );
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
        DB::unprepared('DROP FUNCTION IF EXISTS create_or_update_audit_group');
    }
};
