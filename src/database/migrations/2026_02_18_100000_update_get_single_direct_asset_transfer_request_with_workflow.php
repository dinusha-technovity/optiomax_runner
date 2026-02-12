<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Add workflow queue details to direct asset transfer request
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- Drop existing function if exists
        DROP FUNCTION IF EXISTS get_single_direct_asset_transfer_request(BIGINT, BIGINT);

        -- Function to get single direct asset transfer request with full details including workflow
        CREATE OR REPLACE FUNCTION get_single_direct_asset_transfer_request(
            p_transfer_request_id BIGINT,
            p_tenant_id BIGINT
        ) RETURNS TABLE (
            transfer_request JSONB
        ) LANGUAGE plpgsql AS $$
        DECLARE
            v_transfer_data JSONB;
            v_items_data JSONB;
            v_workflow_data JSONB;
        BEGIN
            -- Get main transfer request data
            SELECT jsonb_build_object(
                'id', datr.id,
                'transfer_request_number', datr.transfer_request_number,
                'transfer_type', datr.transfer_type,
                'transfer_status', datr.transfer_status,
                'work_flow_request', datr.work_flow_request,
                'targeted_responsible_person', CASE 
                    WHEN datr.targeted_responsible_person IS NOT NULL THEN
                        jsonb_build_object(
                            'id', tp.id,
                            'name', tp.name,
                            'email', tp.email,
                            'phone', tp.contact_no,
                            'profile_image', tp.profile_image,
                            'designation', td.designation
                        )
                    ELSE NULL
                END,
                'transfer_reason', datr.transfer_reason,
                'special_note', datr.special_note,
                'is_cancelled', datr.is_cancelled,
                'reason_for_cancellation', datr.reason_for_cancellation,
                'is_received_by_target_person', datr.is_received_by_target_person,
                'received_by_target_person_at', datr.received_by_target_person_at,
                'requester', jsonb_build_object(
                    'id', u.id,
                    'name', u.name,
                    'email', u.email,
                    'phone', u.contact_no,
                    'profile_image', u.profile_image,
                    'designation', d.designation
                ),
                'requested_date', datr.requested_date,
                'created_at', datr.created_at,
                'updated_at', datr.updated_at
            ) INTO v_transfer_data
            FROM direct_asset_transfer_requests datr
            LEFT JOIN users u ON u.id = datr.requester_id
            LEFT JOIN designations d ON d.id = u.designation_id
            LEFT JOIN users tp ON tp.id = datr.targeted_responsible_person
            LEFT JOIN designations td ON td.id = tp.designation_id
            WHERE datr.id = p_transfer_request_id
                AND datr.tenant_id = p_tenant_id
                AND datr.deleted_at IS NULL;

            IF v_transfer_data IS NULL THEN
                RETURN QUERY SELECT NULL::JSONB;
                RETURN;
            END IF;

            -- Get workflow details with approval levels (similar to internal asset requisitions)
            SELECT CASE 
                WHEN wrq.id IS NOT NULL THEN
                    jsonb_build_object(
                        'workflow_request_id', wrq.id,
                        'workflow_request_type', wrq.workflow_request_type,
                        'workflow_request_status', wrq.workflow_request_status,
                        'approved_at', wrq.updated_at,
                        'created_at', wrq.created_at,
                        'approval_levels', COALESCE(
                            (
                                SELECT jsonb_agg(
                                    jsonb_build_object(
                                        'level_id', wrqd.id,
                                        'workflow_node_id', wrqd.workflow_node_id,
                                        'request_status_from_level', wrqd.request_status_from_level,
                                        'approver_user_id', wrqd.approver_user_id,
                                        'approver_name', u_app.name,
                                        'approver_email', u_app.email,
                                        'approver_profile_image', u_app.profile_image,
                                        'approver_designation', CASE 
                                            WHEN des_app.id IS NOT NULL THEN jsonb_build_object(
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
                            '[]'::JSONB
                        )
                    )
                ELSE NULL
            END INTO v_workflow_data
            FROM direct_asset_transfer_requests datr
            LEFT JOIN workflow_request_queues wrq ON datr.work_flow_request = wrq.id
            WHERE datr.id = p_transfer_request_id
                AND datr.tenant_id = p_tenant_id
                AND datr.deleted_at IS NULL;

            -- Get asset items with detailed information
            SELECT jsonb_agg(
                jsonb_build_object(
                    'id', datri.id,
                    'asset_item', jsonb_build_object(
                        'id', ai.id,
                        'asset_tag', ai.asset_tag,
                        'model_number', ai.model_number,
                        'serial_number', ai.serial_number,
                        'thumbnail_image', ai.thumbnail_image,
                        'qr_code', ai.qr_code,
                        'item_value', ai.item_value,
                        'item_value_currency_id', ai.item_value_currency_id,
                        'currency_symbol', c.symbol,
                        'currency_code', c.code,
                        'item_documents', ai.item_documents,
                        'responsible_person', ai.responsible_person,
                        'asset_location_latitude', ai.asset_location_latitude,
                        'asset_location_longitude', ai.asset_location_longitude,
                        'asset_classification', ai.asset_classification,
                        'reading_parameters', ai.reading_parameters,
                        'asset', jsonb_build_object(
                            'id', a.id,
                            'name', a.name,
                            'thumbnail_image', a.thumbnail_image,
                            'asset_description', a.asset_description,
                            'asset_details', a.asset_details,
                            'asset_classification', a.asset_classification,
                            'reading_parameters', a.reading_parameters,
                            'assets_type_id', ac.assets_type,
                            'assets_type_name', ast.name,
                            'category_id', a.category,
                            'category_name', ac.name,
                            'sub_category_id', a.sub_category,
                            'sub_category_name', assc.name
                        )
                    ),
                    'current_owner', jsonb_build_object(
                        'id', cu.id,
                        'name', cu.name,
                        'user_name', cu.user_name,
                        'email', cu.email,
                        'contact_no', cu.contact_no,
                        'designation', cd.designation,
                        'designation_id', cd.id,
                        'profile_image', cu.profile_image
                    ),
                    'current_department', CASE 
                        WHEN coh.id IS NOT NULL THEN
                            jsonb_build_object(
                                'id', coh.id,
                                'parent_node_id', coh.parent_node_id,
                                'level', coh.level,
                                'relationship', coh.relationship,
                                'data', coh.data,
                                'name', coh.data->>'organizationName'
                            )
                        ELSE NULL
                    END,
                    'current_location', jsonb_build_object(
                        'latitude', datri.current_location_latitude,
                        'longitude', datri.current_location_longitude,
                        'address', datri.current_location_address
                    ),
                    'new_owner', CASE 
                        WHEN datri.new_owner_id IS NOT NULL THEN
                            jsonb_build_object(
                                'id', nu.id,
                                'name', nu.name,
                                'user_name', nu.user_name,
                                'email', nu.email,
                                'contact_no', nu.contact_no,
                                'designation', nd.designation,
                                'designation_id', nd.id,
                                'profile_image', nu.profile_image
                            )
                        ELSE NULL
                    END,
                    'new_department', CASE 
                        WHEN datri.new_department_id IS NOT NULL THEN
                            jsonb_build_object(
                                'id', noh.id,
                                'parent_node_id', noh.parent_node_id,
                                'level', noh.level,
                                'relationship', noh.relationship,
                                'data', noh.data,
                                'name', noh.data->>'organizationName'
                            )
                        ELSE NULL
                    END,
                    'new_location', CASE 
                        WHEN datri.new_location_latitude IS NOT NULL THEN
                            jsonb_build_object(
                                'latitude', datri.new_location_latitude,
                                'longitude', datri.new_location_longitude,
                                'address', datri.new_location_address
                            )
                        ELSE NULL
                    END,
                    'asset_owner_approval_status', datri.asset_owner_approval_status,
                    'asset_owner_note', datri.asset_owner_note,
                    'asset_owner_action_date', datri.asset_owner_action_date,
                    'target_person_approval_status', datri.target_person_approval_status,
                    'target_person_note', datri.target_person_note,
                    'target_person_action_date', datri.target_person_action_date,
                    'is_reset_current_employee_schedule', datri.is_reset_current_employee_schedule,
                    'is_reset_current_availability_schedule', datri.is_reset_current_availability_schedule,
                    'is_transferred', datri.is_transferred,
                    'transferred_at', datri.transferred_at,
                    'special_note', datri.special_note,
                    'created_at', datri.created_at,
                    'updated_at', datri.updated_at
                )
            ) INTO v_items_data
            FROM direct_asset_transfer_request_items datri
            LEFT JOIN asset_items ai ON ai.id = datri.asset_item_id
            LEFT JOIN assets a ON a.id = ai.asset_id
            LEFT JOIN asset_categories ac ON ac.id = a.category
            LEFT JOIN assets_types ast ON ac.assets_type = ast.id
            LEFT JOIN asset_sub_categories assc ON a.sub_category = assc.id
            LEFT JOIN currencies c ON c.id = ai.item_value_currency_id
            LEFT JOIN users cu ON cu.id = datri.current_owner_id
            LEFT JOIN designations cd ON cd.id = cu.designation_id
            LEFT JOIN organization coh ON coh.id = datri.current_department_id
            LEFT JOIN users nu ON nu.id = datri.new_owner_id
            LEFT JOIN designations nd ON nd.id = nu.designation_id
            LEFT JOIN organization noh ON noh.id = datri.new_department_id
            WHERE datri.direct_asset_transfer_request_id = p_transfer_request_id
                AND datri.deleted_at IS NULL;

            -- Combine all data including workflow details
            RETURN QUERY SELECT jsonb_build_object(
                'transfer_request', v_transfer_data,
                'workflow_details', v_workflow_data,
                'items', COALESCE(v_items_data, '[]'::JSONB)
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
        DB::unprepared(<<<'SQL'
        -- Restore previous version without workflow details
        DROP FUNCTION IF EXISTS get_single_direct_asset_transfer_request(BIGINT, BIGINT);

        CREATE OR REPLACE FUNCTION get_single_direct_asset_transfer_request(
            p_transfer_request_id BIGINT,
            p_tenant_id BIGINT
        ) RETURNS TABLE (
            transfer_request JSONB
        ) LANGUAGE plpgsql AS $$
        DECLARE
            v_transfer_data JSONB;
            v_items_data JSONB;
        BEGIN
            SELECT jsonb_build_object(
                'id', datr.id,
                'transfer_request_number', datr.transfer_request_number,
                'transfer_type', datr.transfer_type,
                'transfer_status', datr.transfer_status,
                'work_flow_request', datr.work_flow_request,
                'targeted_responsible_person', CASE 
                    WHEN datr.targeted_responsible_person IS NOT NULL THEN
                        jsonb_build_object(
                            'id', tp.id,
                            'name', tp.name,
                            'email', tp.email,
                            'phone', tp.contact_no,
                            'profile_image', tp.profile_image,
                            'designation', td.designation
                        )
                    ELSE NULL
                END,
                'transfer_reason', datr.transfer_reason,
                'special_note', datr.special_note,
                'is_cancelled', datr.is_cancelled,
                'reason_for_cancellation', datr.reason_for_cancellation,
                'is_received_by_target_person', datr.is_received_by_target_person,
                'received_by_target_person_at', datr.received_by_target_person_at,
                'requester', jsonb_build_object(
                    'id', u.id,
                    'name', u.name,
                    'email', u.email,
                    'phone', u.contact_no,
                    'profile_image', u.profile_image,
                    'designation', d.designation
                ),
                'requested_date', datr.requested_date,
                'created_at', datr.created_at,
                'updated_at', datr.updated_at
            ) INTO v_transfer_data
            FROM direct_asset_transfer_requests datr
            LEFT JOIN users u ON u.id = datr.requester_id
            LEFT JOIN designations d ON d.id = u.designation_id
            LEFT JOIN users tp ON tp.id = datr.targeted_responsible_person
            LEFT JOIN designations td ON td.id = tp.designation_id
            WHERE datr.id = p_transfer_request_id
                AND datr.tenant_id = p_tenant_id
                AND datr.deleted_at IS NULL;

            IF v_transfer_data IS NULL THEN
                RETURN QUERY SELECT NULL::JSONB;
                RETURN;
            END IF;

            SELECT jsonb_agg(
                jsonb_build_object(
                    'id', datri.id,
                    'asset_item', jsonb_build_object(
                        'id', ai.id,
                        'asset_tag', ai.asset_tag,
                        'serial_number', ai.serial_number,
                        'asset_name', a.name,
                        'category_name', ac.name,
                        'item_value', ai.item_value,
                        'currency_symbol', c.symbol,
                        'thumbnail_image', ai.thumbnail_image,
                        'qr_code', ai.qr_code
                    ),
                    'current_owner', jsonb_build_object(
                        'id', cu.id,
                        'name', cu.name,
                        'email', cu.email,
                        'designation', cd.designation,
                        'profile_image', cu.profile_image
                    ),
                    'current_department', jsonb_build_object(
                        'id', coh.id,
                        'name', coh.data->>'organizationName'
                    ),
                    'current_location', jsonb_build_object(
                        'latitude', datri.current_location_latitude,
                        'longitude', datri.current_location_longitude,
                        'address', datri.current_location_address
                    ),
                    'new_owner', CASE 
                        WHEN datri.new_owner_id IS NOT NULL THEN
                            jsonb_build_object(
                                'id', nu.id,
                                'name', nu.name,
                                'email', nu.email,
                                'designation', nd.designation,
                                'profile_image', nu.profile_image
                            )
                        ELSE NULL
                    END,
                    'new_department', CASE 
                        WHEN datri.new_department_id IS NOT NULL THEN
                            jsonb_build_object(
                                'id', noh.id,
                                'name', noh.data->>'organizationName'
                            )
                        ELSE NULL
                    END,
                    'new_location', CASE 
                        WHEN datri.new_location_latitude IS NOT NULL THEN
                            jsonb_build_object(
                                'latitude', datri.new_location_latitude,
                                'longitude', datri.new_location_longitude,
                                'address', datri.new_location_address
                            )
                        ELSE NULL
                    END,
                    'asset_owner_approval_status', datri.asset_owner_approval_status,
                    'asset_owner_note', datri.asset_owner_note,
                    'asset_owner_action_date', datri.asset_owner_action_date,
                    'target_person_approval_status', datri.target_person_approval_status,
                    'target_person_note', datri.target_person_note,
                    'target_person_action_date', datri.target_person_action_date,
                    'is_reset_current_employee_schedule', datri.is_reset_current_employee_schedule,
                    'is_reset_current_availability_schedule', datri.is_reset_current_availability_schedule,
                    'is_transferred', datri.is_transferred,
                    'transferred_at', datri.transferred_at,
                    'special_note', datri.special_note
                )
            ) INTO v_items_data
            FROM direct_asset_transfer_request_items datri
            LEFT JOIN asset_items ai ON ai.id = datri.asset_item_id
            LEFT JOIN assets a ON a.id = ai.asset_id
            LEFT JOIN asset_categories ac ON ac.id = a.category
            LEFT JOIN currencies c ON c.id = ai.item_value_currency_id
            LEFT JOIN users cu ON cu.id = datri.current_owner_id
            LEFT JOIN designations cd ON cd.id = cu.designation_id
            LEFT JOIN organization coh ON coh.id = datri.current_department_id
            LEFT JOIN users nu ON nu.id = datri.new_owner_id
            LEFT JOIN designations nd ON nd.id = nu.designation_id
            LEFT JOIN organization noh ON noh.id = datri.new_department_id
            WHERE datri.direct_asset_transfer_request_id = p_transfer_request_id
                AND datri.deleted_at IS NULL;

            RETURN QUERY SELECT jsonb_build_object(
                'transfer_request', v_transfer_data,
                'items', COALESCE(v_items_data, '[]'::JSONB)
            );
        END;
        $$;
        SQL);
    }
};