<?php

use Illuminate\Database\Migrations\Migration;
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
                WHERE proname = 'create_or_update_user'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION create_or_update_user(
            IN p_user_id BIGINT,
            IN p_user_name VARCHAR(255),
            IN p_email VARCHAR(255),
            IN p_name VARCHAR(255),
            IN p_contact_no VARCHAR(50),
            IN p_contact_no_code BIGINT,
            IN p_user_description TEXT,
            IN p_profile_image TEXT,
            IN p_designation_id BIGINT,
            IN p_organization_id BIGINT,
            IN p_password VARCHAR(255),
            IN p_tenant_id BIGINT,
            IN p_is_system_user BOOLEAN,
            IN p_employee_number VARCHAR(255),
            IN p_current_time TIMESTAMPTZ,
            IN p_causer_id BIGINT DEFAULT NULL,
            IN p_causer_name TEXT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            user_id BIGINT,
            old_data JSONB,
            new_data JSONB
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            old_record JSONB;
            new_record JSONB;
            log_success BOOLEAN;
            v_user_id BIGINT;
        BEGIN
            -- Validate required fields
            IF p_user_name IS NULL OR p_user_name = '' THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'User name is required'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;

            IF p_email IS NULL OR p_email = '' THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Email is required'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;

            IF p_name IS NULL OR p_name = '' THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Name is required'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;

            IF p_tenant_id IS NULL OR p_tenant_id = 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Tenant ID cannot be null or zero'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;

            -- If not system user, employee number is required
            IF p_is_system_user = FALSE AND (p_employee_number IS NULL OR p_employee_number = '') THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Employee number is required for non-system users'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;

            -- Check if this is an insert or update
            IF p_user_id IS NULL OR p_user_id = 0 THEN
                -- INSERT operation

                -- Check if email already exists
                IF EXISTS (
                    SELECT 1 FROM users
                    WHERE email = p_email
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL
                ) THEN
                    RETURN QUERY SELECT 'FAILURE'::TEXT, 'Email already exists'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                -- Check if employee number already exists (for non-system users)
                IF p_is_system_user = FALSE THEN
                    IF EXISTS (
                        SELECT 1 FROM users
                        WHERE employee_number = p_employee_number
                        AND tenant_id = p_tenant_id
                        AND employee_account_enabled = TRUE
                        AND deleted_at IS NULL
                    ) THEN
                        RETURN QUERY SELECT 'FAILURE'::TEXT, 'Employee number already exists'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                        RETURN;
                    END IF;
                END IF;

                -- Create new user
                INSERT INTO users (
                    user_name,
                    name,
                    email,
                    contact_no,
                    contact_no_code,
                    address,
                    organization,
                    designation_id,
                    profile_image,
                    user_description,
                    tenant_id,
                    user_account_enabled,
                    employee_account_enabled,
                    employee_number,
                    password,
                    created_at,
                    updated_at
                )
                VALUES (
                    p_user_name,
                    p_name,
                    p_email,
                    p_contact_no,
                    p_contact_no_code,
                    NULL, -- address removed as per requirement
                    p_organization_id,
                    p_designation_id,
                    p_profile_image,
                    p_user_description,
                    p_tenant_id,
                    p_is_system_user, -- user_account_enabled based on toggle
                    TRUE, -- employee_account_enabled - always true
                    p_employee_number,
                    p_password,
                    p_current_time,
                    p_current_time
                )
                RETURNING id INTO v_user_id;

                -- Get the inserted record
                SELECT to_jsonb(u) INTO new_record
                FROM users u
                WHERE id = v_user_id;

                -- Log the insert activity
                BEGIN
                    PERFORM log_activity(
                        CASE WHEN p_is_system_user THEN 'create_system_user' ELSE 'create_employee_user' END,
                        format('User %s created %s: %s', 
                            p_causer_name, 
                            CASE WHEN p_is_system_user THEN 'system user' ELSE 'employee user' END,
                            p_name
                        ),
                        'users',
                        v_user_id,
                        'user',
                        p_causer_id,
                        new_record,
                        p_tenant_id
                    );
                    log_success := TRUE;
                EXCEPTION WHEN OTHERS THEN
                    log_success := FALSE;
                END;

                RETURN QUERY SELECT 
                    'SUCCESS'::TEXT, 
                    CASE WHEN p_is_system_user THEN 'System user created successfully' ELSE 'Employee user created successfully' END::TEXT, 
                    v_user_id, 
                    NULL::JSONB, 
                    new_record;

            ELSE
                -- UPDATE operation

                -- Check if user exists
                IF NOT EXISTS (
                    SELECT 1 FROM users
                    WHERE id = p_user_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL
                ) THEN
                    RETURN QUERY SELECT 'FAILURE'::TEXT, 'User not found'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                -- Check if email already exists for another user
                IF EXISTS (
                    SELECT 1 FROM users
                    WHERE email = p_email
                    AND tenant_id = p_tenant_id
                    AND id != p_user_id
                    AND deleted_at IS NULL
                ) THEN
                    RETURN QUERY SELECT 'FAILURE'::TEXT, 'Email already exists'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                -- Check if employee number already exists for another user (for non-system users)
                IF p_is_system_user = FALSE THEN
                    IF EXISTS (
                        SELECT 1 FROM users
                        WHERE employee_number = p_employee_number
                        AND tenant_id = p_tenant_id
                        AND employee_account_enabled = TRUE
                        AND id != p_user_id
                        AND deleted_at IS NULL
                    ) THEN
                        RETURN QUERY SELECT 'FAILURE'::TEXT, 'Employee number already exists'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                        RETURN;
                    END IF;
                END IF;

                -- Get old record before update
                SELECT to_jsonb(u) INTO old_record
                FROM users u
                WHERE id = p_user_id;

                -- Update the user
                UPDATE users
                SET
                    user_name = p_user_name,
                    name = p_name,
                    email = p_email,
                    contact_no = p_contact_no,
                    contact_no_code = p_contact_no_code,
                    user_description = p_user_description,
                    profile_image = p_profile_image,
                    organization = p_organization_id,
                    designation_id = p_designation_id,
                    user_account_enabled = p_is_system_user,
                    employee_account_enabled = TRUE, -- Always true
                    employee_number = p_employee_number,
                    updated_at = p_current_time
                WHERE id = p_user_id;

                -- Get updated record
                SELECT to_jsonb(u) INTO new_record
                FROM users u
                WHERE id = p_user_id;

                -- Log the update activity
                BEGIN
                    PERFORM log_activity(
                        'update_user',
                        format('User %s updated user: %s', p_causer_name, p_name),
                        'users',
                        p_user_id,
                        'user',
                        p_causer_id,
                        new_record,
                        p_tenant_id
                    );
                    log_success := TRUE;
                EXCEPTION WHEN OTHERS THEN
                    log_success := FALSE;
                END;

                RETURN QUERY SELECT 'SUCCESS'::TEXT, 'User updated successfully'::TEXT, p_user_id, old_record, new_record;
            END IF;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS create_or_update_user');
    }
};
