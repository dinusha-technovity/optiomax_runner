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
        DB::unprepared("DROP FUNCTION IF EXISTS insert_or_update_procurement_Initiate(
            INT, DATE, JSONB, JSONB, JSONB, JSONB, DATE, VARCHAR, VARCHAR, BIGINT, BIGINT);");

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION insert_or_update_procurement_Initiate(
                IN p_procurement_request_user INT,
                IN p_date DATE,
                IN p_selected_items JSONB,
                IN p_selected_suppliers JSONB,
                IN p_rpf_document JSONB,
                IN p_attachment JSONB,
                IN p_required_date DATE,
                IN p_comment VARCHAR(191),
                IN p_procurement_status VARCHAR(191),
                IN p_tenant_id BIGINT,
                IN p_id BIGINT DEFAULT NULL,
                IN p_prefix TEXT DEFAULT 'PROC'
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                procurement_id BIGINT,
                procurement_reg_no TEXT
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
            BEGIN
                IF p_id IS NULL OR p_id = 0 THEN
                    SELECT nextval('procurement_register_id_seq') INTO curr_val;
                    new_procurement_reg_no := p_prefix || '-' || LPAD(curr_val::TEXT, 4, '0');
                END IF;

                IF p_id IS NULL OR p_id = 0 THEN
                    INSERT INTO public.procurements (
                        request_id, procurement_by, date, selected_items, selected_suppliers, rpf_document,
                        attachment, required_date, comment, procurement_status, tenant_id,
                        created_at, updated_at
                    ) VALUES (
                        new_procurement_reg_no, p_procurement_request_user, p_date, p_selected_items, p_selected_suppliers, p_rpf_document,
                        p_attachment, p_required_date, p_comment, p_procurement_status, p_tenant_id,
                        NOW(), NOW()
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

                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT,
                        'Procurement added successfully'::TEXT,
                        return_id,
                        new_procurement_reg_no::TEXT;

                ELSE
                    SELECT to_jsonb(p) INTO old_procurement FROM procurements p WHERE id = p_id;

                    UPDATE public.procurements
                    SET 
                        procurement_by = p_procurement_request_user,
                        date = p_date,
                        selected_items = p_selected_items,
                        selected_suppliers = p_selected_suppliers,
                        rpf_document = p_rpf_document,
                        attachment = p_attachment,
                        required_date = p_required_date,
                        comment = p_comment,
                        procurement_status = p_procurement_status,
                        tenant_id = p_tenant_id,
                        updated_at = NOW()
                    WHERE id = p_id
                    RETURNING id, to_jsonb(procurements.*) INTO return_id, new_procurement;

                    IF NOT FOUND THEN
                        RETURN QUERY SELECT 
                            'FAILURE'::TEXT,
                            format('Procurement with id %s not found', p_id)::TEXT,
                            NULL::BIGINT,
                            NULL::TEXT;
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

                        RETURN QUERY SELECT 
                            'SUCCESS'::TEXT,
                            'Procurement updated successfully'::TEXT,
                            return_id,
                            new_procurement ->> 'request_id';
                    END IF;
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
        DB::unprepared("DROP FUNCTION IF EXISTS insert_or_update_procurement_Initiate(
            INT, DATE, JSONB, JSONB, JSONB, JSONB, DATE, VARCHAR, VARCHAR, BIGINT, BIGINT, TEXT);");
    }
};