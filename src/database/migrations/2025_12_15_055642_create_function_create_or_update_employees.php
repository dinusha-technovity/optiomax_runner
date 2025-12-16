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
            v_employee_id BIGINT;
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
                    SELECT 1 FROM employees 
                    WHERE employee_number = p_employee_number 
                    AND tenant_id = p_tenant_id 
                    AND deleted_at IS NULL
                ) THEN
                    RETURN QUERY SELECT 'FAILURE'::TEXT, 'Employee number already exists'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                -- Check if email already exists
                IF EXISTS (
                    SELECT 1 FROM employees 
                    WHERE email = p_email 
                    AND tenant_id = p_tenant_id 
                    AND deleted_at IS NULL
                ) THEN
                    RETURN QUERY SELECT 'FAILURE'::TEXT, 'Email already exists'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                INSERT INTO employees (
                    employee_name,
                    employee_number,
                    email,
                    department,
                    designation_id,
                    phone_number,
                    contact_no_code,
                    address,
                    user_id,
                    tenant_id,
                    created_at,
                    updated_at
                )
                VALUES (
                    p_employee_name,
                    p_employee_number,
                    p_email,
                    p_department_id,
                    p_designation_id,
                    p_phone_number,
                    p_contact_no_code,
                    p_address,
                    p_user_id,
                    p_tenant_id,
                    p_current_time,
                    p_current_time
                )
                RETURNING id INTO v_employee_id;

                -- Get the inserted record
                SELECT to_jsonb(e) INTO new_record
                FROM employees e
                WHERE id = v_employee_id;

                -- Log the insert activity
                BEGIN
                    PERFORM log_activity(
                        'insert_employee',
                        format('User %s created employee: %s', p_causer_name, p_employee_name),
                        'employees',
                        v_employee_id,
                        'user',
                        p_causer_id,
                        new_record,
                        p_tenant_id
                    );
                    log_success := TRUE;
                EXCEPTION WHEN OTHERS THEN
                    log_success := FALSE;
                END;

                RETURN QUERY SELECT 'SUCCESS'::TEXT, 'Employee created successfully'::TEXT, v_employee_id, NULL::JSONB, new_record;
                
            ELSE
                -- UPDATE operation
                
                -- Check if employee exists
                IF NOT EXISTS (
                    SELECT 1 FROM employees 
                    WHERE id = p_employee_id 
                    AND tenant_id = p_tenant_id 
                    AND deleted_at IS NULL
                ) THEN
                    RETURN QUERY SELECT 'FAILURE'::TEXT, 'Employee not found'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                -- Check if employee number already exists for another employee
                IF EXISTS (
                    SELECT 1 FROM employees 
                    WHERE employee_number = p_employee_number 
                    AND tenant_id = p_tenant_id 
                    AND id != p_employee_id
                    AND deleted_at IS NULL
                ) THEN
                    RETURN QUERY SELECT 'FAILURE'::TEXT, 'Employee number already exists'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                -- Check if email already exists for another employee
                IF EXISTS (
                    SELECT 1 FROM employees 
                    WHERE email = p_email 
                    AND tenant_id = p_tenant_id 
                    AND id != p_employee_id
                    AND deleted_at IS NULL
                ) THEN
                    RETURN QUERY SELECT 'FAILURE'::TEXT, 'Email already exists'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                -- Get old record before update
                SELECT to_jsonb(e) INTO old_record
                FROM employees e
                WHERE id = p_employee_id;

                -- Update the employee
                UPDATE employees
                SET
                    employee_name = p_employee_name,
                    employee_number = p_employee_number,
                    email = p_email,
                    department = p_department_id,
                    designation_id = p_designation_id,
                    phone_number = p_phone_number,
                    contact_no_code = p_contact_no_code,
                    address = p_address,
                    user_id = p_user_id,
                    updated_at = p_current_time
                WHERE id = p_employee_id;

                -- Get updated record
                SELECT to_jsonb(e) INTO new_record
                FROM employees e
                WHERE id = p_employee_id;

                -- Log the update activity
                BEGIN
                    PERFORM log_activity(
                        'update_employee',
                        format('User %s updated employee: %s', p_causer_name, p_employee_name),
                        'employees',
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
