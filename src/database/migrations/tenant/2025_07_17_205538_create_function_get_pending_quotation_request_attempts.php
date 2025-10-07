<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // DB::unprepared(<<<SQL
        //     DROP FUNCTION IF EXISTS get_pending_request_attempts(BIGINT, BIGINT);

        //     CREATE OR REPLACE FUNCTION get_pending_request_attempts(
        //         IN p_tenant_id BIGINT DEFAULT NULL,
        //         IN p_pending_request_attempts_id BIGINT DEFAULT NULL
        //     )
        //     RETURNS TABLE (
        //         status TEXT,
        //         message TEXT,
        //         attempt_id BIGINT,
        //         procurement_id BIGINT,
        //         attempted_by BIGINT,
        //         attempted_by_name TEXT,
        //         selected_items JSONB,
        //         selected_suppliers JSONB,
        //         rfp_document JSONB,
        //         attachment JSONB,
        //         required_date DATE,
        //         comment TEXT,
        //         request_attempts_status TEXT,
        //         attempt_created_at TIMESTAMP,
        //         procurement_request_id TEXT,
        //         procurement_by BIGINT,
        //         procurement_by_name TEXT,
        //         procurement_date DATE,
        //         procurement_status TEXT,
        //         quotation_request_attempt_count INTEGER,
        //         feedbacks JSON
        //     )
        //     LANGUAGE plpgsql
        //     AS \$\$
        //     BEGIN
        //         RETURN QUERY
        //         SELECT
        //             'SUCCESS'::TEXT,
        //             'Pending quotation request attempts fetched successfully'::TEXT,
        //             pqa.id,
        //             pqa.procurement_id,
        //             pqa.attempted_by,
        //             ua.name::TEXT,
        //             pqa.selected_items,
        //             pqa.selected_suppliers,
        //             pqa.rfp_document,
        //             pqa.attachment,
        //             pqa.required_date,
        //             pqa.comment,
        //             pqa.request_attempts_status::TEXT,
        //             pqa.created_at,
        //             pr.request_id::TEXT,
        //             pr.procurement_by,
        //             ub.name::TEXT,
        //             pr.date,
        //             pr.procurement_status::TEXT,
        //             pr.quotation_request_attempt_count,
        //             COALESCE(json_agg(
        //                 jsonb_build_object(
        //                     'id', qf.id,
        //                     'date', qf.date,
        //                     'selected_supplier_id', qf.selected_supplier_id,
        //                     'supplier_name', s.name,
        //                     'selected_items', qf.selected_items,
        //                     'available_date', qf.available_date,
        //                     'feedback_fill_by', qf.feedback_fill_by,
        //                     'feedback_fill_by_name', 
        //                         CASE
        //                             WHEN fb_user.is_system_user IS TRUE THEN s.name
        //                             ELSE fb_user.name
        //                         END,
        //                     'created_at', qf.created_at
        //                 )
        //             ) FILTER (WHERE qf.id IS NOT NULL), '[]'::JSON) AS feedbacks
        //         FROM procurements_quotation_request_attempts pqa
        //         INNER JOIN procurements pr ON pr.id = pqa.procurement_id
        //         LEFT JOIN users ua ON ua.id = pqa.attempted_by
        //         LEFT JOIN users ub ON ub.id = pr.procurement_by
        //         LEFT JOIN quotation_feedbacks qf ON qf.procurements_quotation_request_attempts_id = pqa.id
        //         LEFT JOIN users fb_user ON fb_user.id = qf.feedback_fill_by
        //         LEFT JOIN suppliers s ON s.id = qf.selected_supplier_id
        //         WHERE pqa.request_attempts_status = 'pending'
        //         AND pqa.deleted_at IS NULL
        //         AND pqa.isactive = TRUE
        //         AND (p_tenant_id IS NULL OR pqa.tenant_id = p_tenant_id)
        //         AND (p_pending_request_attempts_id IS NULL OR pqa.id = p_pending_request_attempts_id)
        //         GROUP BY
        //             pqa.id,
        //             pr.id,
        //             ua.name,
        //             ub.name;
        //     END;
        //     \$\$;
        // SQL);
        DB::unprepared(<<<SQL
            DROP FUNCTION IF EXISTS get_pending_request_attempts(BIGINT, BIGINT);

            CREATE OR REPLACE FUNCTION get_pending_request_attempts(
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_pending_request_attempts_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                attempt_id BIGINT,
                procurement_id BIGINT,
                attemp_number BIGINT,
                attempted_by BIGINT,
                attempted_by_name TEXT,
                procurement_request_id TEXT,
                procurement_status TEXT,
                quotation_request_attempt_count INTEGER,
                attempt_created_at TIMESTAMP,
                closing_date DATE,
                created_by BIGINT,
                created_by_name TEXT,
                supplier_quotations JSON,
                suppliers_not_received_quotation JSON
            )
            LANGUAGE plpgsql
            AS \$\$
            BEGIN
                RETURN QUERY
                SELECT
                    'SUCCESS',
                    'Pending quotation request attempts fetched successfully',
                    pqa.id,
                    pqa.procurement_id,
                    pqa.attemp_number,
                    pqa.attempted_by,
                    ua.name::TEXT,
                    pr.request_id::TEXT,
                    pr.procurement_status::TEXT,
                    pr.quotation_request_attempt_count,
                    pqa.created_at,
                    pqa.closing_date,
                    pr.created_by,
                    ub.name::TEXT,

                    -- supplier_quotations (unique supplier, aggregated items)
                    (
                        SELECT COALESCE(json_agg(sq) FILTER (WHERE json_array_length((sq->'items')::json) > 0), '[]'::json)
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
                                            'procurement_id', pari.procurement_id,
                                            'attempted_id', pari.attempted_id,
                                            'supplier_id', pari.supplier_id,
                                            'item_id', pari.item_id,
                                            'item_name', ari.item_name,  -- ✅ NEW FIELD
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
                                    WHERE pari.attempted_id = pqa.id
                                    AND pari.supplier_id = s.id
                                    AND pari.is_receive_quotation = TRUE
                                )
                            ) AS sq
                            FROM suppliers s
                            WHERE EXISTS (
                                SELECT 1
                                FROM procurement_attempt_request_items pari
                                WHERE pari.attempted_id = pqa.id
                                AND pari.supplier_id = s.id
                                AND pari.is_receive_quotation = TRUE
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
                        WHERE pari2.attempted_id = pqa.id
                        AND pari2.is_receive_quotation = FALSE
                    )

                FROM procurements_quotation_request_attempts pqa
                INNER JOIN procurements pr ON pr.id = pqa.procurement_id
                LEFT JOIN users ua ON ua.id = pqa.attempted_by
                LEFT JOIN users ub ON ub.id = pr.created_by

                WHERE pqa.request_attempts_status = 'pending'
                AND pqa.deleted_at IS NULL
                AND pqa.isactive = TRUE
                AND (p_tenant_id IS NULL OR pqa.tenant_id = p_tenant_id)
                AND (p_pending_request_attempts_id IS NULL OR pqa.id = p_pending_request_attempts_id)

                GROUP BY
                    pqa.id,
                    pr.id,
                    ua.name,
                    ub.name;
            END;
            \$\$;
        SQL);

    }

    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_pending_request_attempts(BIGINT, BIGINT);");
    }
};