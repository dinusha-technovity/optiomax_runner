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
         DO $$
                DECLARE
                    r RECORD;
                BEGIN
                    FOR r IN
                        SELECT oid::regprocedure::text AS func_signature
                        FROM pg_proc
                        WHERE proname = 'get_procurement_items_details'
                    LOOP
                        EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                    END LOOP;
                END$$;

                CREATE OR REPLACE FUNCTION get_procurement_items_details(
                    IN p_auth_user_id BIGINT,
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
                    suppliers_items JSON,
                    suppliers_not_received_quotation JSON
                )
                LANGUAGE plpgsql
                STABLE
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
                        u_creator.name::TEXT,
                        p.created_at,

                        -- suppliers_items
                        (
                            SELECT COALESCE(json_agg(supplier_json), '[]')
                            FROM (
                                SELECT jsonb_build_object(
                                    'supplier_id', s.id,
                                    'supplier_name', s.name,
                                    'supplier_reg_status', s.supplier_reg_status,
                                    'supplier_rating', s.supplier_rating,
                                    'items', (
                                        SELECT json_agg(jsonb_build_object(
                                            'id', pari.id,
                                            'attempted_id', pari.attempted_id,
                                            'attempt_number', pqa.attemp_number,
                                            'closing_date', pqa.closing_date,
                                            'attempted_by', pqa.attempted_by,
                                            'attempted_by_name', attempt_user.name,
                                            'item_id', pari.item_id,
                                            'item_name', ari.item_name,
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
                                        ))
                                        FROM procurement_attempt_request_items pari
                                        LEFT JOIN users quser ON quser.id = pari.quotation_submitted_by
                                        LEFT JOIN asset_requisitions_items ari ON ari.id = pari.item_id
                                        LEFT JOIN procurements_quotation_request_attempts pqa ON pqa.id = pari.attempted_id
                                        LEFT JOIN users attempt_user ON attempt_user.id = pqa.attempted_by
                                        WHERE pari.procurement_id = p.id
                                        AND (p.procurement_status = 'save' OR p.procurement_status = 'COMPLETE' OR p.procurement_status = 'APPROVED')
                                        AND pari.supplier_id = s.id
                                        AND pari.is_receive_quotation = TRUE
                                        AND pari.isactive = TRUE
                                    )
                                ) AS supplier_json
                                FROM suppliers s
                                WHERE EXISTS (
                                    SELECT 1
                                    FROM procurement_attempt_request_items pari
                                    WHERE pari.procurement_id = p.id
                                    AND pari.supplier_id = s.id
                                    AND pari.is_receive_quotation = TRUE
                                    AND pari.isactive = TRUE
                                )
                            ) subq
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
                        )

                    FROM procurements p
                    LEFT JOIN users u_creator ON u_creator.id = p.created_by
                    WHERE p.deleted_at IS NULL
                    AND p.isactive = TRUE
                    AND (p.procurement_status = 'save' OR p.procurement_status = 'COMPLETE' OR p.procurement_status = 'APPROVED')
                    AND (p_tenant_id IS NULL OR p.tenant_id = p_tenant_id)
                    AND (p_procurement_id IS NULL OR p.id = p_procurement_id)
                    AND EXISTS (
                        SELECT 1
                        FROM procurement_staff ps
                        WHERE ps.user_id = p_auth_user_id
                            AND ps.isactive = TRUE
                            AND ps.deleted_at IS NULL
                            AND (p_tenant_id IS NULL OR ps.tenant_id = p_tenant_id)
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
        DB::unprepared("DROP FUNCTION IF EXISTS get_procurement_items_details(BIGINT, BIGINT);");
    }
};

