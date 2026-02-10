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
                WHERE proname = 'get_auth_user_transfer_items'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_auth_user_transfer_items(
            IN _user_id BIGINT,
            IN _tenant_id BIGINT,
            IN _user_type VARCHAR(50) DEFAULT 'requisition_by'
        )
        RETURNS TABLE (
            transfer_items JSONB
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_items JSONB;
        BEGIN
            -- Validate inputs
            IF _user_id IS NULL OR _user_id <= 0 THEN
                RETURN QUERY SELECT '[]'::JSONB;
                RETURN;
            END IF;

            IF _tenant_id IS NULL OR _tenant_id <= 0 THEN
                RETURN QUERY SELECT '[]'::JSONB;
                RETURN;
            END IF;

            -- Get all transfer items for user's internal requisitions
            SELECT COALESCE(
                jsonb_agg(
                    jsonb_build_object(
                        'transfer_item_id', rbatri.id,
                        'transfer_request_id', rbat.id,
                        'transfer_requisition_id', rbat.requisition_id,
                        'transfer_status', rbat.requisition_status,
                        'is_cancelled', rbat.is_cancelled,
                        'requested_date', rbat.requested_date,
                        'is_gatepass_required', rbat.is_gatepass_required,
                        'asset_item_id', rbatri.asset_item_id,
                        'is_reset_current_employee_schedule', rbatri.is_reset_current_employee_schedule,
                        'is_reset_current_availability_schedule', rbatri.is_reset_current_availability_schedule,
                        'special_note', rbatri.special_note,
                        'asset_requester_approval_status', rbatri.asset_requester_approval_status,
                        'asset_requester_note', rbatri.asset_requester_note,
                        'asset_requester_action_date', rbatri.asset_requester_action_date,
                        'created_at', rbatri.created_at,
                        'updated_at', rbatri.updated_at,
                        'asset_item', jsonb_build_object(
                            'id', ai.id,
                            'model_number', ai.model_number,
                            'serial_number', ai.serial_number,
                            'qr_code', ai.qr_code,
                            'item_value', ai.item_value,
                            'responsible_person', ai.responsible_person,
                            'asset_location_latitude', ai.asset_location_latitude,
                            'asset_location_longitude', ai.asset_location_longitude,
                            'asset', jsonb_build_object(
                                'id', a.id,
                                'name', a.name,
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
                            'item_name', iari.item_name,
                            'required_quantity', iari.required_quantity,
                            'fulfilled_quantity', iari.fulfilled_quantity,
                            'required_date', iari.required_date,
                            'reason_for_requirement', iari.reason_for_requirement,
                            'priority', iari.priority,
                            'internal_requisition_id', iar.requisition_id
                        ),
                        'internal_requisition', jsonb_build_object(
                            'id', iar.id,
                            'requisition_id', iar.requisition_id,
                            'requisition_status', iar.requisition_status,
                            'requested_date', iar.requested_date,
                            'requisition_by', iar.requisition_by,
                            'targeted_responsible_person', iar.targeted_responsible_person
                        ),
                        'transfer_request_by', jsonb_build_object(
                            'id', u.id,
                            'user_name', u.user_name,
                            'email', u.email,
                            'profile_image', u.profile_image,
                            'designation_id', u.designation_id,
                            'designation_name', d.designation
                        )
                    )
                    ORDER BY rbatri.created_at DESC
                ),
                '[]'::JSONB
            ) INTO v_items
            FROM internal_asset_requisitions iar
            LEFT JOIN internal_asset_requisitions_items iari ON iari.internal_asset_requisition_id = iar.id
                AND iari.deleted_at IS NULL
            LEFT JOIN requisition_based_asset_transfer_requwest_items rbatri 
                ON rbatri.based_internal_asset_requisitions_items = iari.id
                AND rbatri.deleted_at IS NULL
            LEFT JOIN requisition_based_asset_transfer_requwest rbat 
                ON rbatri.requisition_based_asset_transfer_requwest = rbat.id
                AND rbat.deleted_at IS NULL
            LEFT JOIN asset_items ai ON rbatri.asset_item_id = ai.id
            LEFT JOIN assets a ON ai.asset_id = a.id
            LEFT JOIN asset_categories ac ON a.category = ac.id
            LEFT JOIN assets_types ast ON ac.assets_type = ast.id
            LEFT JOIN asset_sub_categories assc ON a.sub_category = assc.id
            LEFT JOIN users u ON rbat.requisition_by = u.id
            LEFT JOIN designations d ON u.designation_id = d.id
            WHERE iar.tenant_id = _tenant_id
            AND iar.deleted_at IS NULL
            AND (
                (_user_type = 'requisition_by' AND iar.requisition_by = _user_id) OR
                (_user_type = 'targeted' AND iar.targeted_responsible_person = _user_id) OR
                (_user_type = 'all' AND (iar.requisition_by = _user_id OR iar.targeted_responsible_person = _user_id))
            )
            AND rbatri.id IS NOT NULL;
            
            RETURN QUERY SELECT v_items;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_auth_user_transfer_items CASCADE;');
    }
};