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
        // Create function to get all requisition based asset transfers with pagination and filters
        DB::unprepared(<<<'SQL'
        DO $$
        DECLARE
            r RECORD;
        BEGIN 
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'get_all_requisition_based_asset_transfers'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_all_requisition_based_asset_transfers(
            IN _tenant_id BIGINT,
            IN _requisition_by BIGINT DEFAULT NULL,
            IN _based_asset_requisition BIGINT DEFAULT NULL,
            IN _requisition_status VARCHAR(255) DEFAULT NULL,
            IN _page_no INT DEFAULT 1,
            IN _page_size INT DEFAULT 10,
            IN _search TEXT DEFAULT NULL,
            IN _sort_by VARCHAR(50) DEFAULT 'newest'
        )
        RETURNS TABLE (
            id BIGINT,
            requisition_id VARCHAR(255),
            based_asset_requisition BIGINT,
            requisition_by BIGINT,
            requested_date TIMESTAMP,
            requisition_status VARCHAR(255),
            special_note TEXT,
            is_gatepass_required BOOLEAN,
            is_cancelled BOOLEAN,
            reason_for_cancellation TEXT,
            created_at TIMESTAMP,
            updated_at TIMESTAMP,
            requisition_by_name VARCHAR(255),
            requisition_by_email VARCHAR(255),
            internal_requisition_id VARCHAR(255),
            total_items BIGINT,
            total_records BIGINT
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_offset INT;
            v_total_records BIGINT;
        BEGIN
            -- Calculate offset
            v_offset := (_page_no - 1) * _page_size;
            
            -- Get total count first
            SELECT COUNT(DISTINCT rbat.id) INTO v_total_records
            FROM requisition_based_asset_transfer_requwest rbat
            LEFT JOIN users u ON rbat.requisition_by = u.id
            LEFT JOIN internal_asset_requisitions iar ON rbat.based_asset_requisition = iar.id
            WHERE rbat.tenant_id = _tenant_id
            AND rbat.deleted_at IS NULL
            AND rbat.isactive = TRUE
            AND (_requisition_by IS NULL OR rbat.requisition_by = _requisition_by)
            AND (_based_asset_requisition IS NULL OR rbat.based_asset_requisition = _based_asset_requisition)
            AND (_requisition_status IS NULL OR rbat.requisition_status = _requisition_status)
            AND (
                _search IS NULL OR _search = '' OR
                rbat.requisition_id ILIKE '%' || _search || '%' OR
                u.user_name ILIKE '%' || _search || '%' OR
                iar.requisition_id ILIKE '%' || _search || '%' OR
                rbat.special_note ILIKE '%' || _search || '%'
            );
            
            -- Return paginated results with total count
            RETURN QUERY
            SELECT 
                rbat.id,
                rbat.requisition_id,
                rbat.based_asset_requisition,
                rbat.requisition_by,
                rbat.requested_date,
                rbat.requisition_status,
                rbat.special_note,
                rbat.is_gatepass_required,
                rbat.is_cancelled,
                rbat.reason_for_cancellation,
                rbat.created_at,
                rbat.updated_at,
                u.user_name as requisition_by_name,
                u.email as requisition_by_email,
                iar.requisition_id as internal_requisition_id,
                COUNT(rbati.id) as total_items,
                v_total_records as total_records
            FROM requisition_based_asset_transfer_requwest rbat
            LEFT JOIN users u ON rbat.requisition_by = u.id
            LEFT JOIN internal_asset_requisitions iar ON rbat.based_asset_requisition = iar.id
            LEFT JOIN requisition_based_asset_transfer_requwest_items rbati 
                ON rbat.id = rbati.requisition_based_asset_transfer_requwest 
                AND rbati.deleted_at IS NULL
            WHERE rbat.tenant_id = _tenant_id
            AND rbat.deleted_at IS NULL
            AND rbat.isactive = TRUE
            AND (_requisition_by IS NULL OR rbat.requisition_by = _requisition_by)
            AND (_based_asset_requisition IS NULL OR rbat.based_asset_requisition = _based_asset_requisition)
            AND (_requisition_status IS NULL OR rbat.requisition_status = _requisition_status)
            AND (
                _search IS NULL OR _search = '' OR
                rbat.requisition_id ILIKE '%' || _search || '%' OR
                u.user_name ILIKE '%' || _search || '%' OR
                iar.requisition_id ILIKE '%' || _search || '%' OR
                rbat.special_note ILIKE '%' || _search || '%'
            )
            GROUP BY rbat.id, u.user_name, u.email, iar.requisition_id
            ORDER BY 
                CASE WHEN _sort_by = 'oldest' THEN rbat.created_at END ASC,
                CASE WHEN _sort_by = 'status' THEN rbat.requisition_status END ASC,
                CASE WHEN _sort_by = 'newest' THEN rbat.created_at END DESC
            OFFSET v_offset
            LIMIT _page_size;
        END;
        $$;
        SQL);

        // Create function to get single requisition based asset transfer with items
        DB::unprepared(<<<'SQL'
        DO $$
        DECLARE
            r RECORD;
        BEGIN 
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'get_single_requisition_based_asset_transfer'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_single_requisition_based_asset_transfer(
            IN _requisition_id BIGINT,
            IN _tenant_id BIGINT
        )
        RETURNS TABLE (
            requisition JSONB,
            items JSONB
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_requisition JSONB;
            v_items JSONB;
        BEGIN
            -- Get requisition details
            SELECT to_jsonb(sub.*) INTO v_requisition
            FROM (
                SELECT 
                    rbat.*,
                    jsonb_build_object(
                        'id', u.id,
                        'user_name', u.user_name,
                        'email', u.email,
                        'name', u.name,
                        'contact_no', u.contact_no,
                        'profile_image', u.profile_image
                    ) as requisition_by_user,
                    iar.requisition_id as internal_requisition_id,
                    iar.requisition_status as internal_requisition_status
                FROM requisition_based_asset_transfer_requwest rbat
                LEFT JOIN users u ON rbat.requisition_by = u.id
                LEFT JOIN internal_asset_requisitions iar ON rbat.based_asset_requisition = iar.id
                WHERE rbat.id = _requisition_id
                AND rbat.tenant_id = _tenant_id
                AND rbat.deleted_at IS NULL
            ) sub;
            
            IF v_requisition IS NULL THEN
                RETURN QUERY SELECT NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;
            
            -- Get items with asset and requisition details
            SELECT COALESCE(
                jsonb_agg(
                    jsonb_build_object(
                        'id', rbati.id,
                        'asset_item_id', rbati.asset_item_id,
                        'based_internal_asset_requisitions_items', rbati.based_internal_asset_requisitions_items,
                        'is_reset_current_employee_schedule', rbati.is_reset_current_employee_schedule,
                        'is_reset_current_availability_schedule', rbati.is_reset_current_availability_schedule,
                        'special_note', rbati.special_note,
                        'asset_requester_approval_status', rbati.asset_requester_approval_status,
                        'asset_requester_note', rbati.asset_requester_note,
                        'asset_requester_action_date', rbati.asset_requester_action_date,
                        'created_at', rbati.created_at,
                        'updated_at', rbati.updated_at,
                        'asset_item', jsonb_build_object(
                            'id', ai.id,
                            'model_number', ai.model_number,
                            'serial_number', ai.serial_number,
                            'thumbnail_image', ai.thumbnail_image,
                            'qr_code', ai.qr_code,
                            'item_value', ai.item_value,
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
                        'requisition_item', jsonb_build_object(
                            'id', iari.id,
                            'internal_asset_requisition_id', iari.internal_asset_requisition_id,
                            'internal_asset_requisitions_item_selection_types_id', iari.internal_asset_requisitions_item_selection_types_id,
                            'selection_type', CASE 
                                WHEN iarist.id IS NOT NULL THEN jsonb_build_object(
                                    'id', iarist.id,
                                    'name', iarist.name
                                )
                                ELSE NULL
                            END,
                            'asset_item_id', iari.asset_item_id,
                            'item_name', iari.item_name,
                            'required_quantity', iari.required_quantity,
                            'fulfilled_quantity', iari.fulfilled_quantity,
                            'required_date', iari.required_date,
                            'priority', iari.priority,
                            'priority_details', CASE 
                                WHEN arpt.id IS NOT NULL THEN jsonb_build_object(
                                    'id', arpt.id,
                                    'name', arpt.name
                                )
                                ELSE NULL
                            END,
                            'department', iari.department,
                            'department_details', CASE 
                                WHEN org.id IS NOT NULL THEN jsonb_build_object(
                                    'id', org.id,
                                    'parent_node_id', org.parent_node_id,
                                    'level', org.level,
                                    'relationship', org.relationship,
                                    'data', org.data
                                )
                                ELSE NULL
                            END,
                            'required_location_latitude', iari.required_location_latitude,
                            'required_location_longitude', iari.required_location_longitude,
                            'reason_for_requirement', iari.reason_for_requirement,
                            'additional_notes', iari.additional_notes,
                            'other_details', iari.other_details,
                            'related_documents', iari.related_documents
                        )
                    )
                ),
                '[]'::JSONB
            ) INTO v_items
            FROM requisition_based_asset_transfer_requwest_items rbati
            LEFT JOIN asset_items ai ON rbati.asset_item_id = ai.id
            LEFT JOIN assets a ON ai.asset_id = a.id
            LEFT JOIN asset_categories ac ON a.category = ac.id
            LEFT JOIN assets_types ast ON ac.assets_type = ast.id
            LEFT JOIN asset_sub_categories assc ON a.sub_category = assc.id
            LEFT JOIN internal_asset_requisitions_items iari ON rbati.based_internal_asset_requisitions_items = iari.id
            LEFT JOIN internal_asset_requisitions_item_selection_types iarist ON iari.internal_asset_requisitions_item_selection_types_id = iarist.id
            LEFT JOIN asset_requisition_priority_types arpt ON iari.priority = arpt.id
            LEFT JOIN organization org ON iari.department = org.id
            WHERE rbati.requisition_based_asset_transfer_requwest = _requisition_id
            AND rbati.deleted_at IS NULL;
            
            RETURN QUERY SELECT v_requisition, v_items;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_all_requisition_based_asset_transfers CASCADE;');
        DB::unprepared('DROP FUNCTION IF EXISTS get_single_requisition_based_asset_transfer CASCADE;');
    }
};