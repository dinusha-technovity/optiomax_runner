<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Create audit staff create/update function
     * 
     * This function handles both creating new audit staff and updating existing ones.
     * Includes validation, duplicate checking, auditor_code generation, and activity logging.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- Drop existing function if exists (with any parameter signature)
        DROP FUNCTION IF EXISTS create_or_update_audit_staff(BIGINT, BIGINT, BIGINT, VARCHAR, TEXT, BIGINT, VARCHAR, TIMESTAMPTZ) CASCADE;
        DROP FUNCTION IF EXISTS create_or_update_audit_staff(BIGINT, BIGINT, BIGINT, TEXT, BIGINT, VARCHAR, TIMESTAMPTZ) CASCADE;

        CREATE OR REPLACE FUNCTION create_or_update_audit_staff(
                p_user_id BIGINT,
                p_tenant_id BIGINT,
                p_staff_id BIGINT DEFAULT NULL,
                p_notes TEXT DEFAULT NULL,
                p_assigned_by BIGINT DEFAULT NULL,
                p_user_name VARCHAR DEFAULT NULL,
                p_current_time TIMESTAMPTZ DEFAULT now()
            ) RETURNS JSONB LANGUAGE plpgsql AS $$
            DECLARE
                v_staff_id BIGINT;
                v_is_update BOOLEAN := FALSE;
                v_old_data JSONB;
                v_new_data JSONB;
                v_user_exists BOOLEAN;
                v_generated_code VARCHAR;
                v_final_auditor_code VARCHAR;
                v_user_name VARCHAR;
            BEGIN
                -- Validate input
                IF p_user_id IS NULL THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'User ID is required'
                    );
                END IF;

                IF p_tenant_id IS NULL THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'Tenant ID is required'
                    );
                END IF;

                -- Get user details
                SELECT name INTO v_user_name
                FROM users
                WHERE id = p_user_id AND tenant_id = p_tenant_id AND deleted_at IS NULL;

                IF v_user_name IS NULL THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'User not found or does not belong to this tenant'
                    );
                END IF;

                -- Check if user is already audit staff (excluding current record if updating)
                SELECT EXISTS(
                    SELECT 1 FROM audit_staff
                    WHERE user_id = p_user_id 
                        AND tenant_id = p_tenant_id 
                        AND deleted_at IS NULL
                        AND (p_staff_id IS NULL OR id != p_staff_id)
                ) INTO v_user_exists;

                IF v_user_exists THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'This user is already registered as audit staff'
                    );
                END IF;

                -- Update existing audit staff
                IF p_staff_id IS NOT NULL THEN
                    v_is_update := TRUE;
                    
                    -- Get old data for logging
                    SELECT jsonb_build_object(
                        'user_id', user_id,
                        'auditor_code', auditor_code,
                        'notes', notes,
                        'assigned_by', assigned_by,
                        'isactive', isactive
                    ) INTO v_old_data
                    FROM audit_staff
                    WHERE id = p_staff_id 
                        AND tenant_id = p_tenant_id 
                        AND deleted_at IS NULL;

                    IF v_old_data IS NULL THEN
                        RETURN jsonb_build_object(
                            'status', 'ERROR',
                            'message', 'Audit staff record not found'
                        );
                    END IF;

                    -- Update the audit staff
                    UPDATE audit_staff
                    SET 
                        notes = p_notes,
                        assigned_by = p_assigned_by,
                        updated_at = p_current_time
                    WHERE id = p_staff_id 
                        AND tenant_id = p_tenant_id
                        AND deleted_at IS NULL;

                    v_staff_id := p_staff_id;

                    -- Get current auditor_code
                    SELECT auditor_code INTO v_final_auditor_code
                    FROM audit_staff
                    WHERE id = p_staff_id;

                    -- Get new data for logging
                    v_new_data := jsonb_build_object(
                        'user_id', p_user_id,
                        'auditor_code', v_final_auditor_code,
                        'notes', p_notes,
                        'assigned_by', p_assigned_by,
                        'isactive', TRUE
                    );

                    -- Log activity
                    BEGIN
                        PERFORM log_activity(
                            'audit_staff.updated',
                            'Audit staff updated for "' || v_user_name || '" (Code: ' || v_final_auditor_code || ')',
                            'audit_staff',
                            v_staff_id,
                            'user',
                            COALESCE(p_assigned_by, p_user_id),
                            jsonb_build_object(
                                'staff_id', v_staff_id,
                                'user_name', v_user_name,
                                'auditor_code', v_final_auditor_code,
                                'old_data', v_old_data,
                                'new_data', v_new_data,
                                'modified_by', p_user_name,
                                'action_time', p_current_time
                            ),
                            p_tenant_id
                        );
                    EXCEPTION WHEN OTHERS THEN
                        RAISE NOTICE 'Log activity failed: %', SQLERRM;
                    END;

                ELSE
                    -- Auto-generate auditor_code using database sequence
                    v_final_auditor_code := 'AUD-' || p_tenant_id || '-' || LPAD(nextval('audit_staff_code_seq')::TEXT, 6, '0');

                    -- Insert new audit staff
                    INSERT INTO audit_staff (
                        user_id,
                        tenant_id,
                        auditor_code,
                        notes,
                        assigned_by,
                        assigned_at,
                        isactive,
                        created_at,
                        updated_at
                    ) VALUES (
                        p_user_id,
                        p_tenant_id,
                        v_final_auditor_code,
                        p_notes,
                        p_assigned_by,
                        p_current_time,
                        TRUE,
                        p_current_time,
                        p_current_time
                    ) RETURNING id INTO v_staff_id;

                    -- Log activity
                    BEGIN
                        PERFORM log_activity(
                            'audit_staff.created',
                            'Audit staff created for "' || v_user_name || '" (Code: ' || v_final_auditor_code || ')',
                            'audit_staff',
                            v_staff_id,
                            'user',
                            COALESCE(p_assigned_by, p_user_id),
                            jsonb_build_object(
                                'staff_id', v_staff_id,
                                'user_name', v_user_name,
                                'user_id', p_user_id,
                                'auditor_code', v_final_auditor_code,
                                'notes', p_notes,
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
                    'message', CASE 
                        WHEN v_is_update THEN 'Audit staff updated successfully'
                        ELSE 'Audit staff created successfully'
                    END,
                    'staff_id', v_staff_id,
                    'auditor_code', v_final_auditor_code,
                    'user_name', v_user_name,
                    'is_update', v_is_update
                );

            EXCEPTION
                WHEN unique_violation THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'This user is already registered as audit staff or auditor code already exists'
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
        DB::unprepared('DROP FUNCTION IF EXISTS create_or_update_audit_staff');
    }
};