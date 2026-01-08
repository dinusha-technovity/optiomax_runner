<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
                WHERE proname = 'insert_or_update_asset_audit_variable'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION insert_or_update_asset_audit_variable(
            IN p_audit_variable_id BIGINT,
            IN p_asset_audit_variable_type_id BIGINT,
            IN p_name VARCHAR(255),
            IN p_description TEXT,
            IN p_tenant_id BIGINT,
            IN p_is_active BOOLEAN,
            IN p_current_time TIMESTAMPTZ,
            IN p_causer_id BIGINT DEFAULT NULL,
            IN p_causer_name TEXT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            audit_variable_id BIGINT,
            old_data JSONB,
            new_data JSONB
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            old_record JSONB;
            new_record JSONB;
            log_success BOOLEAN;
            v_audit_variable_id BIGINT;
        BEGIN
            -- Validate required fields
            IF p_name IS NULL OR p_name = '' THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Variable name is required'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;

            IF p_asset_audit_variable_type_id IS NULL OR p_asset_audit_variable_type_id = 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Asset audit variable type ID is required'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;

            IF p_tenant_id IS NULL OR p_tenant_id = 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Tenant ID cannot be null or zero'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;

            -- Validate that the asset_audit_variable_type exists (either tenant-specific or global)
            IF NOT EXISTS (
                SELECT 1 FROM asset_audit_variable_type 
                WHERE id = p_asset_audit_variable_type_id 
                AND deleted_at IS NULL
                AND is_active = TRUE
            ) THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Invalid asset audit variable type ID'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;

            -- Check if this is an insert or update
            IF p_audit_variable_id IS NULL OR p_audit_variable_id = 0 THEN
                -- INSERT operation
                
                -- Check if variable name already exists for the tenant
                IF EXISTS (
                    SELECT 1 FROM asset_audit_variable 
                    WHERE name = p_name 
                    AND tenant_id = p_tenant_id 
                    AND deleted_at IS NULL
                ) THEN
                    RETURN QUERY SELECT 'FAILURE'::TEXT, 'Variable name already exists'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                INSERT INTO asset_audit_variable (
                    asset_audit_variable_type_id,
                    name,
                    description,
                    tenant_id,
                    is_active,
                    created_at,
                    updated_at
                )
                VALUES (
                    p_asset_audit_variable_type_id,
                    p_name,
                    p_description,
                    p_tenant_id,
                    COALESCE(p_is_active, TRUE),
                    p_current_time,
                    p_current_time
                )
                RETURNING id INTO v_audit_variable_id;

                -- Get the inserted record
                SELECT to_jsonb(a) INTO new_record
                FROM asset_audit_variable a
                WHERE id = v_audit_variable_id;

                -- Log the insert activity
                BEGIN
                    PERFORM log_activity(
                        'insert_asset_audit_variable',
                        format('User %s created asset audit variable: %s', p_causer_name, p_name),
                        'asset_audit_variable',
                        v_audit_variable_id,
                        'user',
                        p_causer_id,
                        new_record,
                        p_tenant_id
                    );
                    log_success := TRUE;
                EXCEPTION WHEN OTHERS THEN
                    log_success := FALSE;
                END;

                RETURN QUERY SELECT 'SUCCESS'::TEXT, 'Asset audit variable created successfully'::TEXT, v_audit_variable_id, NULL::JSONB, new_record;
                
            ELSE
                -- UPDATE operation
                
                -- Check if asset audit variable exists
                IF NOT EXISTS (
                    SELECT 1 FROM asset_audit_variable 
                    WHERE id = p_audit_variable_id 
                    AND tenant_id = p_tenant_id 
                    AND deleted_at IS NULL
                ) THEN
                    RETURN QUERY SELECT 'FAILURE'::TEXT, 'Asset audit variable not found'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                -- Check if variable name already exists for another record
                IF EXISTS (
                    SELECT 1 FROM asset_audit_variable 
                    WHERE name = p_name 
                    AND tenant_id = p_tenant_id 
                    AND id != p_audit_variable_id
                    AND deleted_at IS NULL
                ) THEN
                    RETURN QUERY SELECT 'FAILURE'::TEXT, 'Variable name already exists'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                -- Get old record before update
                SELECT to_jsonb(a) INTO old_record
                FROM asset_audit_variable a
                WHERE id = p_audit_variable_id;

                -- Update the asset audit variable
                UPDATE asset_audit_variable
                SET
                    asset_audit_variable_type_id = p_asset_audit_variable_type_id,
                    name = p_name,
                    description = p_description,
                    is_active = COALESCE(p_is_active, is_active),
                    updated_at = p_current_time
                WHERE id = p_audit_variable_id;

                -- Get updated record
                SELECT to_jsonb(a) INTO new_record
                FROM asset_audit_variable a
                WHERE id = p_audit_variable_id;

                -- Log the update activity
                BEGIN
                    PERFORM log_activity(
                        'update_asset_audit_variable',
                        format('User %s updated asset audit variable: %s', p_causer_name, p_name),
                        'asset_audit_variable',
                        p_audit_variable_id,
                        'user',
                        p_causer_id,
                        new_record,
                        p_tenant_id
                    );
                    log_success := TRUE;
                EXCEPTION WHEN OTHERS THEN
                    log_success := FALSE;
                END;

                RETURN QUERY SELECT 'SUCCESS'::TEXT, 'Asset audit variable updated successfully'::TEXT, p_audit_variable_id, old_record, new_record;
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
        DB::unprepared(<<<SQL
        DO $$
        DECLARE
            r RECORD;
        BEGIN
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'insert_or_update_asset_audit_variable'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;
        SQL);
    }
};