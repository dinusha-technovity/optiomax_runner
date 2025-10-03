<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS insert_or_update_procurement_Initiate(
            INT, DATE, JSONB, JSONB, JSONB, JSONB, DATE, VARCHAR, VARCHAR, BIGINT, BIGINT, TEXT);");

        // DB::unprepared(<<<SQL
        //     CREATE OR REPLACE FUNCTION insert_or_update_procurement_Initiate(
        //         IN p_procurement_request_user INT,
        //         IN p_date DATE,
        //         IN p_selected_items JSONB,
        //         IN p_selected_suppliers JSONB,
        //         IN p_rfp_document JSONB,
        //         IN p_attachment JSONB,
        //         IN p_quotation_calling_closing_date DATE,
        //         IN p_comment VARCHAR(191),
        //         IN p_procurement_status VARCHAR(191),
        //         IN p_tenant_id BIGINT,
        //         IN p_current_time TIMESTAMP WITH TIME ZONE,
        //         IN p_id BIGINT DEFAULT NULL,
        //         IN p_prefix TEXT DEFAULT 'PROC' 
        //     )
        //     RETURNS TABLE (
        //         status TEXT,
        //         message TEXT,
        //         procurement_id BIGINT,
        //         procurement_reg_no TEXT,
        //         attempt_id BIGINT
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     DECLARE
        //         return_id BIGINT;
        //         new_procurement_reg_no VARCHAR(50);
        //         curr_val INT;
        //         new_procurement JSONB;
        //         old_procurement JSONB;
        //         v_log_success BOOLEAN := FALSE;
        //         attempt_id BIGINT;
        //         attempt_json JSONB;
        //         supplier JSONB;
        //         item JSONB;
        //     BEGIN
        //         IF p_id IS NULL OR p_id = 0 THEN
        //             SELECT nextval('procurement_register_id_seq') INTO curr_val;
        //             new_procurement_reg_no := p_prefix || '-' || LPAD(curr_val::TEXT, 4, '0');

        //             INSERT INTO public.procurements (
        //                 request_id, created_by, quotation_request_attempt_count, procurement_status,
        //                 selected_items, tenant_id, created_at, updated_at
        //             ) VALUES (
        //                 new_procurement_reg_no, p_procurement_request_user, 1, p_procurement_status,
        //                 p_selected_items, p_tenant_id, p_current_time, p_current_time
        //             ) RETURNING id, to_jsonb(procurements.*) INTO return_id, new_procurement;

        //             BEGIN
        //                 PERFORM log_activity(
        //                     'create_procurement',
        //                     'Procurement created',
        //                     'procurement',
        //                     return_id,
        //                     'user',
        //                     p_procurement_request_user,
        //                     jsonb_build_object('after', new_procurement),
        //                     p_tenant_id
        //                 );
        //                 v_log_success := TRUE;
        //             EXCEPTION WHEN OTHERS THEN
        //                 v_log_success := FALSE;
        //             END;

        //             BEGIN
        //                 INSERT INTO procurements_quotation_request_attempts (
        //                     procurement_id, attemp_number, attempted_by, closing_date, tenant_id, created_at, updated_at
        //                 ) VALUES (
        //                     return_id, 1, p_procurement_request_user, p_quotation_calling_closing_date, p_tenant_id, p_current_time, p_current_time
        //                 ) RETURNING id, to_jsonb(procurements_quotation_request_attempts.*)
        //                 INTO attempt_id, attempt_json;

        //                 BEGIN
        //                     PERFORM log_activity(
        //                         'create_procurement_attempt',
        //                         'Quotation Request Attempt created',
        //                         'procurements_quotation_request_attempts',
        //                         attempt_id,
        //                         'user',
        //                         p_procurement_request_user,
        //                         jsonb_build_object('after', attempt_json),
        //                         p_tenant_id
        //                     );
        //                 EXCEPTION WHEN OTHERS THEN
        //                     NULL;
        //                 END;
        //             END;

        //             FOR item IN SELECT * FROM jsonb_array_elements(p_selected_items)
        //             LOOP
        //                 FOR supplier IN SELECT * FROM jsonb_array_elements(p_selected_suppliers)
        //                 LOOP
        //                     INSERT INTO procurement_attempt_request_items (
        //                         procurement_id, attempted_id, supplier_id, item_id,
        //                         expected_budget_per_item, requested_quantity, rfp_document,
        //                         attachment, required_date, message_to_supplier,
        //                         is_receive_quotation, is_available_on_quotation,
        //                         tenant_id, created_at, updated_at
        //                     ) VALUES (
        //                         return_id,
        //                         attempt_id,
        //                         (supplier ->> 'id')::BIGINT,
        //                         (item ->> 'id')::BIGINT,
        //                         (item ->> 'budget')::DECIMAL,
        //                         (item ->> 'quantity')::DECIMAL,
        //                         p_rfp_document,
        //                         p_attachment,
        //                         (item ->> 'required_date')::DATE,
        //                         (item ->> 'business_purpose')::TEXT,
        //                         FALSE,
        //                         FALSE,
        //                         p_tenant_id,
        //                         p_current_time,
        //                         p_current_time
        //                     );
        //                 END LOOP;
        //             END LOOP;

        //             RETURN QUERY SELECT 
        //                 'SUCCESS',
        //                 'Procurement added successfully',
        //                 return_id,
        //                 new_procurement_reg_no::TEXT,
        //                 attempt_id;

        //         ELSE
        //             SELECT to_jsonb(p) INTO old_procurement FROM procurements p WHERE id = p_id;

        //             SELECT COALESCE(MAX(pqra.attemp_number), 0) + 1
        //             INTO curr_val
        //             FROM procurements_quotation_request_attempts pqra
        //             WHERE pqra.procurement_id = p_id;

        //             UPDATE public.procurements
        //             SET 
        //                 procurement_status = p_procurement_status,
        //                 selected_items = p_selected_items,
        //                 tenant_id = p_tenant_id,
        //                 quotation_request_attempt_count = curr_val,
        //                 updated_at = p_current_time
        //             WHERE id = p_id
        //             RETURNING id, to_jsonb(procurements.*) INTO return_id, new_procurement;

        //             IF NOT FOUND THEN
        //                 RETURN QUERY SELECT 
        //                     'FAILURE',
        //                     format('Procurement with id %s not found', p_id),
        //                     NULL,
        //                     NULL,
        //                     NULL;
        //             ELSE
        //                 BEGIN
        //                     PERFORM log_activity(
        //                         'update_procurement',
        //                         'Procurement updated',
        //                         'procurement',
        //                         return_id,
        //                         'user',
        //                         p_procurement_request_user,
        //                         jsonb_build_object('before', old_procurement, 'after', new_procurement),
        //                         p_tenant_id
        //                     );
        //                     v_log_success := TRUE;
        //                 EXCEPTION WHEN OTHERS THEN
        //                     v_log_success := FALSE;
        //                 END;

        //                 UPDATE procurements_quotation_request_attempts pqra
        //                 SET request_attempts_status = 'EXIT'
        //                 WHERE pqra.procurement_id = p_id;

        //                 INSERT INTO procurements_quotation_request_attempts (
        //                     procurement_id, attemp_number, attempted_by, closing_date, tenant_id, created_at, updated_at
        //                 ) VALUES (
        //                     p_id,
        //                     curr_val,
        //                     p_procurement_request_user,
        //                     p_quotation_calling_closing_date,
        //                     p_tenant_id,
        //                     p_current_time,
        //                     p_current_time
        //                 )
        //                 RETURNING id, to_jsonb(procurements_quotation_request_attempts.*)
        //                 INTO attempt_id, attempt_json;

        //                 BEGIN
        //                     PERFORM log_activity(
        //                         'create_procurement_attempt',
        //                         'New attempt after update',
        //                         'procurements_quotation_request_attempts',
        //                         attempt_id,
        //                         'user',
        //                         p_procurement_request_user,
        //                         jsonb_build_object('after', attempt_json),
        //                         p_tenant_id
        //                     );
        //                 EXCEPTION WHEN OTHERS THEN
        //                     NULL;
        //                 END;

        //                 FOR item IN SELECT * FROM jsonb_array_elements(p_selected_items)
        //                 LOOP
        //                     FOR supplier IN SELECT * FROM jsonb_array_elements(p_selected_suppliers)
        //                     LOOP
        //                         UPDATE procurement_attempt_request_items
        //                         SET isactive = FALSE
        //                         WHERE procurement_id = p_id
        //                         AND item_id = (item ->> 'id')::BIGINT
        //                         AND supplier_id = (supplier ->> 'id')::BIGINT;

        //                         INSERT INTO procurement_attempt_request_items (
        //                             procurement_id, attempted_id, supplier_id, item_id,
        //                             expected_budget_per_item, requested_quantity, rfp_document,
        //                             attachment, required_date, message_to_supplier,
        //                             is_receive_quotation, is_available_on_quotation,
        //                             isactive, tenant_id, created_at, updated_at
        //                         ) VALUES (
        //                             p_id,
        //                             attempt_id,
        //                             (supplier ->> 'id')::BIGINT,
        //                             (item ->> 'id')::BIGINT,
        //                             (item ->> 'budget')::DECIMAL,
        //                             (item ->> 'quantity')::DECIMAL,
        //                             p_rfp_document,
        //                             p_attachment,
        //                             (item ->> 'required_date')::DATE,
        //                             (item ->> 'business_purpose')::TEXT,
        //                             FALSE,
        //                             FALSE,
        //                             TRUE,
        //                             p_tenant_id,
        //                             p_current_time,
        //                             p_current_time
        //                         );
        //                     END LOOP;
        //                 END LOOP;

        //                 RETURN QUERY SELECT 
        //                     'SUCCESS',
        //                     'Procurement updated successfully',
        //                     return_id,
        //                     new_procurement ->> 'request_id'::TEXT,
        //                     attempt_id;
        //             END IF;
        //         END IF;
        //     END;
        //     $$;
        // SQL);

        // DB::unprepared(<<<SQL
        //     CREATE OR REPLACE FUNCTION insert_or_update_procurement_Initiate(
        //         IN p_procurement_request_user INT,
        //         IN p_date DATE,
        //         IN p_selected_items JSONB,
        //         IN p_selected_suppliers JSONB,
        //         IN p_rfp_document JSONB,
        //         IN p_attachment JSONB,
        //         IN p_quotation_calling_closing_date DATE,
        //         IN p_comment VARCHAR(191),
        //         IN p_procurement_status VARCHAR(191),
        //         IN p_tenant_id BIGINT,
        //         IN p_current_time TIMESTAMP WITH TIME ZONE,
        //         IN p_id BIGINT DEFAULT NULL,
        //         IN p_prefix TEXT DEFAULT 'PROC'
        //     )
        //     RETURNS TABLE (
        //         status TEXT,
        //         message TEXT,
        //         procurement_id BIGINT,
        //         procurement_reg_no TEXT,
        //         attempt_id BIGINT
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     DECLARE
        //         return_id BIGINT;
        //         new_procurement_reg_no VARCHAR(50);
        //         curr_val INT;
        //         new_procurement JSONB;
        //         old_procurement JSONB;
        //         v_log_success BOOLEAN := FALSE;
        //         attempt_id BIGINT;
        //         attempt_json JSONB;
        //         supplier JSONB;
        //         item JSONB;
        //     BEGIN
        //         IF p_id IS NULL OR p_id = 0 THEN
        //             SELECT nextval('procurement_register_id_seq') INTO curr_val;
        //             new_procurement_reg_no := p_prefix || '-' || LPAD(curr_val::TEXT, 4, '0');

        //             INSERT INTO public.procurements (
        //                 request_id, created_by, quotation_request_attempt_count, procurement_status,
        //                 selected_items, tenant_id, created_at, updated_at
        //             ) VALUES (
        //                 new_procurement_reg_no, p_procurement_request_user, 1, p_procurement_status,
        //                 p_selected_items, p_tenant_id, p_current_time, p_current_time
        //             ) RETURNING id, to_jsonb(procurements.*) INTO return_id, new_procurement;

        //             BEGIN
        //                 PERFORM log_activity(
        //                     'create_procurement',
        //                     'Procurement created',
        //                     'procurement',
        //                     return_id,
        //                     'user',
        //                     p_procurement_request_user,
        //                     jsonb_build_object('after', new_procurement),
        //                     p_tenant_id
        //                 );
        //                 v_log_success := TRUE;
        //             EXCEPTION WHEN OTHERS THEN
        //                 v_log_success := FALSE;
        //             END;

        //             BEGIN
        //                 INSERT INTO procurements_quotation_request_attempts (
        //                     procurement_id, attemp_number, attempted_by, closing_date, tenant_id, created_at, updated_at
        //                 ) VALUES (
        //                     return_id, 1, p_procurement_request_user, p_quotation_calling_closing_date, p_tenant_id, p_current_time, p_current_time
        //                 ) RETURNING id, to_jsonb(procurements_quotation_request_attempts.*)
        //                 INTO attempt_id, attempt_json;

        //                 BEGIN
        //                     PERFORM log_activity(
        //                         'create_procurement_attempt',
        //                         'Quotation Request Attempt created',
        //                         'procurements_quotation_request_attempts',
        //                         attempt_id,
        //                         'user',
        //                         p_procurement_request_user,
        //                         jsonb_build_object('after', attempt_json),
        //                         p_tenant_id
        //                     );
        //                 EXCEPTION WHEN OTHERS THEN
        //                     NULL;
        //                 END;
        //             END;

        //             FOR item IN SELECT * FROM jsonb_array_elements(p_selected_items)
        //             LOOP
        //                 FOR supplier IN SELECT * FROM jsonb_array_elements(p_selected_suppliers)
        //                 LOOP
        //                     INSERT INTO procurement_attempt_request_items (
        //                         procurement_id, attempted_id, supplier_id, item_id,
        //                         expected_budget_per_item, requested_quantity, rfp_document,
        //                         attachment, required_date, message_to_supplier,
        //                         is_receive_quotation, is_available_on_quotation,
        //                         tenant_id, created_at, updated_at
        //                     ) VALUES (
        //                         return_id,
        //                         attempt_id,
        //                         (supplier ->> 'id')::BIGINT,
        //                         (item ->> 'id')::BIGINT,
        //                         (item ->> 'budget')::DECIMAL,
        //                         (item ->> 'quantity')::DECIMAL,
        //                         p_rfp_document,
        //                         p_attachment,
        //                         (item ->> 'required_date')::DATE,
        //                         (item ->> 'business_purpose')::TEXT,
        //                         FALSE,
        //                         FALSE,
        //                         p_tenant_id,
        //                         p_current_time,
        //                         p_current_time
        //                     );
        //                 END LOOP;
        //             END LOOP;

        //             RETURN QUERY SELECT 
        //                 'SUCCESS',
        //                 'Procurement added successfully',
        //                 return_id,
        //                 new_procurement_reg_no::TEXT,
        //                 attempt_id;

        //         ELSE
        //             SELECT to_jsonb(p) INTO old_procurement FROM procurements p WHERE id = p_id;

        //             SELECT COALESCE(MAX(pqra.attemp_number), 0) + 1
        //             INTO curr_val
        //             FROM procurements_quotation_request_attempts pqra
        //             WHERE pqra.procurement_id = p_id;

        //             UPDATE public.procurements
        //             SET 
        //                 procurement_status = p_procurement_status,
        //                 selected_items = p_selected_items,
        //                 tenant_id = p_tenant_id,
        //                 quotation_request_attempt_count = curr_val,
        //                 updated_at = p_current_time
        //             WHERE id = p_id
        //             RETURNING id, to_jsonb(procurements.*) INTO return_id, new_procurement;

        //             IF NOT FOUND THEN
        //                 RETURN QUERY SELECT 
        //                     'FAILURE',
        //                     format('Procurement with id %s not found', p_id),
        //                     NULL,
        //                     NULL,
        //                     NULL;
        //             ELSE
        //                 BEGIN
        //                     PERFORM log_activity(
        //                         'update_procurement',
        //                         'Procurement updated',
        //                         'procurement',
        //                         return_id,
        //                         'user',
        //                         p_procurement_request_user,
        //                         jsonb_build_object('before', old_procurement, 'after', new_procurement),
        //                         p_tenant_id
        //                     );
        //                     v_log_success := TRUE;
        //                 EXCEPTION WHEN OTHERS THEN
        //                     v_log_success := FALSE;
        //                 END;

        //                 UPDATE procurements_quotation_request_attempts pqra
        //                 SET request_attempts_status = 'EXIT'
        //                 WHERE pqra.procurement_id = p_id;

        //                 INSERT INTO procurements_quotation_request_attempts (
        //                     procurement_id, attemp_number, attempted_by, closing_date, tenant_id, created_at, updated_at
        //                 ) VALUES (
        //                     p_id,
        //                     curr_val,
        //                     p_procurement_request_user,
        //                     p_quotation_calling_closing_date,
        //                     p_tenant_id,
        //                     p_current_time,
        //                     p_current_time
        //                 )
        //                 RETURNING id, to_jsonb(procurements_quotation_request_attempts.*)
        //                 INTO attempt_id, attempt_json;

        //                 BEGIN
        //                     PERFORM log_activity(
        //                         'create_procurement_attempt',
        //                         'New attempt after update',
        //                         'procurements_quotation_request_attempts',
        //                         attempt_id,
        //                         'user',
        //                         p_procurement_request_user,
        //                         jsonb_build_object('after', attempt_json),
        //                         p_tenant_id
        //                     );
        //                 EXCEPTION WHEN OTHERS THEN
        //                     NULL;
        //                 END;

        //                 FOR item IN SELECT * FROM jsonb_array_elements(p_selected_items)
        //                 LOOP
        //                     FOR supplier IN SELECT * FROM jsonb_array_elements(p_selected_suppliers)
        //                     LOOP
        //                         UPDATE procurement_attempt_request_items pai
        //                         SET isactive = FALSE
        //                         WHERE pai.procurement_id = p_id
        //                         AND pai.item_id = (item ->> 'id')::BIGINT
        //                         AND pai.supplier_id = (supplier ->> 'id')::BIGINT;

        //                         INSERT INTO procurement_attempt_request_items (
        //                             procurement_id, attempted_id, supplier_id, item_id,
        //                             expected_budget_per_item, requested_quantity, rfp_document,
        //                             attachment, required_date, message_to_supplier,
        //                             is_receive_quotation, is_available_on_quotation,
        //                             isactive, tenant_id, created_at, updated_at
        //                         ) VALUES (
        //                             p_id,
        //                             attempt_id,
        //                             (supplier ->> 'id')::BIGINT,
        //                             (item ->> 'id')::BIGINT,
        //                             (item ->> 'budget')::DECIMAL,
        //                             (item ->> 'quantity')::DECIMAL,
        //                             p_rfp_document,
        //                             p_attachment,
        //                             (item ->> 'required_date')::DATE,
        //                             (item ->> 'business_purpose')::TEXT,
        //                             FALSE,
        //                             FALSE,
        //                             TRUE,
        //                             p_tenant_id,
        //                             p_current_time,
        //                             p_current_time
        //                         );
        //                     END LOOP;
        //                 END LOOP;

        //                 RETURN QUERY SELECT 
        //                     'SUCCESS',
        //                     'Procurement updated successfully',
        //                     return_id,
        //                     new_procurement ->> 'request_id'::TEXT,
        //                     attempt_id;
        //             END IF;
        //         END IF;
        //     END;
        //     $$;
        // SQL);

        DB::unprepared(<<<SQL
        CREATE OR REPLACE FUNCTION insert_or_update_procurement_Initiate(
            IN p_procurement_request_user INT,
            IN p_date DATE,
            IN p_selected_items JSONB,
            IN p_selected_suppliers JSONB,
            IN p_rfp_document JSONB,
            IN p_attachment JSONB,
            IN p_quotation_calling_closing_date DATE,
            IN p_comment VARCHAR(191),
            IN p_procurement_status VARCHAR(191),
            IN p_tenant_id BIGINT,
            IN p_current_time TIMESTAMP WITH TIME ZONE,
            IN p_id BIGINT DEFAULT NULL,
            IN p_prefix TEXT DEFAULT 'PROC'
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            procurement_id BIGINT,
            procurement_reg_no TEXT,
            attempt_id BIGINT
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            return_id BIGINT;
            new_procurement_reg_no VARCHAR(50);
            curr_val INT;
            new_procurement JSONB;
            old_procurement JSONB;
            v_log_success BOOLEAN := FALSE;
            attempt_id BIGINT;
            attempt_json JSONB;
            supplier JSONB;
            item JSONB;
            latest_version INT;
        BEGIN
            IF p_id IS NULL OR p_id = 0 THEN
                SELECT nextval('procurement_register_id_seq') INTO curr_val;
                new_procurement_reg_no := p_prefix || '-' || LPAD(curr_val::TEXT, 4, '0');

                INSERT INTO public.procurements (
                    request_id, created_by, quotation_request_attempt_count, procurement_status,
                    selected_items, tenant_id, created_at, updated_at
                ) VALUES (
                    new_procurement_reg_no, p_procurement_request_user, 1, p_procurement_status,
                    p_selected_items, p_tenant_id, p_current_time, p_current_time
                ) RETURNING id, to_jsonb(procurements.*) INTO return_id, new_procurement;

                BEGIN
                    PERFORM log_activity(
                        'create_procurement',
                        'Procurement created',
                        'procurement',
                        return_id,
                        'user',
                        p_procurement_request_user,
                        jsonb_build_object('after', new_procurement),
                        p_tenant_id
                    );
                    v_log_success := TRUE;
                EXCEPTION WHEN OTHERS THEN
                    v_log_success := FALSE;
                END;

                BEGIN
                    INSERT INTO procurements_quotation_request_attempts (
                        procurement_id, attemp_number, attempted_by, closing_date, tenant_id, created_at, updated_at
                    ) VALUES (
                        return_id, 1, p_procurement_request_user, p_quotation_calling_closing_date, p_tenant_id, p_current_time, p_current_time
                    ) RETURNING id, to_jsonb(procurements_quotation_request_attempts.*)
                    INTO attempt_id, attempt_json;

                    BEGIN
                        PERFORM log_activity(
                            'create_procurement_attempt',
                            'Quotation Request Attempt created',
                            'procurements_quotation_request_attempts',
                            attempt_id,
                            'user',
                            p_procurement_request_user,
                            jsonb_build_object('after', attempt_json),
                            p_tenant_id
                        );
                    EXCEPTION WHEN OTHERS THEN
                        NULL;
                    END;
                END;

                FOR item IN SELECT * FROM jsonb_array_elements(p_selected_items)
                LOOP
                    FOR supplier IN SELECT * FROM jsonb_array_elements(p_selected_suppliers)
                    LOOP
                        INSERT INTO procurement_attempt_request_items (
                            procurement_id, attempted_id, supplier_id, item_id,
                            expected_budget_per_item, requested_quantity, rfp_document,
                            attachment, required_date, message_to_supplier,
                            is_receive_quotation, is_available_on_quotation,
                            quotation_version, isactive,
                            tenant_id, created_at, updated_at
                        ) VALUES (
                            return_id,
                            attempt_id,
                            (supplier ->> 'id')::BIGINT,
                            (item ->> 'id')::BIGINT,
                            (item ->> 'budget')::DECIMAL,
                            (item ->> 'quantity')::DECIMAL,
                            p_rfp_document,
                            p_attachment,
                            (item ->> 'required_date')::DATE,
                            (item ->> 'business_purpose')::TEXT,
                            FALSE,
                            FALSE,
                            1,
                            TRUE,
                            p_tenant_id,
                            p_current_time,
                            p_current_time
                        );
                    END LOOP;
                END LOOP;

                RETURN QUERY SELECT 
                    'SUCCESS',
                    'Procurement added successfully',
                    return_id,
                    new_procurement_reg_no::TEXT,
                    attempt_id;

            ELSE
                SELECT to_jsonb(p) INTO old_procurement FROM procurements p WHERE id = p_id;

                SELECT COALESCE(MAX(pqra.attemp_number), 0) + 1
                INTO curr_val
                FROM procurements_quotation_request_attempts pqra
                WHERE pqra.procurement_id = p_id;

                UPDATE public.procurements
                SET 
                    procurement_status = p_procurement_status,
                    selected_items = p_selected_items,
                    tenant_id = p_tenant_id,
                    quotation_request_attempt_count = curr_val,
                    updated_at = p_current_time
                WHERE id = p_id
                RETURNING id, to_jsonb(procurements.*) INTO return_id, new_procurement;

                IF NOT FOUND THEN
                    RETURN QUERY SELECT 
                        'FAILURE',
                        format('Procurement with id %s not found', p_id),
                        NULL,
                        NULL,
                        NULL;
                ELSE
                    BEGIN
                        PERFORM log_activity(
                            'update_procurement',
                            'Procurement updated',
                            'procurement',
                            return_id,
                            'user',
                            p_procurement_request_user,
                            jsonb_build_object('before', old_procurement, 'after', new_procurement),
                            p_tenant_id
                        );
                        v_log_success := TRUE;
                    EXCEPTION WHEN OTHERS THEN
                        v_log_success := FALSE;
                    END;

                    UPDATE procurements_quotation_request_attempts pqra
                    SET request_attempts_status = 'EXIT'
                    WHERE pqra.procurement_id = p_id;

                    INSERT INTO procurements_quotation_request_attempts (
                        procurement_id, attemp_number, attempted_by, closing_date, tenant_id, created_at, updated_at
                    ) VALUES (
                        p_id,
                        curr_val,
                        p_procurement_request_user,
                        p_quotation_calling_closing_date,
                        p_tenant_id,
                        p_current_time,
                        p_current_time
                    )
                    RETURNING id, to_jsonb(procurements_quotation_request_attempts.*)
                    INTO attempt_id, attempt_json;

                    BEGIN
                        PERFORM log_activity(
                            'create_procurement_attempt',
                            'New attempt after update',
                            'procurements_quotation_request_attempts',
                            attempt_id,
                            'user',
                            p_procurement_request_user,
                            jsonb_build_object('after', attempt_json),
                            p_tenant_id
                        );
                    EXCEPTION WHEN OTHERS THEN
                        NULL;
                    END;

                    FOR item IN SELECT * FROM jsonb_array_elements(p_selected_items)
                    LOOP
                        FOR supplier IN SELECT * FROM jsonb_array_elements(p_selected_suppliers)
                        LOOP
                            UPDATE procurement_attempt_request_items pai
                            SET isactive = FALSE
                            WHERE pai.procurement_id = p_id
                            AND pai.item_id = (item ->> 'id')::BIGINT
                            AND pai.supplier_id = (supplier ->> 'id')::BIGINT;

                            SELECT COALESCE(MAX(pai.quotation_version), 0)
                            INTO latest_version
                            FROM procurement_attempt_request_items pai
                            WHERE pai.procurement_id = p_id
                            AND pai.item_id = (item ->> 'id')::BIGINT
                            AND pai.supplier_id = (supplier ->> 'id')::BIGINT;

                            INSERT INTO procurement_attempt_request_items (
                                procurement_id, attempted_id, supplier_id, item_id,
                                expected_budget_per_item, requested_quantity, rfp_document,
                                attachment, required_date, message_to_supplier,
                                is_receive_quotation, is_available_on_quotation,
                                isactive, quotation_version,
                                tenant_id, created_at, updated_at
                            ) VALUES (
                                p_id,
                                attempt_id,
                                (supplier ->> 'id')::BIGINT,
                                (item ->> 'id')::BIGINT,
                                (item ->> 'budget')::DECIMAL,
                                (item ->> 'quantity')::DECIMAL,
                                p_rfp_document,
                                p_attachment,
                                (item ->> 'required_date')::DATE,
                                (item ->> 'business_purpose')::TEXT,
                                FALSE,
                                FALSE,
                                TRUE,
                                latest_version + 1,
                                p_tenant_id,
                                p_current_time,
                                p_current_time
                            );
                        END LOOP;
                    END LOOP;

                    RETURN QUERY SELECT 
                        'SUCCESS',
                        'Procurement updated successfully',
                        return_id,
                        new_procurement ->> 'request_id'::TEXT,
                        attempt_id;
                END IF;
            END IF;
        END;
        $$;
        SQL);

    }

    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS insert_or_update_procurement_Initiate(
            INT, DATE, JSONB, JSONB, JSONB, JSONB, DATE, VARCHAR, VARCHAR, BIGINT, BIGINT, TEXT);");
    }
};