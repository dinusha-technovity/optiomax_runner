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
                        WHERE proname = 'get_single_internal_asset_requisition'
                    LOOP
                        EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                    END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION get_single_internal_asset_requisition(
                p_requisition_id BIGINT,
                p_tenant_id BIGINT DEFAULT NULL
            )
            RETURNS JSON
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_result JSON;
            BEGIN
                SELECT json_build_object(
                    'success', true,
                    'message', 'Internal asset requisition retrieved successfully',
                    'data', CASE 
                        WHEN iar.id IS NOT NULL THEN json_build_object(
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
                                                    'approver_profile_image', u_app.profile_image,
                                                    'approver_designation', CASE 
                                                        WHEN des_app.id IS NOT NULL THEN json_build_object(
                                                            'id', des_app.id,
                                                            'designation', des_app.designation
                                                        )
                                                        ELSE NULL
                                                    END,
                                                    'comment_for_action', wrqd.comment_for_action,
                                                    'action_date', wrqd.updated_at,
                                                    'created_at', wrqd.created_at
                                                ) ORDER BY wrqd.created_at ASC
                                            )
                                            FROM workflow_request_queue_details wrqd
                                            LEFT JOIN users u_app ON wrqd.approver_user_id = u_app.id
                                            LEFT JOIN designations des_app ON u_app.designation_id = des_app.id
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
                                            'fulfilled_quantity', iari.fulfilled_quantity,
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
                                            'is_rejected_by_responsible_person', iari.is_rejected_by_responsible_person,
                                            'rejection_reason', iari.rejection_reason,
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
                                        AND iari.isactive = TRUE
                                ),
                                '[]'::JSON
                            )
                        )
                        ELSE NULL
                    END
                )
                INTO v_result
                FROM internal_asset_requisitions iar
                LEFT JOIN users u_target ON iar.targeted_responsible_person = u_target.id
                LEFT JOIN organization org_target ON u_target.organization = org_target.id
                LEFT JOIN designations des_target ON u_target.designation_id = des_target.id
                LEFT JOIN users u_by ON iar.requisition_by = u_by.id
                LEFT JOIN organization org_by ON u_by.organization = org_by.id
                LEFT JOIN designations des_by ON u_by.designation_id = des_by.id
                LEFT JOIN workflow_request_queues wrq ON iar.work_flow_request = wrq.id
                WHERE iar.id = p_requisition_id
                    AND iar.deleted_at IS NULL
                    AND (p_tenant_id IS NULL OR iar.tenant_id = p_tenant_id);

                -- If no requisition found
                IF v_result->>'data' IS NULL THEN
                    RETURN json_build_object(
                        'success', false,
                        'message', 'Internal asset requisition not found',
                        'data', NULL
                    );
                END IF;

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
        DB::unprepared('DROP FUNCTION IF EXISTS get_single_internal_asset_requisition(BIGINT, BIGINT);');
    }
};