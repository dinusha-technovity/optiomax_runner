<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
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
                    WHERE proname = 'insert_or_update_quotation_feedback'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

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
                    -- Update procurement attempt request items for existing feedback
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

                        -- Handle tax rates for updated items
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
                                p_id,
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

                    RETURN QUERY SELECT 'SUCCESS', 'Quotation updated successfully', p_procurement_id::BIGINT;
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