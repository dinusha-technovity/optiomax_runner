<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS insert_or_update_quotation_feedback(
            DATE, INT, INT, JSONB, DATE, INT, TIMESTAMPTZ, BIGINT, BIGINT, TEXT, BIGINT
        );");

        // DB::unprepared(<<<SQL
        //     CREATE OR REPLACE FUNCTION insert_or_update_quotation_feedback(
        //         IN p_date DATE, 
        //         IN p_procurement_id INT,
        //         IN p_selected_supplier_id INT,
        //         IN p_selected_items JSONB,
        //         IN p_required_date DATE,
        //         IN p_feedback_fill_by INT,
        //         IN p_attempt_id INT,
        //         IN p_current_time TIMESTAMPTZ,
        //         IN p_id BIGINT DEFAULT NULL,
        //         IN p_causer_id BIGINT DEFAULT NULL,
        //         IN p_causer_name TEXT DEFAULT NULL,
        //         IN p_tenant_id BIGINT DEFAULT NULL
        //     )
        //     RETURNS TABLE (
        //         status TEXT,
        //         message TEXT,
        //         procurement_id BIGINT
        //     )
        //     LANGUAGE plpgsql
        //     AS \$\$
        //     DECLARE
        //         return_id BIGINT;
        //         inserted_row JSONB;
        //         updated_row JSONB;
        //         v_selected_suppliers JSONB;
        //         supplier_count INT;
        //         feedback_count INT;
        //     BEGIN
        //         IF p_id IS NULL OR p_id = 0 THEN
        //             INSERT INTO public.quotation_feedbacks (
        //                 date, procurement_id, selected_supplier_id, selected_items, available_date, feedback_fill_by, procurements_quotation_request_attempts_id,
        //                 created_at, updated_at
        //             ) VALUES (
        //                 p_date, p_procurement_id, p_selected_supplier_id, p_selected_items, p_required_date, p_feedback_fill_by, p_attempt_id,
        //                 p_current_time, p_current_time
        //             ) RETURNING id, to_jsonb(quotation_feedbacks) INTO return_id, inserted_row;

        //             BEGIN
        //                 PERFORM log_activity(
        //                     'insert_quotation_feedback',
        //                     format('Inserted quotation feedback with ID %s by %s', return_id, p_causer_name),
        //                     'quotation_feedbacks',
        //                     return_id,
        //                     'user',
        //                     p_causer_id,
        //                     inserted_row,
        //                     p_tenant_id
        //                 );
        //             EXCEPTION WHEN OTHERS THEN NULL;
        //             END;

        //             -- Fetch selected_suppliers JSONB from the attempt
        //             SELECT pqrra.selected_suppliers INTO v_selected_suppliers
        //             FROM procurements_quotation_request_attempts pqrra
        //             WHERE pqrra.id = p_attempt_id;

        //             -- Count only suppliers with valid non-null IDs
        //             SELECT COUNT(*) INTO supplier_count
        //             FROM jsonb_array_elements(v_selected_suppliers) AS supplier
        //             WHERE (supplier ->> 'id') IS NOT NULL;

        //             -- Count distinct feedbacks submitted
        //             SELECT COUNT(DISTINCT selected_supplier_id) INTO feedback_count
        //             FROM quotation_feedbacks
        //             WHERE procurements_quotation_request_attempts_id = p_attempt_id;

        //             IF feedback_count >= supplier_count THEN
        //                 UPDATE procurements_quotation_request_attempts
        //                 SET request_attempts_status = 'complete',
        //                     updated_at = p_current_time
        //                 WHERE id = p_attempt_id;

        //                 UPDATE procurements
        //                 SET procurement_status = 'complete',
        //                     updated_at = p_current_time
        //                 WHERE id = p_procurement_id;
        //             END IF;

        //             RETURN QUERY SELECT 'SUCCESS', 'Quotation submitted successfully', return_id;

        //         ELSE
        //             UPDATE public.quotation_feedbacks
        //             SET 
        //                 date = p_date,
        //                 procurement_id = p_procurement_id,
        //                 selected_supplier_id = p_selected_supplier_id,
        //                 selected_items = p_selected_items,
        //                 available_date = p_required_date, 
        //                 feedback_fill_by = p_feedback_fill_by,
        //                 updated_at = p_current_time
        //             WHERE id = p_id RETURNING id, to_jsonb(quotation_feedbacks) INTO return_id, updated_row;

        //             IF FOUND THEN
        //                 BEGIN
        //                     PERFORM log_activity(
        //                         'update_quotation_feedback',
        //                         format('Updated quotation feedback ID %s by %s', return_id, p_causer_name),
        //                         'quotation_feedbacks',
        //                         return_id,
        //                         'user',
        //                         p_causer_id,
        //                         updated_row,
        //                         p_tenant_id
        //                     );
        //                 EXCEPTION WHEN OTHERS THEN NULL;
        //                 END;

        //                 RETURN QUERY SELECT 'SUCCESS', 'Quotation updated successfully', return_id;
        //             ELSE
        //                 RETURN QUERY SELECT 'FAILURE', format('Quotation feedback with ID %s not found', p_id), NULL::BIGINT;
        //             END IF;
        //         END IF;
        //     END;
        //     \$\$;
        // SQL);
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION insert_or_update_quotation_feedback(
                IN p_date DATE, 
                IN p_procurement_id INT, 
                IN p_selected_supplier_id INT,
                IN p_selected_items JSONB,
                IN p_feedback_fill_by INT,
                IN p_attempt_id INT,
                IN p_current_time TIMESTAMPTZ,
                IN p_id BIGINT DEFAULT NULL,
                IN p_causer_id BIGINT DEFAULT NULL,
                IN p_causer_name TEXT DEFAULT NULL,
                IN p_tenant_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                procurement_id BIGINT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                return_id BIGINT;
                inserted_row JSONB;
                updated_row JSONB;
                item JSONB;
                tax JSONB;
                item_row_id BIGINT;
            BEGIN
                IF p_id IS NULL OR p_id = 0 THEN

                    FOR item IN SELECT * FROM jsonb_array_elements(p_selected_items)
                    LOOP
                        UPDATE procurement_attempt_request_items
                        SET
                            expected_budget_per_item = CASE WHEN (item ->> 'normal_price') ~ '^\s*$' THEN NULL ELSE (item ->> 'normal_price')::NUMERIC END,
                            with_tax_price_per_item = CASE WHEN (item ->> 'withtax_price') ~ '^\s*$' THEN NULL ELSE (item ->> 'withtax_price')::NUMERIC END,
                            normal_price_per_item = CASE WHEN (item ->> 'normal_price') ~ '^\s*$' THEN NULL ELSE (item ->> 'normal_price')::NUMERIC END,
                            available_quantity = CASE WHEN (item ->> 'available_qty') ~ '^\s*$' THEN NULL ELSE (item ->> 'available_qty')::NUMERIC END,
                            available_date = NULLIF((item ->> 'available_date')::DATE, NULL),
                            is_available_on_quotation = COALESCE((item ->> 'is_available')::BOOLEAN, false),
                            is_receive_quotation = true,
                            delivery_cost = CASE WHEN (item ->> 'delivery_cost') ~ '^\s*$' THEN NULL ELSE (item ->> 'delivery_cost')::NUMERIC END,
                            can_full_fill_requested_quantity = COALESCE((item ->> 'can_fulfill_full_qty')::BOOLEAN, true),
                            message_from_supplier = NULLIF(item ->> 'comment', ''),
                            reason_for_not_available = NULLIF(item ->> 'not_available_reason', ''),
                            supplier_terms_and_conditions = item -> 'conditions',
                            supplier_attachment = item -> 'document_ids',
                            quotation_submitted_by = p_causer_id,
                            updated_at = p_current_time
                        WHERE
                            procurement_attempt_request_items.procurement_id = p_procurement_id
                            AND procurement_attempt_request_items.attempted_id = p_attempt_id
                            AND procurement_attempt_request_items.id = (item ->> 'item_id')::BIGINT
                            AND procurement_attempt_request_items.supplier_id = p_selected_supplier_id
                        RETURNING id INTO item_row_id;

                        DELETE FROM procurement_attempt_request_items_related_tax_rate
                        WHERE procurement_attempt_request_item_id = item_row_id;

                        FOR tax IN SELECT * FROM jsonb_array_elements(item -> 'taxes')
                        LOOP
                            INSERT INTO procurement_attempt_request_items_related_tax_rate (
                                procurement_attempt_request_item_id,
                                tax_type,
                                tax_rate,
                                deleted_at,
                                isactive,
                                tenant_id,
                                created_at,
                                updated_at
                            ) VALUES (
                                item_row_id,
                                NULLIF(tax ->> 'tax_type', ''),
                                CASE WHEN (tax ->> 'tax_value') ~ '^\s*$' THEN NULL ELSE (tax ->> 'tax_value')::NUMERIC END,
                                NULL,
                                true,
                                p_tenant_id,
                                p_current_time,
                                p_current_time
                            );
                        END LOOP;
                    END LOOP;

                    BEGIN
                        PERFORM log_activity(
                            'update_procurement_attempt_items',
                            format(
                                'Updated procurement items for procurement ID %s and supplier ID %s via feedback ID %s by %s',
                                p_procurement_id,
                                p_selected_supplier_id,
                                return_id,
                                p_causer_name
                            ),
                            'procurement_attempt_request_items',
                            NULL,
                            'user',
                            p_causer_id,
                            p_selected_items,
                            p_tenant_id
                        );
                    EXCEPTION WHEN OTHERS THEN NULL;
                    END;

                    RETURN QUERY SELECT 'SUCCESS', 'Quotation submitted successfully', return_id;

                ELSE
                    UPDATE public.quotation_feedbacks
                    SET 
                        date = p_date,
                        procurement_id = p_procurement_id,
                        selected_supplier_id = p_selected_supplier_id,
                        selected_items = p_selected_items,
                        feedback_fill_by = p_feedback_fill_by,
                        updated_at = p_current_time
                    WHERE id = p_id RETURNING id, to_jsonb(quotation_feedbacks) INTO return_id, updated_row;

                    IF FOUND THEN
                        BEGIN
                            PERFORM log_activity(
                                'update_quotation_feedback',
                                format('Updated quotation feedback ID %s by %s', return_id, p_causer_name),
                                'quotation_feedbacks',
                                return_id,
                                'user',
                                p_causer_id,
                                updated_row,
                                p_tenant_id
                            );
                        EXCEPTION WHEN OTHERS THEN NULL;
                        END;

                        RETURN QUERY SELECT 'SUCCESS', 'Quotation updated successfully', return_id;
                    ELSE
                        RETURN QUERY SELECT 'FAILURE', format('Quotation feedback with ID %s not found', p_id), NULL::BIGINT;
                    END IF;
                END IF;
            END;
            $$;
        SQL);

    }

    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS insert_or_update_quotation_feedback(
            DATE, INT, INT, JSONB, DATE, INT, INT, TIMESTAMPTZ, BIGINT, BIGINT, TEXT, BIGINT
        );");
    }
};