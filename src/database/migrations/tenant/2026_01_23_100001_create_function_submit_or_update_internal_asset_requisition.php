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
                WHERE proname = 'submit_or_update_internal_asset_requisition'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION submit_or_update_internal_asset_requisition(
            IN _requisition_by BIGINT,
            IN _requisition_status VARCHAR(255),
            IN _tenant_id BIGINT,
            IN _current_time TIMESTAMP WITH TIME ZONE,
            IN _requested_date TIMESTAMP WITH TIME ZONE,
            IN _items JSONB,
            IN _requisition_id VARCHAR(255) DEFAULT NULL,
            IN _targeted_responsible_person BIGINT DEFAULT NULL,
            IN _work_flow_request BIGINT DEFAULT NULL,
            IN _update_status_only BOOLEAN DEFAULT FALSE
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            old_requisition JSONB,
            new_requisition JSONB,
            requisition_items JSONB
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            curr_val INT;
            v_internal_asset_requisition_id BIGINT;
            internal_requisition_register_id TEXT;
            item JSONB;
            old_data JSONB;
            new_data JSONB;
            items_data JSONB := '[]'::JSONB;
            existing_item_ids BIGINT[];
            provided_item_ids BIGINT[];
            v_item_id BIGINT;
        BEGIN
            ----------------------------------------------------------------
            -- Validations
            ----------------------------------------------------------------
            IF _tenant_id IS NULL OR _tenant_id <= 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT,
                    'Invalid tenant ID provided'::TEXT,
                    NULL::JSONB,
                    NULL::JSONB,
                    '[]'::JSONB;
                RETURN;
            END IF;

            IF _requisition_by IS NULL OR _requisition_by <= 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT,
                    'Invalid requisition_by user ID provided'::TEXT,
                    NULL::JSONB,
                    NULL::JSONB,
                    '[]'::JSONB;
                RETURN;
            END IF;

            ----------------------------------------------------------------
            -- Check if updating existing requisition
            ----------------------------------------------------------------
            IF _requisition_id IS NOT NULL THEN
                SELECT id, to_jsonb(internal_asset_requisitions.*) 
                INTO v_internal_asset_requisition_id, old_data
                FROM internal_asset_requisitions 
                WHERE requisition_id = _requisition_id
                AND deleted_at IS NULL;

                IF NOT FOUND THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Internal asset requisition not found'::TEXT,
                        NULL::JSONB,
                        NULL::JSONB,
                        '[]'::JSONB;
                    RETURN;
                END IF;

                ----------------------------------------------------------------
                -- Update only status if requested
                ----------------------------------------------------------------
                IF _update_status_only = TRUE THEN
                    UPDATE internal_asset_requisitions 
                    SET requisition_status = _requisition_status,
                        updated_at = _current_time
                    WHERE id = v_internal_asset_requisition_id
                    RETURNING to_jsonb(internal_asset_requisitions.*) INTO new_data;

                    -- Fetch existing items
                    SELECT COALESCE(
                        jsonb_agg(
                            jsonb_build_object(
                                'item_id', iari.id,
                                'item_selection_type_id', iari.internal_asset_requisitions_item_selection_types_id,
                                'item_selection_type_name', iarst.name,
                                'asset_item_id', iari.asset_item_id,
                                'asset_item_name', a.name,
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
                                'related_documents', iari.related_documents
                            )
                        ) FILTER (WHERE iari.id IS NOT NULL),
                        '[]'::JSONB
                    ) INTO items_data
                    FROM internal_asset_requisitions_items iari
                    LEFT JOIN internal_asset_requisitions_item_selection_types iarst 
                        ON iari.internal_asset_requisitions_item_selection_types_id = iarst.id
                    LEFT JOIN asset_items ai ON iari.asset_item_id = ai.id
                    LEFT JOIN assets a ON ai.asset_id = a.id
                    LEFT JOIN asset_requisition_priority_types arpt ON iari.priority = arpt.id
                    LEFT JOIN organization org ON iari.department = org.id
                    WHERE iari.internal_asset_requisition_id = v_internal_asset_requisition_id
                    AND iari.deleted_at IS NULL;

                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT,
                        'Internal asset requisition status updated successfully'::TEXT,
                        old_data,
                        new_data,
                        items_data;
                    RETURN;
                END IF;

                ----------------------------------------------------------------
                -- Full update of requisition and items
                ----------------------------------------------------------------
                UPDATE internal_asset_requisitions 
                SET targeted_responsible_person = _targeted_responsible_person,
                    requested_date = _requested_date,
                    requisition_status = _requisition_status,
                    work_flow_request = _work_flow_request,
                    updated_at = _current_time
                WHERE id = v_internal_asset_requisition_id
                RETURNING to_jsonb(internal_asset_requisitions.*) INTO new_data;

                -- First, soft delete all existing items for this requisition
                UPDATE internal_asset_requisitions_items
                SET deleted_at = _current_time,
                    isactive = false,
                    updated_at = _current_time
                WHERE internal_asset_requisition_id = v_internal_asset_requisition_id
                AND deleted_at IS NULL;

                -- Then, insert all current items as new records
                FOR item IN SELECT * FROM jsonb_array_elements(_items) LOOP
                    INSERT INTO internal_asset_requisitions_items (
                        internal_asset_requisition_id,
                        internal_asset_requisitions_item_selection_types_id,
                        asset_item_id,
                        item_name,
                        required_quantity,
                        required_date,
                        priority,
                        department,
                        required_location_latitude,
                        required_location_longitude,
                        reason_for_requirement,
                        additional_notes,
                        other_details,
                        related_documents,
                        tenant_id,
                        created_at,
                        updated_at
                    ) VALUES (
                        v_internal_asset_requisition_id,
                        NULLIF(item->>'item_selection_type_id', '')::BIGINT,
                        NULLIF(item->>'asset_item_id', '')::BIGINT,
                        item->>'item_name',
                        NULLIF(item->>'required_quantity', '')::DECIMAL,
                        NULLIF(item->>'required_date', '')::DATE,
                        NULLIF(item->>'priority', '')::BIGINT,
                        NULLIF(item->>'department', '')::BIGINT,
                        item->>'required_location_latitude',
                        item->>'required_location_longitude',
                        item->>'reason_for_requirement',
                        item->>'additional_notes',
                        NULLIF(item->>'other_details', '')::JSONB,
                        NULLIF(item->>'related_documents', '')::JSONB,
                        _tenant_id,
                        _current_time,
                        _current_time
                    );
                END LOOP;

                -- Soft delete items that are not in the provided items array (This is now redundant but kept for consistency)
                -- All items were already deleted above, so this won't do anything
                -- UPDATE internal_asset_requisitions_items
                -- SET deleted_at = _current_time,
                --     updated_at = _current_time
                -- WHERE internal_asset_requisition_id = v_internal_asset_requisition_id
                -- AND deleted_at IS NULL
                -- AND (provided_item_ids IS NULL OR id != ALL(provided_item_ids));

            ELSE
                ----------------------------------------------------------------
                -- Create new requisition
                ----------------------------------------------------------------
                -- Generate requisition ID
                SELECT nextval('internal_asset_requisition_id_seq') INTO curr_val;
                internal_requisition_register_id := 'INTREQU-' || LPAD(curr_val::TEXT, 6, '0');

                -- Insert new internal asset requisition
                INSERT INTO internal_asset_requisitions (
                    requisition_id,
                    targeted_responsible_person,
                    requisition_by,
                    requested_date,
                    requisition_status,
                    work_flow_request,
                    tenant_id,
                    created_at,
                    updated_at
                ) VALUES (
                    internal_requisition_register_id,
                    _targeted_responsible_person,
                    _requisition_by,
                    _requested_date,
                    _requisition_status,
                    _work_flow_request,
                    _tenant_id,
                    _current_time,
                    _current_time
                )
                RETURNING id, to_jsonb(internal_asset_requisitions.*) 
                INTO v_internal_asset_requisition_id, new_data;

                -- Insert each item
                FOR item IN SELECT * FROM jsonb_array_elements(_items) LOOP
                    INSERT INTO internal_asset_requisitions_items (
                        internal_asset_requisition_id,
                        internal_asset_requisitions_item_selection_types_id,
                        asset_item_id,
                        item_name,
                        required_quantity,
                        required_date,
                        priority,
                        department,
                        required_location_latitude,
                        required_location_longitude,
                        reason_for_requirement,
                        additional_notes,
                        other_details,
                        related_documents,
                        tenant_id,
                        created_at,
                        updated_at
                    ) VALUES (
                        v_internal_asset_requisition_id,
                        NULLIF(item->>'item_selection_type_id', '')::BIGINT,
                        NULLIF(item->>'asset_item_id', '')::BIGINT,
                        item->>'item_name',
                        NULLIF(item->>'required_quantity', '')::DECIMAL,
                        NULLIF(item->>'required_date', '')::DATE,
                        NULLIF(item->>'priority', '')::BIGINT,
                        NULLIF(item->>'department', '')::BIGINT,
                        item->>'required_location_latitude',
                        item->>'required_location_longitude',
                        item->>'reason_for_requirement',
                        item->>'additional_notes',
                        NULLIF(item->>'other_details', '')::JSONB,
                        NULLIF(item->>'related_documents', '')::JSONB,
                        _tenant_id,
                        _current_time,
                        _current_time
                    );
                END LOOP;
            END IF;

            ----------------------------------------------------------------
            -- Fetch all related items with joins
            ----------------------------------------------------------------
            SELECT COALESCE(
                jsonb_agg(
                    jsonb_build_object(
                        'item_id', iari.id,
                        'item_selection_type_id', iari.internal_asset_requisitions_item_selection_types_id,
                        'item_selection_type_name', iarst.name,
                        'asset_item_id', iari.asset_item_id,
                        'asset_item_name', a.name,
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
                        'related_documents', iari.related_documents
                    )
                ) FILTER (WHERE iari.id IS NOT NULL),
                '[]'::JSONB
            ) INTO items_data
            FROM internal_asset_requisitions_items iari
            LEFT JOIN internal_asset_requisitions_item_selection_types iarst 
                ON iari.internal_asset_requisitions_item_selection_types_id = iarst.id
            LEFT JOIN asset_items ai ON iari.asset_item_id = ai.id
            LEFT JOIN assets a ON ai.asset_id = a.id
            LEFT JOIN asset_requisition_priority_types arpt ON iari.priority = arpt.id
            LEFT JOIN organization org ON iari.department = org.id
            WHERE iari.internal_asset_requisition_id = v_internal_asset_requisition_id
            AND iari.deleted_at IS NULL;

            ----------------------------------------------------------------
            -- Return final result
            ----------------------------------------------------------------
            RETURN QUERY SELECT 
                'SUCCESS'::TEXT,
                CASE 
                    WHEN old_data IS NOT NULL THEN 'Internal asset requisition updated successfully'
                    ELSE 'Internal asset requisition created successfully'
                END::TEXT,
                old_data,
                new_data,
                items_data;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS submit_or_update_internal_asset_requisition CASCADE;');
    }
};