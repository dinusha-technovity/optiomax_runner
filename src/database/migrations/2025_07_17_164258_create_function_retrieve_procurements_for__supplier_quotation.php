<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("
            CREATE OR REPLACE FUNCTION get_procurements_details_for_supplier_quotation(
                p_tenant_id BIGINT,
                p_procurement_id INT DEFAULT 0,
                p_request_id TEXT DEFAULT NULL,
                p_attempt_id BIGINT DEFAULT 0,
                p_supplier_id BIGINT DEFAULT 0
            )
            RETURNS TABLE (
                id BIGINT,
                request_id TEXT,
                created_by BIGINT,
                procurement_status TEXT,
                created_at TIMESTAMP,
                updated_at TIMESTAMP,
                quotation_request_attempts JSONB
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RETURN QUERY
                SELECT 
                    p.id,
                    p.request_id::TEXT,
                    p.created_by,
                    p.procurement_status::TEXT,
                    p.created_at,
                    p.updated_at,

                    (
                        SELECT COALESCE(jsonb_agg(
                            jsonb_build_object(
                                'id', qa.id,
                                'procurement_id', qa.procurement_id,
                                'attempted_by', qa.attempted_by,
                                'attempted_by_name', u.name,
                                'closing_date', qa.closing_date,
                                'request_attempts_status', qa.request_attempts_status,
                                'created_at', qa.created_at,
                                'updated_at', qa.updated_at,
                                'items', (
                                    SELECT COALESCE(jsonb_agg(
                                        jsonb_build_object(
                                            'id', i.id,
                                            'supplier_id', i.supplier_id,
                                            'item_id', i.item_id,
                                            'expected_budget_per_item', i.expected_budget_per_item,
                                            'requested_quantity', i.requested_quantity,
                                            'rfp_document', i.rfp_document,
                                            'attachment', i.attachment,
                                            'required_date', i.required_date,
                                            'message_to_supplier', i.message_to_supplier,
                                            'created_at', i.created_at,
                                            'updated_at', i.updated_at,

                                            -- From asset_requisitions_items
                                            'item_name', ari.item_name,
                                            'expected_conditions', ari.expected_conditions,
                                            'files', ari.files,
                                            'item_details', ari.item_details,
                                            'period_from', ari.period_from,
                                            'period_to', ari.period_to,
                                            'period', ari.period,

                                            -- Organization info
                                            'organization_details', jsonb_build_object(
                                                'id', o.id,
                                                'organizationName', o.data->>'organizationName',
                                                'email', o.data->>'email',
                                                'address', o.data->>'address',
                                                'website', o.data->>'website',
                                                'telephoneNumber', o.data->>'telephoneNumber',
                                                'organizationDescription', o.data->>'organizationDescription'
                                            ),

                                            -- Availability type info
                                            'availability_type', jsonb_build_object(
                                                'id', arat.id,
                                                'name', arat.name
                                            ),

                                            -- Period status info
                                            'period_status', jsonb_build_object(
                                                'id', apt.id,
                                                'name', apt.name
                                            )
                                        )
                                    ), '[]'::jsonb)
                                    FROM procurement_attempt_request_items i
                                    LEFT JOIN asset_requisitions_items ari ON i.item_id = ari.id
                                    LEFT JOIN organization o ON o.id = ari.organization AND o.deleted_at IS NULL AND o.isactive = TRUE
                                    LEFT JOIN asset_requisition_availability_types arat ON arat.id = ari.availability_type AND arat.deleted_at IS NULL AND arat.isactive = TRUE
                                    LEFT JOIN asset_requisition_period_types apt ON apt.id = ari.period_status AND apt.deleted_at IS NULL AND apt.isactive = TRUE
                                    WHERE i.attempted_id = qa.id
                                    AND i.procurement_id = p.id
                                    AND i.deleted_at IS NULL
                                    AND i.isactive = TRUE
                                    AND i.tenant_id = p_tenant_id
                                    AND (p_supplier_id = 0 OR i.supplier_id = p_supplier_id)
                                )
                            )
                        ), '[]'::jsonb)
                        FROM procurements_quotation_request_attempts qa
                        LEFT JOIN users u ON u.id = qa.attempted_by
                        WHERE qa.procurement_id = p.id
                        AND qa.deleted_at IS NULL
                        AND qa.isactive = TRUE
                        AND qa.tenant_id = p_tenant_id
                        AND (p_attempt_id = 0 OR qa.id = p_attempt_id)
                    ) AS quotation_request_attempts

                FROM procurements p
                WHERE p.deleted_at IS NULL 
                AND p.isactive = TRUE
                AND p.tenant_id = p_tenant_id
                AND (
                    (p_procurement_id != 0 AND p.id = p_procurement_id)
                    OR (p_procurement_id = 0 AND (p.request_id = p_request_id OR p_request_id IS NULL))
                )
                ORDER BY p.id;
            END;
            $$;
        ");
    }

    public function down(): void
    {
        DB::unprepared("
            DROP FUNCTION IF EXISTS get_procurements_details_for_supplier_quotation(BIGINT, INT, TEXT, BIGINT, BIGINT);
        ");
    }
};