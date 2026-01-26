<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
                WHERE proname = 'create_or_update_employee'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION create_or_update_employee(
            IN p_employee_id BIGINT,
            IN p_employee_name VARCHAR(255),
            IN p_employee_number VARCHAR(255),
            IN p_email VARCHAR(255),
            IN p_department_id BIGINT,
            IN p_designation_id BIGINT,
            IN p_phone_number VARCHAR(50),
            IN p_contact_no_code BIGINT,
            IN p_address TEXT,
            IN p_user_id BIGINT,
            IN p_tenant_id BIGINT,
            IN p_current_time TIMESTAMPTZ,
            IN p_causer_id BIGINT DEFAULT NULL,
            IN p_causer_name TEXT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            employee_id BIGINT,
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
            v_is_current_user BOOLEAN := FALSE;
        BEGIN
            -- Validate required fields
            IF p_employee_name IS NULL OR p_employee_name = '' THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Employee name is required'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;

            IF p_employee_number IS NULL OR p_employee_number = '' THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Employee number is required'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;

            IF p_email IS NULL OR p_email = '' THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Email is required'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;

            IF p_tenant_id IS NULL OR p_tenant_id = 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Tenant ID cannot be null or zero'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;

            -- Check if this is an insert or update
            IF p_employee_id IS NULL OR p_employee_id = 0 THEN
                -- INSERT operation

                -- Check if employee number already exists
                IF EXISTS (
                    SELECT 1 FROM users
                    WHERE employee_number = p_employee_number
                    AND tenant_id = p_tenant_id
                    AND employee_account_enabled = TRUE
                ) THEN
                    RETURN QUERY SELECT 'FAILURE'::TEXT, 'Employee number already exists'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                -- If user_id is provided, update existing user with employee fields
                IF p_user_id IS NOT NULL THEN
                    -- Check if user exists
                    IF NOT EXISTS (
                        SELECT 1 FROM users
                        WHERE id = p_user_id
                        AND tenant_id = p_tenant_id
                    ) THEN
                        RETURN QUERY SELECT 'FAILURE'::TEXT, 'User not found'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                        RETURN;
                    END IF;

                    -- Check if user is already an employee
                    IF EXISTS (
                        SELECT 1 FROM users
                        WHERE id = p_user_id
                        AND tenant_id = p_tenant_id
                        AND employee_account_enabled = TRUE
                    ) THEN
                        RETURN QUERY SELECT 'FAILURE'::TEXT, 'User is already an employee'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                        RETURN;
                    END IF;

                    -- Check if this user is the current user (causer)
                    v_is_current_user := (p_user_id = p_causer_id);

                    -- Get old record before update
                    SELECT to_jsonb(u) INTO old_record
                    FROM users u
                    WHERE id = p_user_id;

                    -- Update existing user with employee fields
                    UPDATE users
                    SET
                        name = p_employee_name,
                        employee_number = p_employee_number,
                        email = p_email,
                        contact_no = p_phone_number,
                        contact_no_code = p_contact_no_code,
                        address = p_address,
                        organization = p_department_id,
                        designation_id = p_designation_id,
                        employee_account_enabled = TRUE,
                        user_account_enabled = TRUE,
                        updated_at = p_current_time
                    WHERE id = p_user_id
                    RETURNING id INTO v_user_id;

                    -- Get updated record
                    SELECT to_jsonb(u) INTO new_record
                    FROM users u
                    WHERE id = v_user_id;

                    -- Log the update activity
                    BEGIN
                        PERFORM log_activity(
                            'enable_employee_account',
                            format('User %s enabled employee account for user: %s', p_causer_name, p_employee_name),
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

                    RETURN QUERY SELECT 'SUCCESS'::TEXT, 'Employee account enabled successfully'::TEXT, v_user_id, old_record, new_record;

                ELSE
                    -- Create new user with employee fields
                    -- Check if email already exists (only for new user creation)
                    IF EXISTS (
                        SELECT 1 FROM users
                        WHERE email = p_email
                        AND tenant_id = p_tenant_id
                    ) THEN
                        RETURN QUERY SELECT 'FAILURE'::TEXT, 'Email already exists'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                        RETURN;
                    END IF;

                    INSERT INTO users (
                        user_name,
                        name,
                        employee_number,
                        email,
                        contact_no,
                        contact_no_code,
                        address,
                        organization,
                        designation_id,
                        tenant_id,
                        user_account_enabled,
                        employee_account_enabled,
                        password,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        p_email, -- user_name defaults to email
                        p_employee_name,
                        p_employee_number,
                        p_email,
                        p_phone_number,
                        p_contact_no_code,
                        p_address,
                        p_department_id,
                        p_designation_id,
                        p_tenant_id,
                        FALSE, -- user_account_enabled (FALSE for new employees who are not current user)
                        TRUE, -- employee_account_enabled
                        '', -- empty password (will be set later)
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
                            'create_employee_user',
                            format('User %s created employee user: %s', p_causer_name, p_employee_name),
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

                    RETURN QUERY SELECT 'SUCCESS'::TEXT, 'Employee user created successfully'::TEXT, v_user_id, NULL::JSONB, new_record;
                END IF;            ELSE
                -- UPDATE operation

                -- Check if user exists and has employee account enabled
                IF NOT EXISTS (
                    SELECT 1 FROM users
                    WHERE id = p_employee_id
                    AND tenant_id = p_tenant_id
                    AND employee_account_enabled = TRUE
                ) THEN
                    RETURN QUERY SELECT 'FAILURE'::TEXT, 'Employee not found'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                -- Check if employee number already exists for another user
                IF EXISTS (
                    SELECT 1 FROM users
                    WHERE employee_number = p_employee_number
                    AND tenant_id = p_tenant_id
                    AND employee_account_enabled = TRUE
                    AND id != p_employee_id
                ) THEN
                    RETURN QUERY SELECT 'FAILURE'::TEXT, 'Employee number already exists'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                -- Check if email already exists for another user
                IF EXISTS (
                    SELECT 1 FROM users
                    WHERE email = p_email
                    AND tenant_id = p_tenant_id
                    AND id != p_employee_id
                ) THEN
                    RETURN QUERY SELECT 'FAILURE'::TEXT, 'Email already exists'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                -- Check if this user is the current user (causer)
                v_is_current_user := (p_employee_id = p_causer_id);

                -- Get old record before update
                SELECT to_jsonb(u) INTO old_record
                FROM users u
                WHERE id = p_employee_id;

                -- Update the user
                UPDATE users
                SET
                    name = p_employee_name,
                    employee_number = p_employee_number,
                    email = p_email,
                    contact_no = p_phone_number,
                    address = p_address,
                    organization = p_department_id,
                    designation_id = p_designation_id,
                    user_account_enabled = CASE WHEN v_is_current_user THEN TRUE ELSE user_account_enabled END,
                    employee_account_enabled = TRUE,
                    updated_at = p_current_time
                WHERE id = p_employee_id;

                -- Get updated record
                SELECT to_jsonb(u) INTO new_record
                FROM users u
                WHERE id = p_employee_id;

                -- Log the update activity
                BEGIN
                    PERFORM log_activity(
                        'update_employee',
                        format('User %s updated employee: %s', p_causer_name, p_employee_name),
                        'users',
                        p_employee_id,
                        'user',
                        p_causer_id,
                        new_record,
                        p_tenant_id
                    );
                    log_success := TRUE;
                EXCEPTION WHEN OTHERS THEN
                    log_success := FALSE;
                END;

                RETURN QUERY SELECT 'SUCCESS'::TEXT, 'Employee updated successfully'::TEXT, p_employee_id, old_record, new_record;
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
        DB::unprepared('DROP FUNCTION IF EXISTS create_or_update_employee');
    }
};
