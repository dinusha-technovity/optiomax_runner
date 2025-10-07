<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<SQL
        CREATE OR REPLACE FUNCTION insert_or_update_quotation_feedback(
            IN p_date DATE,
            IN p_procurement_id INT,
            IN p_selected_supplier_id INT,
            IN p_selected_items JSONB,
            IN p_required_date DATE,
            IN p_feedback_fill_by INT,
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
        AS \$\$
        DECLARE
            return_id BIGINT;
            inserted_row JSONB;
            updated_row JSONB;
        BEGIN
            IF p_id IS NULL OR p_id = 0 THEN
                INSERT INTO public.quotation_feedbacks (
                    date, procurement_id, selected_supplier_id, selected_items, available_date, feedback_fill_by,
                    created_at, updated_at
                ) VALUES (
                    p_date, p_procurement_id, p_selected_supplier_id, p_selected_items, p_required_date, p_feedback_fill_by,
                    p_current_time, p_current_time
                ) RETURNING id, to_jsonb(quotation_feedbacks) INTO return_id, inserted_row;

                BEGIN
                    PERFORM log_activity(
                        'insert_quotation_feedback',
                        format('Inserted quotation feedback with ID %s by %s', return_id, p_causer_name),
                        'quotation_feedbacks',
                        return_id,
                        'user',
                        p_causer_id,
                        inserted_row,
                        p_tenant_id
                    );
                EXCEPTION WHEN OTHERS THEN NULL;
                END;

                RETURN QUERY SELECT 'SUCCESS', 'Quotation submited successfully', return_id;

            ELSE
                UPDATE public.quotation_feedbacks
                SET 
                    date = p_date,
                    procurement_id = p_procurement_id,
                    selected_supplier_id = p_selected_supplier_id,
                    selected_items = p_selected_items,
                    available_date = p_required_date, 
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
        \$\$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS insert_or_update_quotation_feedback(
            DATE, INT, INT, JSONB, DATE, INT, BIGINT, BIGINT, TEXT, BIGINT, TIMESTAMPTZ
        );");
    }
};