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
        DB::unprepared(<<<'SQL'

        DO $$
        DECLARE
            r RECORD;
        BEGIN
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'insert_or_update_asset_audit_variable_assignment'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION insert_or_update_asset_audit_variable_assignment(
            IN p_assignment_id BIGINT,
            IN p_asset_audit_variable_id BIGINT,
            IN p_assignable_type_id BIGINT,
            IN p_assignable_id BIGINT,
            IN p_tenant_id BIGINT,
            IN p_current_time TIMESTAMPTZ,
            IN p_is_active BOOLEAN DEFAULT TRUE,
            IN p_assigned_by BIGINT DEFAULT NULL,
            IN p_causer_id BIGINT DEFAULT NULL,
            IN p_causer_name TEXT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            assignment_id BIGINT,
            old_data JSONB,
            new_data JSONB
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            old_record JSONB;
            new_record JSONB;
            log_success BOOLEAN;
            v_assignment_id BIGINT;
            v_asset_id BIGINT;
            v_parent_asset_exists BOOLEAN := FALSE;
            v_child_items_exist BOOLEAN := FALSE;
            v_table_name TEXT;
            v_asset_type_id BIGINT := NULL;
            v_assetitem_type_id BIGINT := NULL;
        BEGIN
            -- Get assignable type IDs for Asset and AssetItem
            SELECT id INTO v_asset_type_id 
            FROM assignable_types 
            WHERE name = 'Asset' AND is_active = TRUE;

            SELECT id INTO v_assetitem_type_id 
            FROM assignable_types 
            WHERE name = 'AssetItem' AND is_active = TRUE;

            -- Validate required fields
            IF p_asset_audit_variable_id IS NULL OR p_asset_audit_variable_id = 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Asset audit variable ID is required'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;

            IF p_assignable_type_id IS NULL OR p_assignable_type_id = 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Assignable type ID is required'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;

            IF p_assignable_id IS NULL OR p_assignable_id = 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Assignable ID is required'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;

            IF p_tenant_id IS NULL OR p_tenant_id = 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Tenant ID cannot be null or zero'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;

            -- Validate assignable_type_id exists
            SELECT table_name INTO v_table_name
            FROM assignable_types
            WHERE id = p_assignable_type_id AND is_active = TRUE;

            IF v_table_name IS NULL THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Invalid assignable type ID'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;

            -- Validate that the asset_audit_variable exists and belongs to tenant (or is global)
            IF NOT EXISTS (
                SELECT 1 FROM asset_audit_variable 
                WHERE id = p_asset_audit_variable_id 
                AND (tenant_id = p_tenant_id OR tenant_id IS NULL)
                AND deleted_at IS NULL
                AND is_active = TRUE
            ) THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Asset audit variable not found or not accessible'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;

            -- Validate that the assignable entity exists
            IF p_assignable_type_id = v_asset_type_id THEN
                IF NOT EXISTS (
                    SELECT 1 FROM assets 
                    WHERE id = p_assignable_id 
                    AND tenant_id = p_tenant_id 
                    AND deleted_at IS NULL
                ) THEN
                    RETURN QUERY SELECT 'FAILURE'::TEXT, 'Asset not found'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;
            ELSIF p_assignable_type_id = v_assetitem_type_id THEN
                IF NOT EXISTS (
                    SELECT 1 FROM asset_items 
                    WHERE id = p_assignable_id 
                    AND tenant_id = p_tenant_id 
                    AND deleted_at IS NULL
                ) THEN
                    RETURN QUERY SELECT 'FAILURE'::TEXT, 'Asset item not found'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;
            END IF;

            -- Check if this is an insert or update
            IF p_assignment_id IS NULL OR p_assignment_id = 0 THEN
                -- INSERT operation
                
                -- Check if assignment already exists (including soft-deleted)
                SELECT id INTO v_assignment_id
                FROM asset_audit_variable_assignments 
                WHERE asset_audit_variable_id = p_asset_audit_variable_id
                AND assignable_type_id = p_assignable_type_id
                AND assignable_id = p_assignable_id
                AND tenant_id = p_tenant_id;
                
                -- If record exists and is not deleted, return error
                IF v_assignment_id IS NOT NULL THEN
                    IF EXISTS (
                        SELECT 1 FROM asset_audit_variable_assignments 
                        WHERE id = v_assignment_id
                        AND deleted_at IS NULL
                    ) THEN
                        RETURN QUERY SELECT 'FAILURE'::TEXT, 'This audit variable is already assigned to this entity'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                        RETURN;
                    END IF;
                    
                    -- Record exists but is soft-deleted, restore it
                    UPDATE asset_audit_variable_assignments
                    SET
                        deleted_at = NULL,
                        is_active = p_is_active,
                        assigned_by = p_assigned_by,
                        assigned_at = p_current_time,
                        updated_at = p_current_time
                    WHERE id = v_assignment_id;
                    
                    -- Get the restored record
                    SELECT to_jsonb(a) INTO new_record
                    FROM asset_audit_variable_assignments a
                    WHERE id = v_assignment_id;
                    
                    -- Log the restore activity
                    BEGIN
                        PERFORM log_activity(
                            'restore_asset_audit_variable_assignment',
                            format('User %s restored audit variable ID %s assignment to type ID %s entity ID %s', 
                                p_causer_name, p_asset_audit_variable_id, p_assignable_type_id, p_assignable_id),
                            'asset_audit_variable_assignments',
                            v_assignment_id,
                            'user',
                            p_causer_id,
                            new_record,
                            p_tenant_id
                        );
                        log_success := TRUE;
                    EXCEPTION WHEN OTHERS THEN
                        log_success := FALSE;
                    END;
                    
                    RETURN QUERY SELECT 'SUCCESS'::TEXT, 'Audit variable assignment restored successfully'::TEXT, v_assignment_id, NULL::JSONB, new_record;
                    RETURN;
                END IF;

                -- Business Rule Check: If assigning to AssetItem, check if parent Asset already has this variable
                IF p_assignable_type_id = v_assetitem_type_id THEN
                    -- Get the parent asset_id for this asset_item
                    SELECT asset_id INTO v_asset_id
                    FROM asset_items
                    WHERE id = p_assignable_id;

                    -- Check if the parent asset has this variable assigned
                    SELECT EXISTS (
                        SELECT 1 FROM asset_audit_variable_assignments
                        WHERE asset_audit_variable_id = p_asset_audit_variable_id
                        AND assignable_type_id = v_asset_type_id
                        AND assignable_id = v_asset_id
                        AND tenant_id = p_tenant_id
                        AND deleted_at IS NULL
                        AND is_active = TRUE
                    ) INTO v_parent_asset_exists;

                    IF v_parent_asset_exists THEN
                        RETURN QUERY SELECT 'FAILURE'::TEXT, 'This audit variable is already assigned to the parent asset. Cannot assign to individual items.'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                        RETURN;
                    END IF;
                END IF;

                -- Business Rule Check: If assigning to Asset, check if any child items already have this variable
                IF p_assignable_type_id = v_asset_type_id THEN
                    SELECT EXISTS (
                        SELECT 1 FROM asset_audit_variable_assignments aava
                        INNER JOIN asset_items ai ON ai.id = aava.assignable_id
                        WHERE aava.asset_audit_variable_id = p_asset_audit_variable_id
                        AND aava.assignable_type_id = v_assetitem_type_id
                        AND ai.asset_id = p_assignable_id
                        AND aava.tenant_id = p_tenant_id
                        AND aava.deleted_at IS NULL
                        AND aava.is_active = TRUE
                    ) INTO v_child_items_exist;

                    IF v_child_items_exist THEN
                        RETURN QUERY SELECT 'FAILURE'::TEXT, 'This audit variable is already assigned to one or more child items. Cannot assign to parent asset.'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                        RETURN;
                    END IF;
                END IF;

                -- Insert the assignment
                INSERT INTO asset_audit_variable_assignments (
                    asset_audit_variable_id,
                    assignable_type_id,
                    assignable_id,
                    assigned_by,
                    assigned_at,
                    tenant_id,
                    is_active,
                    created_at,
                    updated_at
                )
                VALUES (
                    p_asset_audit_variable_id,
                    p_assignable_type_id,
                    p_assignable_id,
                    p_assigned_by,
                    p_current_time,
                    p_tenant_id,
                    p_is_active,
                    p_current_time,
                    p_current_time
                )
                RETURNING id INTO v_assignment_id;

                -- Get the inserted record
                SELECT to_jsonb(a) INTO new_record
                FROM asset_audit_variable_assignments a
                WHERE id = v_assignment_id;

                -- Log the insert activity
                BEGIN
                    PERFORM log_activity(
                        'insert_asset_audit_variable_assignment',
                        format('User %s assigned audit variable ID %s to type ID %s entity ID %s', 
                            p_causer_name, p_asset_audit_variable_id, p_assignable_type_id, p_assignable_id),
                        'asset_audit_variable_assignments',
                        v_assignment_id,
                        'user',
                        p_causer_id,
                        new_record,
                        p_tenant_id
                    );
                    log_success := TRUE;
                EXCEPTION WHEN OTHERS THEN
                    log_success := FALSE;
                END;

                RETURN QUERY SELECT 'SUCCESS'::TEXT, 'Audit variable assigned successfully'::TEXT, v_assignment_id, NULL::JSONB, new_record;
                
            ELSE
                -- UPDATE operation
                
                -- Check if assignment exists
                IF NOT EXISTS (
                    SELECT 1 FROM asset_audit_variable_assignments 
                    WHERE id = p_assignment_id 
                    AND tenant_id = p_tenant_id 
                    AND deleted_at IS NULL
                ) THEN
                    RETURN QUERY SELECT 'FAILURE'::TEXT, 'Assignment not found'::TEXT, NULL::BIGINT, NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                -- Get old record before update
                SELECT to_jsonb(a) INTO old_record
                FROM asset_audit_variable_assignments a
                WHERE id = p_assignment_id;

                -- Update the assignment
                UPDATE asset_audit_variable_assignments
                SET
                    is_active = p_is_active,
                    updated_at = p_current_time
                WHERE id = p_assignment_id;

                -- Get updated record
                SELECT to_jsonb(a) INTO new_record
                FROM asset_audit_variable_assignments a
                WHERE id = p_assignment_id;

                -- Log the update activity
                BEGIN
                    PERFORM log_activity(
                        'update_asset_audit_variable_assignment',
                        format('User %s updated audit variable assignment ID %s', p_causer_name, p_assignment_id),
                        'asset_audit_variable_assignments',
                        p_assignment_id,
                        'user',
                        p_causer_id,
                        new_record,
                        p_tenant_id
                    );
                    log_success := TRUE;
                EXCEPTION WHEN OTHERS THEN
                    log_success := FALSE;
                END;

                RETURN QUERY SELECT 'SUCCESS'::TEXT, 'Assignment updated successfully'::TEXT, p_assignment_id, old_record, new_record;
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
        DB::unprepared(<<<'SQL'
        DO $$
        DECLARE
            r RECORD;
        BEGIN
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'insert_or_update_asset_audit_variable_assignment'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;
        SQL);
    }
};