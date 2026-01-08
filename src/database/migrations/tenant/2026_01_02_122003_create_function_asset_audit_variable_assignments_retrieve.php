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
                    WHERE proname = 'get_asset_audit_variable_assignments'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
            
            CREATE OR REPLACE FUNCTION get_asset_audit_variable_assignments(
                IN p_tenant_id BIGINT,
                IN p_assignable_type_id BIGINT DEFAULT NULL,
                IN p_assignable_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                assignment_id BIGINT,
                asset_audit_variable_id BIGINT,
                variable_name TEXT,
                variable_description TEXT,
                variable_type_id BIGINT,
                variable_type_name TEXT,
                assignable_type_id BIGINT,
                assignable_type_name TEXT,
                assignable_id BIGINT,
                assigned_by BIGINT,
                assigned_at TIMESTAMPTZ,
                is_active BOOLEAN,
                is_inherited_from_parent BOOLEAN
            )
            LANGUAGE plpgsql
            AS \$\$
            DECLARE
                v_record_count INT;
                v_asset_type_id BIGINT;
            BEGIN
                -- Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT AS assignment_id,
                        NULL::BIGINT AS asset_audit_variable_id,
                        NULL::TEXT AS variable_name,
                        NULL::TEXT AS variable_description,
                        NULL::BIGINT AS variable_type_id,
                        NULL::TEXT AS variable_type_name,
                        NULL::BIGINT AS assignable_type_id,
                        NULL::TEXT AS assignable_type_name,
                        NULL::BIGINT AS assignable_id,
                        NULL::BIGINT AS assigned_by,
                        NULL::TIMESTAMPTZ AS assigned_at,
                        NULL::BOOLEAN AS is_active,
                        NULL::BOOLEAN AS is_inherited_from_parent;
                    RETURN;
                END IF;

                -- Get Asset assignable_type_id for later use
                SELECT id INTO v_asset_type_id FROM assignable_types WHERE name = 'Asset' LIMIT 1;

                -- Validate assignable_type_id if provided
                IF p_assignable_type_id IS NOT NULL THEN
                    IF p_assignable_type_id < 0 THEN
                        RETURN QUERY SELECT 
                            'FAILURE'::TEXT AS status,
                            'Invalid assignable type ID provided'::TEXT AS message,
                            NULL::BIGINT AS assignment_id,
                            NULL::BIGINT AS asset_audit_variable_id,
                            NULL::TEXT AS variable_name,
                            NULL::TEXT AS variable_description,
                            NULL::BIGINT AS variable_type_id,
                            NULL::TEXT AS variable_type_name,
                            NULL::BIGINT AS assignable_type_id,
                            NULL::TEXT AS assignable_type_name,
                            NULL::BIGINT AS assignable_id,
                            NULL::BIGINT AS assigned_by,
                            NULL::TIMESTAMPTZ AS assigned_at,
                            NULL::BOOLEAN AS is_active,
                            NULL::BOOLEAN AS is_inherited_from_parent;
                        RETURN;
                    END IF;

                    -- Validate assignable_type_id exists
                    IF NOT EXISTS (SELECT 1 FROM assignable_types atypes WHERE atypes.id = p_assignable_type_id AND atypes.is_active = TRUE) THEN
                        RETURN QUERY SELECT 
                            'FAILURE'::TEXT AS status,
                            'Assignable type does not exist or is inactive'::TEXT AS message,
                            NULL::BIGINT AS assignment_id,
                            NULL::BIGINT AS asset_audit_variable_id,
                            NULL::TEXT AS variable_name,
                            NULL::TEXT AS variable_description,
                            NULL::BIGINT AS variable_type_id,
                            NULL::TEXT AS variable_type_name,
                            NULL::BIGINT AS assignable_type_id,
                            NULL::TEXT AS assignable_type_name,
                            NULL::BIGINT AS assignable_id,
                            NULL::BIGINT AS assigned_by,
                            NULL::TIMESTAMPTZ AS assigned_at,
                            NULL::BOOLEAN AS is_active,
                            NULL::BOOLEAN AS is_inherited_from_parent;
                        RETURN;
                    END IF;
                END IF;

                -- Validate assignable_id if provided
                IF p_assignable_id IS NOT NULL AND p_assignable_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid assignable ID provided'::TEXT AS message,
                        NULL::BIGINT AS assignment_id,
                        NULL::BIGINT AS asset_audit_variable_id,
                        NULL::TEXT AS variable_name,
                        NULL::TEXT AS variable_description,
                        NULL::BIGINT AS variable_type_id,
                        NULL::TEXT AS variable_type_name,
                        NULL::BIGINT AS assignable_type_id,
                        NULL::TEXT AS assignable_type_name,
                        NULL::BIGINT AS assignable_id,
                        NULL::BIGINT AS assigned_by,
                        NULL::TIMESTAMPTZ AS assigned_at,
                        NULL::BOOLEAN AS is_active,
                        NULL::BOOLEAN AS is_inherited_from_parent;
                    RETURN;
                END IF;

                -- Return matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Asset audit variable assignments fetched successfully'::TEXT AS message,
                    aava.id AS assignment_id,
                    aava.asset_audit_variable_id,
                    aav.name::TEXT AS variable_name,
                    aav.description::TEXT AS variable_description,
                    aav.asset_audit_variable_type_id AS variable_type_id,
                    aavt.name::TEXT AS variable_type_name,
                    aava.assignable_type_id,
                    at.name::TEXT AS assignable_type_name,
                    aava.assignable_id,
                    aava.assigned_by,
                    aava.assigned_at::TIMESTAMPTZ,
                    aava.is_active,
                    FALSE AS is_inherited_from_parent
                FROM
                    asset_audit_variable_assignments aava
                INNER JOIN
                    asset_audit_variable aav ON aav.id = aava.asset_audit_variable_id
                LEFT JOIN
                    asset_audit_variable_type aavt ON aavt.id = aav.asset_audit_variable_type_id
                INNER JOIN
                    assignable_types at ON at.id = aava.assignable_type_id
                WHERE
                    aava.tenant_id = p_tenant_id
                    AND (p_assignable_type_id IS NULL OR aava.assignable_type_id = p_assignable_type_id)
                    AND (p_assignable_id IS NULL OR aava.assignable_id = p_assignable_id)
                    AND aava.deleted_at IS NULL
                    AND aava.is_active = TRUE
                    AND aav.deleted_at IS NULL
                    AND aav.is_active = TRUE
                
                UNION ALL
                
                -- Include parent asset assignments for AssetItem queries
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Asset audit variable assignments fetched successfully'::TEXT AS message,
                    parent_aava.id AS assignment_id,
                    parent_aava.asset_audit_variable_id,
                    parent_aav.name::TEXT AS variable_name,
                    parent_aav.description::TEXT AS variable_description,
                    parent_aav.asset_audit_variable_type_id AS variable_type_id,
                    parent_aavt.name::TEXT AS variable_type_name,
                    parent_aava.assignable_type_id,
                    parent_at.name::TEXT AS assignable_type_name,
                    parent_aava.assignable_id,
                    parent_aava.assigned_by,
                    parent_aava.assigned_at::TIMESTAMPTZ,
                    parent_aava.is_active,
                    TRUE AS is_inherited_from_parent
                FROM
                    asset_items ai
                INNER JOIN
                    asset_audit_variable_assignments parent_aava ON parent_aava.assignable_id = ai.asset_id 
                    AND parent_aava.assignable_type_id = v_asset_type_id
                INNER JOIN
                    asset_audit_variable parent_aav ON parent_aav.id = parent_aava.asset_audit_variable_id
                LEFT JOIN
                    asset_audit_variable_type parent_aavt ON parent_aavt.id = parent_aav.asset_audit_variable_type_id
                INNER JOIN
                    assignable_types parent_at ON parent_at.id = parent_aava.assignable_type_id
                WHERE
                    parent_aava.tenant_id = p_tenant_id
                    AND p_assignable_type_id = (SELECT id FROM assignable_types WHERE name = 'AssetItem' LIMIT 1)
                    AND (p_assignable_id IS NULL OR ai.id = p_assignable_id)
                    AND ai.deleted_at IS NULL
                    AND parent_aava.deleted_at IS NULL
                    AND parent_aava.is_active = TRUE
                    AND parent_aav.deleted_at IS NULL
                    AND parent_aav.is_active = TRUE
                
                ORDER BY assigned_at DESC;

                -- Check if any records were found
                GET DIAGNOSTICS v_record_count = ROW_COUNT;
                
                IF v_record_count = 0 THEN
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status,
                        'No asset audit variable assignments found for the given criteria'::TEXT AS message,
                        NULL::BIGINT AS assignment_id,
                        NULL::BIGINT AS asset_audit_variable_id,
                        NULL::TEXT AS variable_name,
                        NULL::TEXT AS variable_description,
                        NULL::BIGINT AS variable_type_id,
                        NULL::TEXT AS variable_type_name,
                        NULL::BIGINT AS assignable_type_id,
                        NULL::TEXT AS assignable_type_name,
                        NULL::BIGINT AS assignable_id,
                        NULL::BIGINT AS assigned_by,
                        NULL::TIMESTAMPTZ AS assigned_at,
                        NULL::BOOLEAN AS is_active,
                        NULL::BOOLEAN AS is_inherited_from_parent;
                    RETURN;
                END IF;

            END;
            \$\$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_audit_variable_assignments');
    }
};