<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_all_user_asset_requisitions(
                    p_tenant_id BIGINT,
                    p_user_id INT DEFAULT NULL
                )
                RETURNS TABLE (
                    status TEXT,
                    message TEXT,
                    asset_requisitions_id BIGINT,
                    requisition_id TEXT,
                    requisition_by BIGINT,
                    requisition_date TIMESTAMP,
                    requisition_status TEXT,
                    items JSON
                )
                LANGUAGE plpgsql
                AS $$
                BEGIN
            -- Validate tenant ID
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT AS status,
                    'Invalid tenant ID provided'::TEXT AS message,
                    NULL::BIGINT AS asset_requisitions_id,
                    NULL::TEXT AS requisition_id,
                    NULL::BIGINT AS requisition_by,
                    NULL::TIMESTAMP AS requisition_date,
                    NULL::TEXT AS requisition_status,
                    NULL::JSON AS items;
                RETURN;
            END IF;

            -- Validate user ID
            IF p_user_id IS NOT NULL AND p_user_id < 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT AS status,
                    'Invalid p_user_id provided'::TEXT AS message,
                    NULL::BIGINT AS asset_requisitions_id,
                    NULL::TEXT AS requisition_id,
                    NULL::BIGINT AS requisition_by,
                    NULL::TIMESTAMP AS requisition_date,
                    NULL::TEXT AS requisition_status,
                    NULL::JSON AS items;
                RETURN;
            END IF;

            -- Check if any matching records exist
            IF NOT EXISTS (
                SELECT 1 
                FROM asset_requisitions ar
                WHERE ar.tenant_id = p_tenant_id 
                AND (p_user_id IS NULL OR ar.requisition_by = p_user_id)
                AND ar.deleted_at IS NULL 
                AND ar.isactive = TRUE
            ) THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT AS status,
                    'No matching asset requisitions found'::TEXT AS message,
                    NULL::BIGINT AS asset_requisitions_id,
                    NULL::TEXT AS requisition_id,
                    NULL::BIGINT AS requisition_by,
                    NULL::TIMESTAMP AS requisition_date,
                    NULL::TEXT AS requisition_status,
                    NULL::JSON AS items;
                RETURN;
            END IF;

            -- Return the matching records
            RETURN QUERY
            SELECT
                'SUCCESS'::TEXT AS status,
                'Asset requisitions fetched successfully'::TEXT AS message,
                ar.id AS asset_requisitions_id,
                ar.requisition_id::TEXT,
                ar.requisition_by,
                ar.requisition_date,
                ar.requisition_status::TEXT,
                COALESCE(
                    json_agg(
                        json_build_object(
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
                            'asset_category', a.category,
                            'asset_sub_category', a.sub_category,
                            'expected_depreciation_value', ari.expected_depreciation_value,
                            'item_actual_id', ai.id,
                            'item_serial_number', ai.serial_number,
                            'item_thumbnail_image', ai.thumbnail_image,
                            'item_asset_tag', ai.asset_tag,
                            'item_qr_code', ai.qr_code,
                            'item_value', ai.item_value
                        )
                    ) FILTER (WHERE ari.id IS NOT NULL), '[]'
                ) AS items
            FROM
                asset_requisitions ar
            LEFT JOIN
                asset_requisitions_items ari ON ar.id = ari.asset_requisition_id
            LEFT JOIN
                asset_items ai ON ari.asset_item_id = ai.id
            LEFT JOIN
                assets a ON ai.asset_id = a.id
            LEFT JOIN
                asset_requisition_period_types arpt ON ari.period_status = arpt.id
            LEFT JOIN
                asset_requisition_availability_types arat ON ari.availability_type = arat.id
            LEFT JOIN
                asset_requisition_priority_types arpst ON ari.priority = arpst.id
            LEFT JOIN
                organization org ON ari.organization = org.id
            LEFT JOIN
                asset_requisition_acquisition_types aratyp ON ari.acquisition_type = aratyp.id
            WHERE
                ar.tenant_id = p_tenant_id
                AND (p_user_id IS NULL OR ar.requisition_by = p_user_id)
                AND ar.deleted_at IS NULL
                AND ar.isactive = TRUE
            GROUP BY ar.id;

        END;
        $$;
    SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_all_user_asset_requisitions');
    }
};
