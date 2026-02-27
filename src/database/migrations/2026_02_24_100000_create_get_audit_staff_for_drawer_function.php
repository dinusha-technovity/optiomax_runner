<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates PostgreSQL function for audit staff drawer with action-based filtering
     * ISO 19011:2018 Compliant
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- Drop existing function if exists
        DROP FUNCTION IF EXISTS get_audit_staff_for_drawer CASCADE;

        CREATE OR REPLACE FUNCTION get_audit_staff_for_drawer(
            p_tenant_id BIGINT,
            p_staff_id BIGINT DEFAULT NULL,
            p_action TEXT DEFAULT NULL,
            p_audit_group_ids BIGINT[] DEFAULT NULL,
            p_page_no INT DEFAULT 1,
            p_page_size INT DEFAULT 10,
            p_search TEXT DEFAULT NULL,
            p_prefetch_mode TEXT DEFAULT 'both',
            p_sort_by TEXT DEFAULT 'newest'
        )
        RETURNS JSON
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_total_records INT := 0;
            v_total_pages INT := 0;
            v_data_prev JSON := '[]'::JSON;
            v_data_curr JSON := '[]'::JSON;
            v_data_next JSON := '[]'::JSON;
            v_offset_prev INT := 0;
            v_offset_curr INT := 0;
            v_offset_next INT := 0;
            v_order_clause TEXT := 'ORDER BY ast.id DESC';
            v_message TEXT := '';
            v_base_query TEXT := '';
            v_search_clause TEXT := '';
            v_action_clause TEXT := '';
        BEGIN
            ----------------------------------------------------------------
            -- Validations
            ----------------------------------------------------------------
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN json_build_object(
                    'success', false,
                    'message', 'Invalid tenant_id provided',
                    'data', json_build_object(
                        'prev', '[]'::JSON,
                        'current', '[]'::JSON,
                        'next', '[]'::JSON
                    ),
                    'pagination', json_build_object(
                        'current_page', 0,
                        'total_pages', 0,
                        'total_records', 0,
                        'page_size', p_page_size,
                        'has_previous', false,
                        'has_next', false
                    )
                );
            END IF;

            ----------------------------------------------------------------
            -- Sorting Logic
            ----------------------------------------------------------------
            CASE LOWER(TRIM(p_sort_by))
                WHEN 'newest' THEN v_order_clause := 'ORDER BY ast.id DESC';
                WHEN 'oldest' THEN v_order_clause := 'ORDER BY ast.id ASC';
                WHEN 'az' THEN v_order_clause := 'ORDER BY u.name ASC NULLS LAST';
                WHEN 'za' THEN v_order_clause := 'ORDER BY u.name DESC NULLS LAST';
                WHEN 'code_asc' THEN v_order_clause := 'ORDER BY ast.auditor_code ASC';
                WHEN 'code_desc' THEN v_order_clause := 'ORDER BY ast.auditor_code DESC';
                ELSE v_order_clause := 'ORDER BY ast.id DESC';
            END CASE;

            p_page_no := GREATEST(p_page_no, 1);
            p_page_size := GREATEST(p_page_size, 1);

            ----------------------------------------------------------------
            -- Search clause
            ----------------------------------------------------------------
            IF p_search IS NOT NULL AND LENGTH(TRIM(p_search)) > 0 THEN
                v_search_clause := format(
                    'AND (
                        LOWER(u.name) LIKE LOWER(''%%%s%%'') OR
                        LOWER(u.email) LIKE LOWER(''%%%s%%'') OR
                        LOWER(ast.auditor_code) LIKE LOWER(''%%%s%%'') OR
                        LOWER(d.designation) LIKE LOWER(''%%%s%%'')
                    )',
                    p_search, p_search, p_search, p_search
                );
            END IF;

            ----------------------------------------------------------------
            -- Build base query based on action
            ----------------------------------------------------------------
            IF p_action = 'by_groups' AND p_audit_group_ids IS NOT NULL AND array_length(p_audit_group_ids, 1) > 0 THEN
                -- Get audit staff assigned to specific audit groups
                v_action_clause := format(
                    'AND ast.id IN (
                        SELECT DISTINCT asga.audit_staff_id
                        FROM audit_staff_group_assignments asga
                        WHERE asga.audit_group_id = ANY(ARRAY[%s])
                            AND asga.tenant_id = %s
                            AND asga.deleted_at IS NULL
                            AND asga.isactive = TRUE
                    )',
                    array_to_string(p_audit_group_ids, ','),
                    p_tenant_id
                );
                v_message := 'Audit staff for specific groups retrieved successfully';
            ELSE
                -- Default: Get all audit staff
                v_action_clause := '';
                v_message := 'All audit staff retrieved successfully';
            END IF;

            -- Build base query
            v_base_query := format($b$
                FROM audit_staff ast
                INNER JOIN users u ON u.id = ast.user_id AND u.deleted_at IS NULL
                LEFT JOIN designations d ON d.id = u.designation_id AND d.deleted_at IS NULL AND d.isactive = TRUE
                LEFT JOIN organization org ON org.id = u.organization AND org.deleted_at IS NULL AND org.isactive = TRUE
                LEFT JOIN countries c ON c.id = u.contact_no_code
                LEFT JOIN users assigned_by_user ON assigned_by_user.id = ast.assigned_by AND assigned_by_user.deleted_at IS NULL
                WHERE (ast.id = %L OR %L IS NULL)
                    AND ast.tenant_id = %s
                    AND ast.deleted_at IS NULL
                    AND ast.isactive = TRUE
                    %s
                    %s
            $b$, 
                p_staff_id, p_staff_id, p_tenant_id,
                v_search_clause,
                v_action_clause
            );

            ----------------------------------------------------------------
            -- Count total records
            ----------------------------------------------------------------
            EXECUTE format($s$
                SELECT COUNT(DISTINCT ast.id)
                %s
            $s$, v_base_query)
            INTO v_total_records;

            IF v_total_records = 0 THEN
                RETURN json_build_object(
                    'success', true,
                    'message', 'No audit staff found',
                    'data', json_build_object(
                        'prev', '[]'::JSON,
                        'current', '[]'::JSON,
                        'next', '[]'::JSON
                    ),
                    'pagination', json_build_object(
                        'current_page', 0,
                        'total_pages', 0,
                        'total_records', 0,
                        'page_size', p_page_size,
                        'has_previous', false,
                        'has_next', false
                    )
                );
            END IF;

            ----------------------------------------------------------------
            -- Pagination
            ----------------------------------------------------------------
            v_total_pages := CEIL(v_total_records::DECIMAL / p_page_size);
            v_offset_curr := (p_page_no - 1) * p_page_size;
            v_offset_prev := GREATEST(v_offset_curr - p_page_size, 0);
            v_offset_next := p_page_no * p_page_size;

            ----------------------------------------------------------------
            -- Current page data
            ----------------------------------------------------------------
            EXECUTE format($s$
                SELECT json_build_object(
                    'success', true,
                    'message', '%s',
                    'data', COALESCE(json_agg(t.*), '[]'::JSON)
                )
                FROM (
                    SELECT DISTINCT ON (ast.id)
                        ast.id as staff_id,
                        ast.auditor_code,
                        ast.user_id,
                        u.name as user_name,
                        u.email as user_email,
                        u.contact_no as user_contact,
                        u.designation_id as user_designation_id,
                        d.designation as user_designation,
                        u.organization as user_organization_id,
                        org.data as user_organization_data,
                        u.contact_no_code as user_contact_no_code,
                        c.phone_code as user_country_code,
                        c.name_common as user_country_name,
                        ast.notes,
                        ast.assigned_by,
                        assigned_by_user.name as assigned_by_name,
                        ast.assigned_at,
                        (
                            SELECT COUNT(DISTINCT asga.audit_group_id)
                            FROM audit_staff_group_assignments asga
                            WHERE asga.audit_staff_id = ast.id
                                AND asga.tenant_id = ast.tenant_id
                                AND asga.deleted_at IS NULL
                                AND asga.isactive = TRUE
                        ) as assigned_groups_count,
                        ast.isactive,
                        ast.created_at,
                        ast.updated_at
                    %s
                    %s
                    LIMIT %s OFFSET %s
                ) t
            $s$, v_message, v_base_query, v_order_clause, p_page_size, v_offset_curr)
            INTO v_data_curr;
            v_data_curr := COALESCE(v_data_curr, '[]'::JSON);

            ----------------------------------------------------------------
            -- Previous page (if prefetch)
            ----------------------------------------------------------------
            IF p_prefetch_mode = 'both' AND p_page_no > 1 THEN
                EXECUTE format($s$
                    SELECT json_build_object(
                        'success', true,
                        'message', '%s',
                        'data', COALESCE(json_agg(t.*), '[]'::JSON)
                    )
                    FROM (
                        SELECT DISTINCT ON (ast.id)
                            ast.id as staff_id,
                            ast.auditor_code,
                            ast.user_id,
                            u.name as user_name,
                            u.email as user_email,
                            u.contact_no as user_contact,
                            u.designation_id as user_designation_id,
                            d.designation as user_designation,
                            u.organization as user_organization_id,
                            org.data as user_organization_data,
                            u.contact_no_code as user_contact_no_code,
                            c.phone_code as user_country_code,
                            c.name_common as user_country_name,
                            ast.notes,
                            ast.assigned_by,
                            assigned_by_user.name as assigned_by_name,
                            ast.assigned_at,
                            (
                                SELECT COUNT(DISTINCT asga.audit_group_id)
                                FROM audit_staff_group_assignments asga
                                WHERE asga.audit_staff_id = ast.id
                                    AND asga.tenant_id = ast.tenant_id
                                    AND asga.deleted_at IS NULL
                                    AND asga.isactive = TRUE
                            ) as assigned_groups_count,
                            ast.isactive,
                            ast.created_at,
                            ast.updated_at
                        %s
                        %s
                        LIMIT %s OFFSET %s
                    ) t
                $s$, v_message, v_base_query, v_order_clause, p_page_size, v_offset_prev)
                INTO v_data_prev;
                v_data_prev := COALESCE(v_data_prev, '[]'::JSON);
            END IF;

            ----------------------------------------------------------------
            -- Next page (if prefetch)
            ----------------------------------------------------------------
            IF (p_prefetch_mode = 'both' OR p_prefetch_mode = 'after') AND p_page_no < v_total_pages THEN
                EXECUTE format($s$
                    SELECT json_build_object(
                        'success', true,
                        'message', '%s',
                        'data', COALESCE(json_agg(t.*), '[]'::JSON)
                    )
                    FROM (
                        SELECT DISTINCT ON (ast.id)
                            ast.id as staff_id,
                            ast.auditor_code,
                            ast.user_id,
                            u.name as user_name,
                            u.email as user_email,
                            u.contact_no as user_contact,
                            u.designation_id as user_designation_id,
                            d.designation as user_designation,
                            u.organization as user_organization_id,
                            org.data as user_organization_data,
                            u.contact_no_code as user_contact_no_code,
                            c.phone_code as user_country_code,
                            c.name_common as user_country_name,
                            ast.notes,
                            ast.assigned_by,
                            assigned_by_user.name as assigned_by_name,
                            ast.assigned_at,
                            (
                                SELECT COUNT(DISTINCT asga.audit_group_id)
                                FROM audit_staff_group_assignments asga
                                WHERE asga.audit_staff_id = ast.id
                                    AND asga.tenant_id = ast.tenant_id
                                    AND asga.deleted_at IS NULL
                                    AND asga.isactive = TRUE
                            ) as assigned_groups_count,
                            ast.isactive,
                            ast.created_at,
                            ast.updated_at
                        %s
                        %s
                        LIMIT %s OFFSET %s
                    ) t
                $s$, v_message, v_base_query, v_order_clause, p_page_size, v_offset_next)
                INTO v_data_next;
                v_data_next := COALESCE(v_data_next, '[]'::JSON);
            END IF;

            ----------------------------------------------------------------
            -- Return final result
            ----------------------------------------------------------------
            RETURN json_build_object(
                'success', true,
                'message', v_message,
                'data', json_build_object(
                    'prev', v_data_prev,
                    'current', v_data_curr,
                    'next', v_data_next
                ),
                'pagination', json_build_object(
                    'current_page', p_page_no,
                    'total_pages', v_total_pages,
                    'total_records', v_total_records,
                    'page_size', p_page_size,
                    'has_previous', p_page_no > 1,
                    'has_next', p_page_no < v_total_pages
                )
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
        DB::unprepared('DROP FUNCTION IF EXISTS get_audit_staff_for_drawer CASCADE;');
    }
};