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
        DB::unprepared(<<<SQL

            DROP FUNCTION IF EXISTS update_asset_requisition_status;

            CREATE OR REPLACE FUNCTION update_asset_requisition_status(
                IN _requisition_id BIGINT,
                IN _requisition_status VARCHAR(255),
                IN _current_time TIMESTAMP WITH TIME ZONE
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                requisition_data JSONB,
                requisition_items JSONB
            )
            LANGUAGE plpgsql
            AS $$
            
            DECLARE
                v_asset_requisition JSONB;
                v_asset_requisition_items JSONB;
                v_rows_updated INT;
            BEGIN
                -- Fetch asset requisition before update
                SELECT jsonb_build_object(
                    'id', id,
                    'requisition_id', requisition_id,
                    'requisition_status', requisition_status,
                    'requisition_by', requisition_by,
                    'requisition_date', requisition_date,
                    'tenant_id', tenant_id,
                    'created_at', created_at,
                    'updated_at', updated_at
                ) INTO v_asset_requisition
                FROM asset_requisitions
                WHERE id = _requisition_id;

                -- If the requisition is not found, return failure
                IF v_asset_requisition IS NULL THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Asset requisition not found'::TEXT AS message,
                        NULL::JSONB AS requisition_data,
                        NULL::JSONB AS requisition_items;
                    RETURN;
                END IF;

                -- Update requisition status
                UPDATE asset_requisitions 
                SET requisition_status = _requisition_status, updated_at = _current_time
                WHERE id = _requisition_id;
                
                -- Capture the number of rows updated
                GET DIAGNOSTICS v_rows_updated = ROW_COUNT;

                -- Fetch the updated requisition details
                SELECT jsonb_build_object(
                    'id', id,
                    'requisition_id', requisition_id,
                    'requisition_status', requisition_status,
                    'requisition_by', requisition_by,
                    'requisition_date', requisition_date,
                    'tenant_id', tenant_id,
                    'created_at', created_at,
                    'updated_at', updated_at
                ) INTO v_asset_requisition
                FROM asset_requisitions
                WHERE id = _requisition_id;

                -- Fetch the associated asset requisition items as JSONB
                SELECT COALESCE(
                    jsonb_agg(
                        jsonb_build_object(
                            'item_id', ari.id,
                            'item_name', ari.item_name,
                            'asset_item_id', ari.asset_item_id,
                            'quantity', ari.quantity,
                            'budget', ari.budget,
                            'budget_currency', ari.budget_currency,
                            'business_purpose', ari.business_purpose,
                            'acquisition_type', ari.acquisition_type,
                            'asset_acquisition_type', aratyp.name,
                            'period_status', arpt.id,
                            'period_status_name', arpt.name,
                            'period_from', ari.period_from,
                            'period_to', ari.period_to,
                            'period', ari.period,
                            'availability_type', arat.id,
                            'availability_type_name', arat.name,
                            'priority', arpst.id,
                            'priority_name', arpst.name,
                            'required_date', ari.required_date,
                            'organization', org.id,
                            'organization_data', org.data,
                            'reason', ari.reason,
                            'business_impact', ari.business_impact,
                            'expected_conditions', ari.expected_conditions,
                            'suppliers', ari.suppliers,
                            'files', ari.files,
                            'item_details', ari.item_details,
                            'maintenance_kpi', ari.maintenance_kpi,
                            'service_support_kpi', ari.service_support_kpi,
                            'consumables_kpi', ari.consumables_kpi,
                            'description', ari.description,
                            'asset_category', ari.asset_category,
                            'asset_sub_category', ari.asset_sub_category,
                            'expected_depreciation_value', ari.expected_depreciation_value,
                            'item_actual_id', ai.id,
                            'item_serial_number', ai.serial_number,
                            'item_thumbnail_image', ai.thumbnail_image,
                            'item_asset_tag', ai.asset_tag,
                            'item_qr_code', ai.qr_code,
                            'item_value', ai.item_value,
                            'tenant_id', ari.tenant_id,
                            'created_at', ari.created_at,
                            'updated_at', ari.updated_at
                        )
                    ) FILTER (WHERE ari.id IS NOT NULL), '[]'::jsonb
                ) INTO v_asset_requisition_items
                
                FROM asset_requisitions_items ari
                LEFT JOIN asset_items ai ON ari.asset_item_id = ai.id
                LEFT JOIN assets a ON ai.asset_id = a.id
                LEFT JOIN asset_requisition_period_types arpt ON ari.period_status = arpt.id
                LEFT JOIN asset_requisition_availability_types arat ON ari.availability_type = arat.id
                LEFT JOIN asset_requisition_priority_types arpst ON ari.priority = arpst.id
                LEFT JOIN organization org ON ari.organization = org.id
                LEFT JOIN asset_requisition_acquisition_types aratyp ON ari.acquisition_type = aratyp.id
                WHERE ari.asset_requisition_id = _requisition_id
                AND (ari.deleted_at IS NULL OR ari.id IS NULL);

                -- If no rows were updated, return failure
                IF v_rows_updated = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No rows updated'::TEXT AS message,
                        v_asset_requisition,
                        v_asset_requisition_items;
                ELSE
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status, 
                        'Asset requisition status updated successfully'::TEXT AS message,
                        v_asset_requisition,
                        v_asset_requisition_items;
                END IF;
            END;
    
            $$;

        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS update_asset_requisition_status CASCADE');
    }
};