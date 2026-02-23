<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Create get audit staff details function
     * 
     * Returns detailed information about a specific audit staff member
     * including user details and all audit group assignments
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- Drop existing function if exists
        DROP FUNCTION IF EXISTS get_audit_staff_details(BIGINT, BIGINT) CASCADE;

        CREATE OR REPLACE FUNCTION get_audit_staff_details(
            p_staff_id BIGINT,
            p_tenant_id BIGINT
        ) RETURNS JSONB LANGUAGE plpgsql AS $$
        DECLARE
            v_staff_data JSONB;
            v_assignments JSONB;
        BEGIN
            -- Get staff details with user information
            SELECT jsonb_build_object(
                'id', ast.id,
                'user_id', ast.user_id,
                'user_name', u.name,
                'user_email', u.email,
                'user_contact_no', u.contact_no,
                'user_contact_no_code', u.contact_no_code,
                'user_country_code', c.phone_code,
                'user_country_name', c.name_common,
                'user_profile_image', u.profile_image,
                'user_designation_id', u.designation_id,
                'user_designation', d.designation,
                'user_organization_id', u.organization,
                'user_organization_data', org.data,
                'auditor_code', ast.auditor_code,
                'notes', ast.notes,
                'assigned_by', ast.assigned_by,
                'assigned_by_name', ab.name,
                'assigned_at', ast.assigned_at,
                'isactive', ast.isactive,
                'created_at', ast.created_at,
                'updated_at', ast.updated_at
            ) INTO v_staff_data
            FROM audit_staff ast
            INNER JOIN users u ON u.id = ast.user_id
            LEFT JOIN users ab ON ab.id = ast.assigned_by
            LEFT JOIN designations d ON d.id = u.designation_id AND d.deleted_at IS NULL AND d.isactive = TRUE
            LEFT JOIN organization org ON org.id = u.organization AND org.deleted_at IS NULL AND org.isactive = TRUE
            LEFT JOIN countries c ON c.id = u.contact_no_code
            WHERE ast.id = p_staff_id
                AND ast.tenant_id = p_tenant_id
                AND ast.deleted_at IS NULL;

            IF v_staff_data IS NULL THEN
                RETURN jsonb_build_object(
                    'status', 'ERROR',
                    'message', 'Audit staff not found'
                );
            END IF;

            -- Get all audit group assignments
            SELECT jsonb_agg(
                jsonb_build_object(
                    'assignment_id', asga.id,
                    'audit_group_id', ag.id,
                    'audit_group_name', ag.name,
                    'audit_group_description', ag.description,
                    'asset_count', COALESCE(ac.asset_count, 0),
                    'assigned_at', asga.created_at,
                    'isactive', asga.isactive
                ) ORDER BY asga.created_at DESC
            ) INTO v_assignments
            FROM audit_staff_group_assignments asga
            INNER JOIN audit_groups ag ON ag.id = asga.audit_group_id
            LEFT JOIN (
                SELECT 
                    audit_group_id,
                    COUNT(*) as asset_count
                FROM audit_groups_releated_assets
                WHERE deleted_at IS NULL
                    AND isactive = TRUE
                GROUP BY audit_group_id
            ) ac ON ac.audit_group_id = ag.id
            WHERE asga.audit_staff_id = p_staff_id
                AND asga.tenant_id = p_tenant_id
                AND asga.deleted_at IS NULL
                AND ag.deleted_at IS NULL;

            RETURN jsonb_build_object(
                'status', 'SUCCESS',
                'staff', v_staff_data,
                'assignments', COALESCE(v_assignments, '[]'::jsonb),
                'assignment_count', jsonb_array_length(COALESCE(v_assignments, '[]'::jsonb))
            );

        EXCEPTION
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
        DB::unprepared('DROP FUNCTION IF EXISTS get_audit_staff_details');
    }
};