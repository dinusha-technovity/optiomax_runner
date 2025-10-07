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
            -- Helper: allocate items for a given WO (can be reused)
            CREATE OR REPLACE FUNCTION item_stock_allocate_existing_work_order(
                IN p_work_order_id BIGINT,
                IN p_tenant_id BIGINT,
                IN p_user_id BIGINT DEFAULT NULL,
                IN p_user_name VARCHAR DEFAULT NULL,
                IN p_now TIMESTAMPTZ DEFAULT now()
            ) RETURNS TABLE(status TEXT, message TEXT) LANGUAGE plpgsql AS $$
            DECLARE
                r_item RECORD;
                remaining_req NUMERIC;
                r_batch RECORD;
                v_wo_number TEXT;
                v_mov_id BIGINT;
            BEGIN
                -- Get WO number (for movement reference)
                SELECT work_order_number INTO v_wo_number FROM work_orders WHERE id = p_work_order_id AND tenant_id = p_tenant_id;

                IF v_wo_number IS NULL THEN
                    RETURN QUERY SELECT 'FAILURE', 'Work order not found or tenant mismatch';
                    RETURN;
                END IF;

                -- Iterate requested items for this WO
                FOR r_item IN
                    SELECT wori.id AS work_order_item_id,
                        wori.item_id,
                        COALESCE(wori.requested_qty,0) - COALESCE(wori.fulfilled_qty,0) AS to_allocate
                    FROM work_orders_related_requested_item wori
                    WHERE wori.work_order_id = p_work_order_id
                    AND wori.tenant_id = p_tenant_id
                    AND (COALESCE(wori.requested_qty,0) - COALESCE(wori.fulfilled_qty,0)) > 0
                LOOP
                    remaining_req := r_item.to_allocate;

                    -- FIFO over current stock (from item_stock_levels)
                    FOR r_batch IN
                        SELECT lot_number, expiration_date, quantity
                        FROM item_stock_levels
                        WHERE item_id = r_item.item_id
                        AND tenant_id = p_tenant_id
                        AND quantity > 0
                        ORDER BY expiration_date NULLS LAST, lot_number NULLS FIRST
                    LOOP
                        EXIT WHEN remaining_req <= 0;

                        IF r_batch.quantity >= remaining_req THEN
                            -- Create OUT movement for the exact needed qty
                            INSERT INTO inventory_movements(
                                item_id, grn_line_id, movement_type, movement_reason,
                                quantity, movement_date, reference, lot_number, expiration_date,
                                deleted_at, isactive, tenant_id, created_at, updated_at
                            ) VALUES (
                                r_item.item_id, NULL, 'OUT', 'Work Order',
                                CEIL(remaining_req)::INT, p_now, v_wo_number, r_batch.lot_number, r_batch.expiration_date,
                                NULL, TRUE, p_tenant_id, p_now, p_now
                            ) RETURNING id INTO v_mov_id;

                            -- Link allocation
                            INSERT INTO work_order_item_allocations(work_order_item_id, inventory_movement_id, allocated_qty, tenant_id, created_at, updated_at)
                            VALUES (r_item.work_order_item_id, v_mov_id, remaining_req, p_tenant_id, p_now, p_now);

                            -- Update fulfilled on WO item
                            UPDATE work_orders_related_requested_item
                            SET fulfilled_qty = COALESCE(fulfilled_qty,0) + remaining_req,
                                updated_at = p_now
                            WHERE id = r_item.work_order_item_id;

                            remaining_req := 0;
                        ELSE
                            -- Consume the whole batch, create OUT for r_batch.quantity
                            INSERT INTO inventory_movements(
                                item_id, grn_line_id, movement_type, movement_reason,
                                quantity, movement_date, reference, lot_number, expiration_date,
                                deleted_at, isactive, tenant_id, created_at, updated_at
                            ) VALUES (
                                r_item.item_id, NULL, 'OUT', 'Work Order',
                                CEIL(r_batch.quantity)::INT, p_now, v_wo_number, r_batch.lot_number, r_batch.expiration_date,
                                NULL, TRUE, p_tenant_id, p_now, p_now
                            ) RETURNING id INTO v_mov_id;

                            INSERT INTO work_order_item_allocations(work_order_item_id, inventory_movement_id, allocated_qty, tenant_id, created_at, updated_at)
                            VALUES (r_item.work_order_item_id, v_mov_id, r_batch.quantity, p_tenant_id, p_now, p_now);

                            UPDATE work_orders_related_requested_item
                            SET fulfilled_qty = COALESCE(fulfilled_qty,0) + r_batch.quantity,
                                updated_at = p_now
                            WHERE id = r_item.work_order_item_id;

                            remaining_req := remaining_req - r_batch.quantity;
                        END IF;
                    END LOOP;

                    -- If still short -> create alert
                    IF remaining_req > 0 THEN
                        INSERT INTO item_alerts(tenant_id, item_id, alert_type, severity, message, created_at, updated_at)
                        VALUES (
                            p_tenant_id, r_item.item_id, 'LOW_STOCK', 'CRITICAL',
                            'Insufficient stock for Work Order '||v_wo_number||'. Missing qty: '||remaining_req,
                            p_now, p_now
                        );
                    END IF;
                END LOOP;

                -- Optional: log activity
                IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
                    PERFORM log_activity(
                        'workorder.allocate',
                        'Allocation executed for WO '||v_wo_number||' by '||p_user_name,
                        'work_order',
                        p_work_order_id,
                        'user',
                        p_user_id,
                        jsonb_build_object('work_order_id', p_work_order_id),
                        p_tenant_id
                    );
                END IF;

                RETURN QUERY SELECT 'SUCCESS', 'Allocation completed';
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP FUNCTION IF EXISTS item_stock_allocate_existing_work_order(BIGINT,BIGINT,BIGINT,VARCHAR,TIMESTAMPTZ);
        SQL);
    }
};
