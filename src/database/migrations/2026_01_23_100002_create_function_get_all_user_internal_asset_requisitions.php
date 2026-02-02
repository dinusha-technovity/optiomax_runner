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
                    WHERE proname = 'get_all_user_internal_asset_requisitions'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_all_user_internal_asset_requisitions(
            p_tenant_id BIGINT,
            p_user_id BIGINT DEFAULT NULL,
            p_targeted_responsible_person BIGINT DEFAULT NULL,
            p_requisition_status VARCHAR(255) DEFAULT NULL,
            p_page_no INT DEFAULT 1,
            p_page_size INT DEFAULT 10,
            p_search TEXT DEFAULT NULL,
            p_sort_by TEXT DEFAULT 'newest'
        )
        RETURNS JSON
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_total_records INT := 0;
            v_total_pages INT := 0;
            v_offset INT := 0;
            v_order_clause TEXT := 'ORDER BY iar.created_at DESC';
            v_search_clause TEXT := '';
            v_result JSON;
        BEGIN
            ----------------------------------------------------------------
            -- Validations
            ----------------------------------------------------------------
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN json_build_object(
                    'success', false,
                    'message', 'Invalid tenant ID provided',
                    'data', '[]'::JSON,
                    'meta', json_build_object(
                        'total_records', 0,
                        'total_pages', 0,
                        'current_page', p_page_no,
                        'page_size', p_page_size
                    )
                );
            END IF;

            IF p_user_id IS NOT NULL AND p_user_id <= 0 THEN
                RETURN json_build_object(
                    'success', false,
                    'message', 'Invalid user ID provided',
                    'data', '[]'::JSON,
                    'meta', json_build_object(
                        'total_records', 0,
                        'total_pages', 0,
                        'current_page', p_page_no,
                        'page_size', p_page_size
                    )
                );
            END IF;

            ----------------------------------------------------------------
            -- Sorting Logic
            ----------------------------------------------------------------
            CASE LOWER(TRIM(p_sort_by))
                WHEN 'newest' THEN v_order_clause := 'ORDER BY iar.created_at DESC';
                WHEN 'oldest' THEN v_order_clause := 'ORDER BY iar.created_at ASC';
                WHEN 'date_asc' THEN v_order_clause := 'ORDER BY iar.requested_date ASC';
                WHEN 'date_desc' THEN v_order_clause := 'ORDER BY iar.requested_date DESC';
                ELSE v_order_clause := 'ORDER BY iar.created_at DESC';
            END CASE;

            p_page_no := GREATEST(p_page_no, 1);
            p_page_size := GREATEST(p_page_size, 1);
            v_offset := (p_page_no - 1) * p_page_size;

            ----------------------------------------------------------------
            -- Search clause
            ----------------------------------------------------------------
            IF p_search IS NOT NULL AND LENGTH(TRIM(p_search)) > 0 THEN
                v_search_clause := format(
                    'AND (
                        LOWER(iar.requisition_id) LIKE LOWER(%L) OR
                        LOWER(iar.requisition_status) LIKE LOWER(%L) OR
                        LOWER(u_by.name) LIKE LOWER(%L) OR
                        LOWER(u_target.name) LIKE LOWER(%L)
                    )',
                    '%' || p_search || '%',
                    '%' || p_search || '%',
                    '%' || p_search || '%',
                    '%' || p_search || '%'
                );
            END IF;

            ----------------------------------------------------------------
            -- Count total records
            ----------------------------------------------------------------
            EXECUTE format($s$
                SELECT COUNT(DISTINCT iar.id)
                FROM internal_asset_requisitions iar
                LEFT JOIN users u_by ON iar.requisition_by = u_by.id
                LEFT JOIN users u_target ON iar.targeted_responsible_person = u_target.id
                WHERE iar.tenant_id = %s
                AND iar.deleted_at IS NULL
                AND iar.isactive = true
                %s
                %s
                %s
                %s
            $s$,
                p_tenant_id,
                CASE WHEN p_user_id IS NOT NULL THEN format('AND iar.requisition_by = %s', p_user_id) ELSE '' END,
                CASE WHEN p_targeted_responsible_person IS NOT NULL THEN format('AND iar.targeted_responsible_person = %s', p_targeted_responsible_person) ELSE '' END,
                CASE 
                    WHEN p_requisition_status IS NOT NULL THEN 
                        CASE 
                            WHEN p_requisition_status LIKE '%,%' THEN 
                                format('AND UPPER(iar.requisition_status) IN (%s)', 
                                    (SELECT string_agg(format('''%s''', UPPER(TRIM(status))), ',') 
                                     FROM unnest(string_to_array(p_requisition_status, ',')) AS status)
                                )
                            ELSE 
                                format('AND UPPER(iar.requisition_status) = UPPER(%L)', p_requisition_status)
                        END
                    ELSE '' 
                END,
                v_search_clause
            )
            INTO v_total_records;

            IF v_total_records = 0 THEN
                RETURN json_build_object(
                    'success', true,
                    'message', 'No internal asset requisitions found',
                    'data', '[]'::JSON,
                    'meta', json_build_object(
                        'total_records', 0,
                        'total_pages', 0,
                        'current_page', p_page_no,
                        'page_size', p_page_size
                    )
                );
            END IF;

            v_total_pages := CEIL(v_total_records::DECIMAL / p_page_size);

            ----------------------------------------------------------------
            -- Fetch requisitions with items
            ----------------------------------------------------------------
            EXECUTE format($s$
                SELECT json_build_object(
                    'success', true,
                    'message', 'Internal asset requisitions retrieved successfully',
                    'data', COALESCE(json_agg(requisition_data), '[]'::JSON),
                    'meta', json_build_object(
                        'total_records', %s,
                        'total_pages', %s,
                        'current_page', %s,
                        'page_size', %s
                    )
                )
                FROM (
                    SELECT json_build_object(
                        'requisition_id', iar.id,
                        'requisition_identifier', iar.requisition_id,
                        'targeted_responsible_person', iar.targeted_responsible_person,
                        'targeted_responsible_person_name', u_target.name,
                        'targeted_responsible_person_email', u_target.email,
                        'targeted_responsible_person_profile_image', u_target.profile_image,
                        'targeted_responsible_person_organization', CASE 
                            WHEN org_target.id IS NOT NULL THEN json_build_object(
                                'id', org_target.id,
                                'data', org_target.data,
                                'level', org_target.level,
                                'parent_node_id', org_target.parent_node_id
                            )
                            ELSE NULL
                        END,
                        'targeted_responsible_person_designation', CASE 
                            WHEN des_target.id IS NOT NULL THEN json_build_object(
                                'id', des_target.id,
                                'designation', des_target.designation
                            )
                            ELSE NULL
                        END,
                        'requisition_by', iar.requisition_by,
                        'requisition_by_name', u_by.name,
                        'requisition_by_email', u_by.email,
                        'requisition_by_profile_image', u_by.profile_image,
                        'requisition_by_organization', CASE 
                            WHEN org_by.id IS NOT NULL THEN json_build_object(
                                'id', org_by.id,
                                'data', org_by.data,
                                'level', org_by.level,
                                'parent_node_id', org_by.parent_node_id
                            )
                            ELSE NULL
                        END,
                        'requisition_by_designation', CASE 
                            WHEN des_by.id IS NOT NULL THEN json_build_object(
                                'id', des_by.id,
                                'designation', des_by.designation
                            )
                            ELSE NULL
                        END,
                        'requested_date', iar.requested_date,
                        'requisition_status', iar.requisition_status,
                        'work_flow_request', iar.work_flow_request,
                        'workflow_details', CASE 
                            WHEN wrq.id IS NOT NULL THEN json_build_object(
                                'workflow_request_id', wrq.id,
                                'workflow_request_type', wrq.workflow_request_type,
                                'workflow_request_status', wrq.workflow_request_status,
                                'approved_at', wrq.updated_at,
                                'approval_levels', COALESCE(
                                    (
                                        SELECT json_agg(
                                            json_build_object(
                                                'level_id', wrqd.id,
                                                'workflow_node_id', wrqd.workflow_node_id,
                                                'request_status_from_level', wrqd.request_status_from_level,
                                                'approver_user_id', wrqd.approver_user_id,
                                                'approver_name', u_app.name,
                                                'approver_email', u_app.email,
                                                'comment_for_action', wrqd.comment_for_action,
                                                'action_date', wrqd.updated_at,
                                                'created_at', wrqd.created_at
                                            ) ORDER BY wrqd.created_at ASC
                                        )
                                        FROM workflow_request_queue_details wrqd
                                        LEFT JOIN users u_app ON wrqd.approver_user_id = u_app.id
                                        WHERE wrqd.request_id = wrq.id
                                    ),
                                    '[]'::JSON
                                )
                            )
                            ELSE NULL
                        END,
                        'created_at', iar.created_at,
                        'updated_at', iar.updated_at,
                        'items', COALESCE(
                            (
                                SELECT json_agg(
                                    json_build_object(
                                        'item_id', iari.id,
                                        'item_selection_type_id', iari.internal_asset_requisitions_item_selection_types_id,
                                        'item_selection_type_name', iarst.name,
                                        'item_selection_type_description', iarst.description,
                                        'asset_item_id', iari.asset_item_id,
                                        'asset_item_name', a.name,
                                        'asset_item_serial_number', ai.serial_number,
                                        'item_name', iari.item_name,
                                        'required_quantity', iari.required_quantity,
                                        'required_date', iari.required_date,
                                        'priority', iari.priority,
                                        'priority_name', arpt.name,
                                        'department', iari.department,
                                        'department_data', org.data,
                                        'required_location_latitude', iari.required_location_latitude,
                                        'required_location_longitude', iari.required_location_longitude,
                                        'reason_for_requirement', iari.reason_for_requirement,
                                        'additional_notes', iari.additional_notes,
                                        'other_details', iari.other_details,
                                        'related_documents', iari.related_documents,
                                        'created_at', iari.created_at,
                                        'updated_at', iari.updated_at
                                    )
                                )
                                FROM internal_asset_requisitions_items iari
                                LEFT JOIN internal_asset_requisitions_item_selection_types iarst 
                                    ON iari.internal_asset_requisitions_item_selection_types_id = iarst.id
                                LEFT JOIN asset_items ai ON iari.asset_item_id = ai.id
                                LEFT JOIN assets a ON ai.asset_id = a.id
                                LEFT JOIN asset_requisition_priority_types arpt ON iari.priority = arpt.id
                                LEFT JOIN organization org ON iari.department = org.id
                                WHERE iari.internal_asset_requisition_id = iar.id
                                AND iari.deleted_at IS NULL
                                AND iari.isactive = true
                            ),
                            '[]'::JSON
                        )
                    ) AS requisition_data
                    FROM internal_asset_requisitions iar
                    LEFT JOIN users u_by ON iar.requisition_by = u_by.id
                    LEFT JOIN users u_target ON iar.targeted_responsible_person = u_target.id
                    LEFT JOIN organization org_by ON u_by.organization = org_by.id
                    LEFT JOIN organization org_target ON u_target.organization = org_target.id
                    LEFT JOIN designations des_by ON u_by.designation_id = des_by.id
                    LEFT JOIN designations des_target ON u_target.designation_id = des_target.id
                    LEFT JOIN workflow_request_queues wrq ON iar.work_flow_request = wrq.id
                    WHERE iar.tenant_id = %s
                    AND iar.deleted_at IS NULL
                    AND iar.isactive = true
                    %s
                    %s
                    %s
                    %s
                    %s
                    LIMIT %s OFFSET %s
                ) AS subquery
            $s$,
                v_total_records,
                v_total_pages,
                p_page_no,
                p_page_size,
                p_tenant_id,
                CASE WHEN p_user_id IS NOT NULL THEN format('AND iar.requisition_by = %s', p_user_id) ELSE '' END,
                CASE WHEN p_targeted_responsible_person IS NOT NULL THEN format('AND iar.targeted_responsible_person = %s', p_targeted_responsible_person) ELSE '' END,
                CASE 
                    WHEN p_requisition_status IS NOT NULL THEN 
                        CASE 
                            WHEN p_requisition_status LIKE '%,%' THEN 
                                format('AND UPPER(iar.requisition_status) IN (%s)', 
                                    (SELECT string_agg(format('''%s''', UPPER(TRIM(status))), ',') 
                                     FROM unnest(string_to_array(p_requisition_status, ',')) AS status)
                                )
                            ELSE 
                                format('AND UPPER(iar.requisition_status) = UPPER(%L)', p_requisition_status)
                        END
                    ELSE '' 
                END,
                v_search_clause,
                v_order_clause,
                p_page_size,
                v_offset
            )
            INTO v_result;

            RETURN v_result;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_all_user_internal_asset_requisitions CASCADE;');
    }
};