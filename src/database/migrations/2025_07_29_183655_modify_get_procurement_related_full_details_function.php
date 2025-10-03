<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            DROP FUNCTION IF EXISTS get_procurement_related_full_details(BIGINT, BIGINT);
            
            CREATE OR REPLACE FUNCTION get_procurement_related_full_details(
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_procurement_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                procurement_id BIGINT,
                request_id TEXT,
                procurement_status TEXT,
                quotation_request_attempt_count INTEGER,
                created_by BIGINT,
                created_by_name TEXT,
                created_at TIMESTAMP,
                suppliers_quotation JSON,
                suppliers_not_received_quotation JSON,
                procurement_related_suppliers JSON,
                procurement_items JSON,
                procurement_finalize_items JSON
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RETURN QUERY
                SELECT
                    'SUCCESS',
                    'Procurement items fetched successfully',
                    p.id,
                    p.request_id::TEXT,
                    p.procurement_status::TEXT,
                    p.quotation_request_attempt_count,
                    p.created_by,
                    creator.name::TEXT,
                    p.created_at,

                    -- suppliers_quotation
                    (
                        SELECT COALESCE(json_agg(sq), '[]')
                        FROM (
                            SELECT jsonb_build_object(
                                'supplier_id', s.id,
                                'supplier_name', s.name,
                                'supplier_reg_status', s.supplier_reg_status,
                                'supplier_rating', s.supplier_rating,
                                'items', (
                                    SELECT json_agg(
                                        jsonb_build_object(
                                            'id', pari.id,
                                            'attempted_id', pari.attempted_id,
                                            'attempt_number', pqa.attemp_number,
                                            'closing_date', pqa.closing_date,
                                            'attempted_by', pqa.attempted_by,
                                            'attempted_by_name', attempt_user.name,
                                            'item_id', pari.item_id,
                                            'item_name', ari.item_name,
                                            'priority_id', ari.priority,
                                            'priority_name', arpt.name,
                                            'expected_budget_per_item', pari.expected_budget_per_item,
                                            'requested_quantity', pari.requested_quantity,
                                            'rfp_document', pari.rfp_document,
                                            'attachment', pari.attachment,
                                            'required_date', pari.required_date,
                                            'message_to_supplier', pari.message_to_supplier,
                                            'is_receive_quotation', pari.is_receive_quotation,
                                            'is_available_on_quotation', pari.is_available_on_quotation,
                                            'available_date', pari.available_date,
                                            'normal_price_per_item', pari.normal_price_per_item,
                                            'with_tax_price_per_item', pari.with_tax_price_per_item,
                                            'available_quantity', pari.available_quantity,
                                            'can_full_fill_requested_quantity', pari.can_full_fill_requested_quantity,
                                            'message_from_supplier', pari.message_from_supplier,
                                            'supplier_terms_and_conditions', pari.supplier_terms_and_conditions,
                                            'supplier_attachment', pari.supplier_attachment,
                                            'delivery_cost', pari.delivery_cost,
                                            'reason_for_not_available', pari.reason_for_not_available,
                                            'quotation_submitted_by_id', pari.quotation_submitted_by,
                                            'quotation_submitted_by_name',
                                                CASE
                                                    WHEN quser.is_system_user IS TRUE THEN s.name
                                                    ELSE quser.name
                                                END,
                                            'deleted_at', pari.deleted_at,
                                            'isactive', pari.isactive,
                                            'tenant_id', pari.tenant_id,
                                            'created_at', pari.created_at,
                                            'updated_at', pari.updated_at,
                                            'tax_rates', (
                                                SELECT json_agg(jsonb_build_object(
                                                    'id', tax.id,
                                                    'tax_type', tax.tax_type,
                                                    'tax_rate', tax.tax_rate,
                                                    'deleted_at', tax.deleted_at,
                                                    'isactive', tax.isactive,
                                                    'tenant_id', tax.tenant_id,
                                                    'created_at', tax.created_at,
                                                    'updated_at', tax.updated_at
                                                ))
                                                FROM procurement_attempt_request_items_related_tax_rate tax
                                                WHERE tax.procurement_attempt_request_item_id = pari.id
                                            )
                                        )
                                    )
                                    FROM procurement_attempt_request_items pari
                                    LEFT JOIN users quser ON quser.id = pari.quotation_submitted_by
                                    LEFT JOIN asset_requisitions_items ari ON ari.id = pari.item_id
                                    LEFT JOIN asset_requisition_priority_types arpt ON arpt.id = ari.priority
                                    LEFT JOIN procurements_quotation_request_attempts pqa ON pqa.id = pari.attempted_id
                                    LEFT JOIN users attempt_user ON attempt_user.id = pqa.attempted_by
                                    WHERE pari.procurement_id = p.id 
                                    AND pari.supplier_id = s.id
                                    AND pari.isactive = TRUE
                                )
                            ) AS sq
                            FROM suppliers s
                            WHERE EXISTS (
                                SELECT 1
                                FROM procurement_attempt_request_items pari
                                WHERE pari.procurement_id = p.id
                                AND pari.supplier_id = s.id
                                AND pari.isactive = TRUE
                            )
                        ) supplier_data
                    ),

                    -- suppliers_not_received_quotation
                    (
                        SELECT json_agg(DISTINCT jsonb_build_object(
                            'supplier_id', s2.id,
                            'supplier_name', s2.name
                        ))
                        FROM procurement_attempt_request_items pari2
                        INNER JOIN suppliers s2 ON s2.id = pari2.supplier_id
                        WHERE pari2.procurement_id = p.id
                        AND pari2.is_receive_quotation = FALSE
                        AND pari2.isactive = TRUE
                    ),

                    -- procurement_related_suppliers
                    (
                        SELECT json_agg(DISTINCT jsonb_build_object(
                            'supplier_id', s.id,
                            'supplier_name', s.name,
                            'supplier_reg_status', s.supplier_reg_status,
                            'supplier_rating', s.supplier_rating
                        ))
                        FROM procurement_attempt_request_items pari
                        INNER JOIN suppliers s ON s.id = pari.supplier_id
                        WHERE pari.procurement_id = p.id
                        AND pari.isactive = TRUE
                    ),

                    -- procurement_items (distinct non-repeating)
                    (
                        SELECT json_agg(DISTINCT jsonb_build_object(
                            -- Existing fields
                            'id', ari.id,
                            'item_name', ari.item_name,
                            'quantity', ari.quantity,
                            'item_count', ari.item_count,
                            'requested_budget', ari.requested_budget,
                            'budget', ari.budget,
                            'business_purpose', ari.business_purpose,
                            'required_date', ari.required_date,
                            'expected_conditions', ari.expected_conditions,
                            'reason', ari.reason,
                            'business_impact', ari.business_impact,
                            'files', ari.files,
                            'item_details', ari.item_details,
                            'maintenance_kpi', ari.maintenance_kpi,
                            'service_support_kpi', ari.service_support_kpi,
                            'consumables_kpi', ari.consumables_kpi,

                            -- New columns
                            'asset_item_id', ari.asset_item_id,
                            'asset_item_name', ass.name,
                            'asset_category_id', ari.asset_category,
                            'asset_category_name', ac.name,
                            'asset_sub_category_id', ari.asset_sub_category,
                            'asset_sub_category_name', assc.name,
                            'description', ari.description,


                            -- Lookup names
                            'priority_id', ari.priority,
                            'priority_name', prt.name,
                            'period_status_id', ari.period_status,
                            'period_status_name', pst.name,
                            'availability_type_id', ari.availability_type,
                            'availability_type_name', avt.name,
                            'organization_id', ari.organization,
                            'organization_name', org.data ->> 'organizationName',
                            'organization_email', org.data ->> 'email',
                            'organization_address', org.data ->> 'address',
                            'organization_website', org.data ->> 'website',
                            'organization_telephone_number', org.data ->> 'telephoneNumber',
                            'organization_description', org.data ->> 'organizationDescription',

                            -- Requisition info
                            'requisition_id', ar.requisition_id,
                            'requisition_date', ar.requisition_date
                        ))
                        FROM procurement_attempt_request_items pari
                        INNER JOIN asset_requisitions_items ari ON ari.id = pari.item_id
                        INNER JOIN asset_requisitions ar ON ar.id = ari.asset_requisition_id
                        LEFT JOIN asset_items ai ON ai.id = ari.asset_item_id
                        LEFT JOIN assets ass ON ass.id = ai.asset_id
                        LEFT JOIN asset_categories ac ON ac.id = ari.asset_category
                        LEFT JOIN asset_sub_categories assc ON assc.id = ari.asset_sub_category
                        LEFT JOIN asset_requisition_priority_types prt ON prt.id = ari.priority
                        LEFT JOIN asset_requisition_period_types pst ON pst.id = ari.period_status
                        LEFT JOIN asset_requisition_availability_types avt ON avt.id = ari.availability_type
                        LEFT JOIN organization org ON org.id = ari.organization
                        WHERE pari.procurement_id = p.id
                        AND pari.isactive = TRUE
                    ),


                    -- procurement_finalize_items with added tax_rates
                    (
                        SELECT json_agg(jsonb_build_object(
                            'id', pfi.id,
                            'procurement_id', pfi.procurement_id,
                            'tenant_id', pfi.tenant_id,
                            'finalize_items_id', pfi.finalize_items_id,
                            'quotation_item', jsonb_build_object(
                                'expected_budget_per_item', pari.expected_budget_per_item,
                                'requested_quantity', pari.requested_quantity,
                                'normal_price_per_item', pari.normal_price_per_item,
                                'with_tax_price_per_item', pari.with_tax_price_per_item,
                                'available_quantity', pari.available_quantity,
                                'message_from_supplier', pari.message_from_supplier,
                                'supplier_terms_and_conditions', pari.supplier_terms_and_conditions,
                                'delivery_cost', pari.delivery_cost,
                                'available_date', pari.available_date,
                                'can_full_fill_requested_quantity', pari.can_full_fill_requested_quantity,
                                'is_receive_quotation', pari.is_receive_quotation,
                                'quotation_submitted_by', pari.quotation_submitted_by,
                                'created_at', pari.created_at,
                                'updated_at', pari.updated_at,
                                'tax_rates', (
                                    SELECT json_agg(jsonb_build_object(
                                        'id', tax.id,
                                        'tax_type', tax.tax_type,
                                        'tax_rate', tax.tax_rate,
                                        'deleted_at', tax.deleted_at,
                                        'isactive', tax.isactive,
                                        'tenant_id', tax.tenant_id,
                                        'created_at', tax.created_at,
                                        'updated_at', tax.updated_at
                                    ))
                                    FROM procurement_attempt_request_items_related_tax_rate tax
                                    WHERE tax.procurement_attempt_request_item_id = pari.id
                                )
                            ),
                            'supplier_id', s.id,
                            'supplier_name', s.name,
                            'supplier_reg_status', s.supplier_reg_status,
                            'supplier_rating', s.supplier_rating,
                            'asset_requisitions_id', ar.id,
                            'requisition_id', ar.requisition_id,
                            'requisition_date', ar.requisition_date,
                            'asset_requisitions_item_id', ari.id,
                            'item_name', ari.item_name,
                            'requested_quantity', ari.quantity,
                            'requested_budget', ari.requested_budget,
                            'description', ari.description,
                            'priority_id', ari.priority,
                            'priority_name', prt.name,
                            'period_status_id', ari.period_status,
                            'period_status_name', pst.name,
                            'availability_type_id', ari.availability_type,
                            'availability_type_name', avt.name,
                            'organization_id', ari.organization,
                            'organization_name', org.data ->> 'organizationName',
                            'organization_email', org.data ->> 'email',
                            'organization_address', org.data ->> 'address',
                            'organization_website', org.data ->> 'website',
                            'organization_telephone_number', org.data ->> 'telephoneNumber',
                            'organization_description', org.data ->> 'organizationDescription',
                            'required_date', ari.required_date,
                            'isactive', pfi.isactive,
                            'deleted_at', pfi.deleted_at,
                            'created_at', pfi.created_at,
                            'updated_at', pfi.updated_at
                        ))
                        FROM procurement_finalize_items pfi
                        LEFT JOIN procurement_attempt_request_items pari ON pari.id = pfi.finalize_items_id
                        LEFT JOIN suppliers s ON s.id = pfi.supplier_id
                        LEFT JOIN asset_requisitions ar ON ar.id = pfi.asset_requisitions_id
                        LEFT JOIN asset_requisitions_items ari ON ari.id = pfi.asset_requisitions_item_id
                        LEFT JOIN asset_requisition_priority_types prt ON prt.id = ari.priority
                        LEFT JOIN asset_requisition_period_types pst ON pst.id = ari.period_status
                        LEFT JOIN asset_requisition_availability_types avt ON avt.id = ari.availability_type
                        LEFT JOIN organization org ON org.id = ari.organization
                        WHERE pfi.procurement_id = p.id
                        AND pfi.isactive = TRUE
                        AND pfi.deleted_at IS NULL
                    )
                FROM procurements p
                LEFT JOIN users creator ON creator.id = p.created_by
                WHERE p.deleted_at IS NULL AND p.isactive = TRUE
                    AND (p_tenant_id IS NULL OR p.tenant_id = p_tenant_id)
                    AND (p_procurement_id IS NULL OR p.id = p_procurement_id)
                GROUP BY p.id, creator.name, p.created_at;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_procurement_related_full_details(BIGINT, BIGINT);");
    }
};