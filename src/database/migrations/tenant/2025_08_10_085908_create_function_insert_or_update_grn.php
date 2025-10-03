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
            CREATE OR REPLACE FUNCTION insert_or_update_grn_function(
                IN p_purchasing_number TEXT,
                IN p_supplier_id BIGINT,
                IN p_receipt_date DATE,
                IN p_lines JSONB,
                IN p_tenant_id BIGINT,
                IN p_user_id BIGINT DEFAULT NULL,
                IN p_user_name VARCHAR DEFAULT NULL,
                IN p_current_time TIMESTAMPTZ DEFAULT now(),
                IN p_prefix TEXT DEFAULT 'GRN' 
                ) RETURNS TABLE (
                status TEXT,
                message TEXT,
                grn_id BIGINT
                ) LANGUAGE plpgsql AS $$
                DECLARE
                v_grn_id BIGINT;
                v_grn_number TEXT;
                v_seq_val BIGINT;
                line JSONB;
                v_sum NUMERIC := 0;
                v_new_data JSONB;
                v_log_data JSONB;
                v_log_success BOOLEAN;
                v_error_message TEXT;
                BEGIN
                -- Validate tenant_id
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Invalid tenant ID provided', NULL::BIGINT;
                    RETURN;
                END IF;

                -- Validate GRN lines
                IF p_lines IS NULL OR jsonb_typeof(p_lines) <> 'array' OR jsonb_array_length(p_lines) = 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'GRN lines cannot be empty', NULL::BIGINT;
                    RETURN;
                END IF;

                -- Auto-generate GRN number
                SELECT nextval('grn_number_seq') INTO v_seq_val;
                v_grn_number := p_prefix || '-' || to_char(p_current_time, 'YYYYMM') || '-' || LPAD(v_seq_val::TEXT, 4, '0');

                -- Insert GRN header
                INSERT INTO goods_received_note(
                    tenant_id, grn_number, purchasing_number, supplier_id,
                    receipt_date, status, total_amount, created_at, updated_at
                ) VALUES (
                    p_tenant_id, v_grn_number, p_purchasing_number, p_supplier_id,
                    p_receipt_date, 'posted', 0, p_current_time, p_current_time
                ) RETURNING id INTO v_grn_id;

                -- Insert GRN lines
                FOR line IN SELECT * FROM jsonb_array_elements(p_lines) LOOP
                    INSERT INTO grn_lines(
                    grn_id, item_id, received_qty, unit_price,
                    currency_id, line_total, lot_number, expiration_date,
                    tenant_id, created_at, updated_at
                    ) VALUES (
                    v_grn_id,
                    (line->>'id')::BIGINT,
                    (line->>'quantity')::INT,
                    (line->>'unit_price')::NUMERIC,
                    (line->>'currency_id')::BIGINT,
                    ((line->>'quantity')::INT) * ((line->>'unit_price')::NUMERIC),
                    (line->>'lot_number'),
                    (line->>'expiration_date')::DATE,
                    p_tenant_id, p_current_time, p_current_time
                    );
                    v_sum := v_sum + ((line->>'quantity')::INT) * ((line->>'unit_price')::NUMERIC);
                END LOOP;

                -- Update GRN total amount
                UPDATE goods_received_note
                SET total_amount = v_sum,
                    updated_at = p_current_time
                WHERE id = v_grn_id;

                -- Insert inventory movements
                INSERT INTO inventory_movements(
                    tenant_id, item_id, grn_line_id,
                    movement_type, quantity, movement_date, reference, created_at, updated_at
                )
                SELECT
                    p_tenant_id,
                    gl.item_id,
                    gl.id,
                    'IN',
                    gl.received_qty,
                    p_current_time,
                    v_grn_number,
                    p_current_time, p_current_time
                FROM grn_lines gl WHERE gl.grn_id = v_grn_id;

                -- Build new data snapshot
                v_new_data := jsonb_build_object(
                    'grn_id', v_grn_id,
                    'tenant_id', p_tenant_id,
                    'grn_number', v_grn_number,
                    'purchasing_number', p_purchasing_number,
                    'supplier_id', p_supplier_id,
                    'receipt_date', p_receipt_date,
                    'status', 'posted',
                    'total_amount', v_sum,
                    'lines', p_lines
                );

                v_log_data := jsonb_build_object(
                    'grn_id', v_grn_id,
                    'new_data', v_new_data
                );

                -- Log activity if user info provided
                IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
                    BEGIN
                    PERFORM log_activity(
                        'grn.created',
                        'GRN created by ' || p_user_name || ': ' || v_grn_number,
                        'grn',
                        v_grn_id,
                        'user',
                        p_user_id,
                        v_log_data,
                        p_tenant_id
                    );
                    v_log_success := TRUE;
                    EXCEPTION WHEN OTHERS THEN
                    v_log_success := FALSE;
                    v_error_message := 'Logging failed: ' || SQLERRM;
                    END;
                END IF;

                RETURN QUERY SELECT 'SUCCESS', 'GRN created successfully', v_grn_id;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS insert_or_update_grn_function');
    }
};