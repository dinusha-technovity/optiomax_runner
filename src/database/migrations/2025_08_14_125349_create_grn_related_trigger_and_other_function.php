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
        DB::unprepared(<<<'SQL'
        -- Trigger function: update stock & alerts AFTER INSERT on inventory_movements
        CREATE OR REPLACE FUNCTION update_stock_and_alerts()
        RETURNS TRIGGER AS $$
        DECLARE
            v_item_qty INT;
            v_min INT;
            v_max INT;
            v_reorder INT;
            v_low_flag BOOLEAN;
            v_over_flag BOOLEAN;
            v_severity TEXT;
        BEGIN
            -- Upsert stock per batch
            INSERT INTO item_stock_levels(item_id, tenant_id, lot_number, expiration_date, quantity, created_at, updated_at)
            VALUES (
                NEW.item_id,
                NEW.tenant_id,
                NEW.lot_number,
                NEW.expiration_date,
                CASE WHEN NEW.movement_type = 'IN' THEN NEW.quantity ELSE -NEW.quantity END,
                now(), now()
            )
            ON CONFLICT (item_id, tenant_id, lot_number, expiration_date)
            DO UPDATE SET
                quantity = item_stock_levels.quantity +
                    CASE WHEN NEW.movement_type = 'IN' THEN EXCLUDED.quantity ELSE EXCLUDED.quantity END,
                updated_at = now();

            -- Aggregate total qty for the item/tenant
            SELECT COALESCE(SUM(quantity),0) INTO v_item_qty
            FROM item_stock_levels
            WHERE item_id = NEW.item_id AND tenant_id = NEW.tenant_id;

            -- Load thresholds
            SELECT min_inventory_level, max_inventory_level, re_order_level, low_stock_alert, over_stock_alert
            INTO v_min, v_max, v_reorder, v_low_flag, v_over_flag
            FROM items WHERE id = NEW.item_id;

            -- Low stock (CRITICAL if < min, WARNING if <= reorder)
            IF v_low_flag IS TRUE THEN
                IF v_min IS NOT NULL AND v_item_qty < v_min THEN
                    v_severity := 'CRITICAL';
                    INSERT INTO item_alerts(tenant_id,item_id,alert_type,severity,message,created_at,updated_at)
                    VALUES (NEW.tenant_id, NEW.item_id, 'LOW_STOCK', v_severity,
                            'Stock below minimum ('||v_item_qty||' < '||v_min||')', now(), now());
                ELSIF v_reorder IS NOT NULL AND v_item_qty <= v_reorder THEN
                    v_severity := 'WARNING';
                    INSERT INTO item_alerts(tenant_id,item_id,alert_type,severity,message,created_at,updated_at)
                    VALUES (NEW.tenant_id, NEW.item_id, 'LOW_STOCK', v_severity,
                            'Stock at/below reorder ('||v_item_qty||' <= '||v_reorder||')', now(), now());
                END IF;
            END IF;

            -- Over stock
            IF v_over_flag IS TRUE AND v_max IS NOT NULL AND v_item_qty > v_max THEN
                INSERT INTO item_alerts(tenant_id,item_id,alert_type,severity,message,created_at,updated_at)
                VALUES (NEW.tenant_id, NEW.item_id, 'OVER_STOCK', 'WARNING',
                        'Stock above max ('||v_item_qty||' > '||v_max||')', now(), now());
            END IF;

            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;

        -- Attach trigger
        DROP TRIGGER IF EXISTS trg_update_stock_and_alerts ON inventory_movements;
        CREATE TRIGGER trg_update_stock_and_alerts
        AFTER INSERT ON inventory_movements
        FOR EACH ROW
        EXECUTE FUNCTION update_stock_and_alerts();


        DROP FUNCTION IF EXISTS insert_or_update_grn_function(TEXT, BIGINT, DATE, JSONB, BIGINT, BIGINT, VARCHAR, TIMESTAMPTZ, TEXT);
        
        -- Lean GRN function: inserts header/lines/movements; trigger handles stock+alerts
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
            v_grn_line_id BIGINT;
        BEGIN
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT 'FAILURE', 'Invalid tenant ID provided', NULL::BIGINT; RETURN;
            END IF;
            IF p_lines IS NULL OR jsonb_typeof(p_lines) <> 'array' OR jsonb_array_length(p_lines) = 0 THEN
                RETURN QUERY SELECT 'FAILURE', 'GRN lines cannot be empty', NULL::BIGINT; RETURN;
            END IF;

            SELECT nextval('grn_number_seq') INTO v_seq_val;
            v_grn_number := p_prefix || '-' || to_char(p_current_time, 'YYYYMM') || '-' || LPAD(v_seq_val::TEXT, 4, '0');

            INSERT INTO goods_received_note(
                tenant_id, grn_number, purchasing_number, supplier_id,
                receipt_date, status, total_amount, created_at, updated_at
            ) VALUES (
                p_tenant_id, v_grn_number, p_purchasing_number, p_supplier_id,
                p_receipt_date, 'posted', 0, p_current_time, p_current_time
            ) RETURNING id INTO v_grn_id;

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
                ) RETURNING id INTO v_grn_line_id;

                v_sum := v_sum + ((line->>'quantity')::INT) * ((line->>'unit_price')::NUMERIC);

                -- Insert movement; trigger will update stock & alerts
                INSERT INTO inventory_movements(
                    tenant_id, item_id, grn_line_id,
                    movement_type, movement_reason, quantity, movement_date,
                    reference, lot_number, expiration_date, created_at, updated_at
                )
                VALUES (
                    p_tenant_id,
                    (line->>'id')::BIGINT,
                    v_grn_line_id,
                    'IN',
                    'Goods Receipt',
                    (line->>'quantity')::INT,
                    p_current_time,
                    v_grn_number,
                    (line->>'lot_number'),
                    (line->>'expiration_date')::DATE,
                    p_current_time, p_current_time
                );
            END LOOP;

            UPDATE goods_received_note
            SET total_amount = v_sum, updated_at = p_current_time
            WHERE id = v_grn_id;

            IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
                PERFORM log_activity(
                    'grn.created',
                    'GRN created by ' || p_user_name || ': ' || v_grn_number,
                    'grn',
                    v_grn_id,
                    'user',
                    p_user_id,
                    jsonb_build_object('grn_id', v_grn_id, 'total_amount', v_sum, 'lines', p_lines),
                    p_tenant_id
                );
            END IF;

            RETURN QUERY SELECT 'SUCCESS', 'GRN created successfully', v_grn_id;
        END;
        $$;


        -- Views for reporting
        CREATE OR REPLACE VIEW v_item_stock_by_batch AS
        SELECT
            isl.tenant_id,
            isl.item_id,
            i.item_name,
            isl.lot_number,
            isl.expiration_date,
            isl.quantity
        FROM item_stock_levels isl
        JOIN items i ON i.id = isl.item_id;

        CREATE OR REPLACE VIEW v_item_stock_balances AS
        SELECT
            isl.tenant_id,
            isl.item_id,
            i.item_name,
            COALESCE(SUM(isl.quantity),0) AS total_qty
        FROM item_stock_levels isl
        JOIN items i ON i.id = isl.item_id
        GROUP BY isl.tenant_id, isl.item_id, i.item_name;

        CREATE OR REPLACE VIEW v_item_stock_status AS
        SELECT
            b.tenant_id,
            b.item_id,
            b.item_name,
            b.total_qty,
            i.min_inventory_level,
            i.re_order_level,
            i.max_inventory_level,
            CASE 
                WHEN i.min_inventory_level IS NOT NULL AND b.total_qty < i.min_inventory_level THEN 'LOW_STOCK'
                WHEN i.max_inventory_level IS NOT NULL AND b.total_qty > i.max_inventory_level THEN 'OVER_STOCK'
                WHEN i.re_order_level IS NOT NULL AND b.total_qty <= i.re_order_level THEN 'REORDER'
                ELSE 'NORMAL'
            END AS stock_status
        FROM v_item_stock_balances b
        JOIN items i ON i.id = b.item_id;

        CREATE OR REPLACE VIEW v_item_expiry_aging AS
        SELECT
            isl.tenant_id,
            isl.item_id,
            i.item_name,
            isl.lot_number,
            isl.expiration_date,
            isl.quantity,
            CASE 
                WHEN isl.expiration_date IS NULL THEN NULL
                ELSE (isl.expiration_date - CURRENT_DATE)
            END AS days_to_expiry
        FROM item_stock_levels isl
        JOIN items i ON i.id = isl.item_id;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DROP VIEW IF EXISTS v_item_expiry_aging;
        DROP VIEW IF EXISTS v_item_stock_status;
        DROP VIEW IF EXISTS v_item_stock_balances;
        DROP VIEW IF EXISTS v_item_stock_by_batch;
        DROP FUNCTION IF EXISTS insert_or_update_grn_function(TEXT, BIGINT, DATE, JSONB, BIGINT, BIGINT, VARCHAR, TIMESTAMPTZ, TEXT);
        DROP TRIGGER IF EXISTS trg_update_stock_and_alerts ON inventory_movements;
        DROP FUNCTION IF EXISTS update_stock_and_alerts();
        SQL);
    }
};
