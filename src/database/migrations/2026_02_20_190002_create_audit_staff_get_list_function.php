<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Create get audit staff list function
     * 
     * Returns paginated list of audit staff with user details and assignment counts
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- Drop existing function if exists
        DROP FUNCTION IF EXISTS get_audit_staff(BIGINT, VARCHAR, INT, INT, BOOLEAN) CASCADE;

        CREATE OR REPLACE FUNCTION get_audit_staff(
            p_tenant_id BIGINT,
            p_search_term VARCHAR DEFAULT NULL,
            p_page INT DEFAULT 1,
            p_per_page INT DEFAULT 15,
            p_only_active BOOLEAN DEFAULT TRUE
        ) RETURNS JSONB LANGUAGE plpgsql AS $$
        DECLARE
            v_offset INT;
            v_total_count INT;
            v_data JSONB;
        BEGIN
            -- Calculate offset
            v_offset := (p_page - 1) * p_per_page;

            -- Build and execute count query
            SELECT COUNT(*) INTO v_total_count
            FROM audit_staff ast
            INNER JOIN users u ON u.id = ast.user_id
            WHERE ast.tenant_id = p_tenant_id
                AND ast.deleted_at IS NULL
                AND (NOT p_only_active OR ast.isactive = TRUE)
                AND (
                    p_search_term IS NULL 
                    OR p_search_term = ''
                    OR u.name ILIKE '%' || p_search_term || '%'
                    OR u.email ILIKE '%' || p_search_term || '%'
                    OR ast.auditor_code ILIKE '%' || p_search_term || '%'
                );

            -- Build and execute data query with user details and assignment counts
            SELECT jsonb_agg(
                jsonb_build_object(
                    'id', staff.id,
                    'user_id', staff.user_id,
                    'user_name', staff.user_name,
                    'user_email', staff.user_email,
                    'user_contact_no', staff.user_contact_no,
                    'user_contact_no_code', staff.user_contact_no_code,
                    'user_country_code', staff.user_country_code,
                    'user_country_name', staff.user_country_name,
                    'user_profile_image', staff.user_profile_image,
                    'user_designation_id', staff.user_designation_id,
                    'user_designation', staff.user_designation,
                    'user_organization_id', staff.user_organization_id,
                    'user_organization_data', staff.user_organization_data,
                    'auditor_code', staff.auditor_code,
                    'notes', staff.notes,
                    'assigned_by', staff.assigned_by,
                    'assigned_by_name', staff.assigned_by_name,
                    'assigned_at', staff.assigned_at,
                    'assignment_count', staff.assignment_count,
                    'isactive', staff.isactive,
                    'created_at', staff.created_at,
                    'updated_at', staff.updated_at
                )
            ) INTO v_data
            FROM (
                SELECT 
                    ast.id,
                    ast.user_id,
                    u.name as user_name,
                    u.email as user_email,
                    u.contact_no as user_contact_no,
                    u.contact_no_code as user_contact_no_code,
                    c.phone_code as user_country_code,
                    c.name_common as user_country_name,
                    u.profile_image as user_profile_image,
                    u.designation_id as user_designation_id,
                    d.designation as user_designation,
                    u.organization as user_organization_id,
                    org.data as user_organization_data,
                    ast.auditor_code,
                    ast.notes,
                    ast.assigned_by,
                    ab.name as assigned_by_name,
                    ast.assigned_at,
                    COALESCE(ac.assignment_count, 0) as assignment_count,
                    ast.isactive,
                    ast.created_at,
                    ast.updated_at
                FROM audit_staff ast
                INNER JOIN users u ON u.id = ast.user_id
                LEFT JOIN users ab ON ab.id = ast.assigned_by
                LEFT JOIN designations d ON d.id = u.designation_id AND d.deleted_at IS NULL AND d.isactive = TRUE
                LEFT JOIN organization org ON org.id = u.organization AND org.deleted_at IS NULL AND org.isactive = TRUE
                LEFT JOIN countries c ON c.id = u.contact_no_code
                LEFT JOIN (
                    SELECT 
                        audit_staff_id,
                        COUNT(*) as assignment_count
                    FROM audit_staff_group_assignments
                    WHERE deleted_at IS NULL
                        AND isactive = TRUE
                    GROUP BY audit_staff_id
                ) ac ON ac.audit_staff_id = ast.id
                WHERE ast.tenant_id = p_tenant_id
                    AND ast.deleted_at IS NULL
                    AND (NOT p_only_active OR ast.isactive = TRUE)
                    AND (
                        p_search_term IS NULL 
                        OR p_search_term = ''
                        OR u.name ILIKE '%' || p_search_term || '%'
                        OR u.email ILIKE '%' || p_search_term || '%'
                        OR ast.auditor_code ILIKE '%' || p_search_term || '%'
                    )
                ORDER BY ast.created_at DESC
                LIMIT p_per_page
                OFFSET v_offset
            ) staff;

            -- Return result
            RETURN jsonb_build_object(
                'status', 'SUCCESS',
                'data', COALESCE(v_data, '[]'::jsonb),
                'pagination', jsonb_build_object(
                    'total', v_total_count,
                    'per_page', p_per_page,
                    'current_page', p_page,
                    'last_page', CEIL(v_total_count::DECIMAL / p_per_page),
                    'from', CASE WHEN v_total_count > 0 THEN v_offset + 1 ELSE 0 END,
                    'to', CASE WHEN v_total_count > 0 THEN LEAST(v_offset + p_per_page, v_total_count) ELSE 0 END
                )
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
        DB::unprepared('DROP FUNCTION IF EXISTS get_audit_staff');
    }
};