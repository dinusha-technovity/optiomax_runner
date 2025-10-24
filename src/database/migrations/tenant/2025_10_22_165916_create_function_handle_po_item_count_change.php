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
                    SELECT oid::regprocedure::text AS proc_signature
                    FROM pg_proc
                    WHERE proname = 'handle_po_item_count_change'
                LOOP
                    EXECUTE format('DROP PROCEDURE %s CASCADE;', r.proc_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE PROCEDURE handle_po_item_count_change(
                IN p_po_item_id BIGINT,
                IN p_quantity INTEGER,
                IN p_type TEXT,
                IN p_tenant_id BIGINT,
                IN p_current_time TIMESTAMPTZ
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_finalize_item_id BIGINT;
            BEGIN
                -- Allow p_po_item_id to be either a procurement_finalize_items.id or a purchasing_order_items.id
                -- 1) If it's already a finalize item id, use it directly
                IF EXISTS (
                    SELECT 1 FROM procurement_finalize_items
                    WHERE id = p_po_item_id AND isactive = true
                ) THEN
                    v_finalize_item_id := p_po_item_id;
                ELSE
                    -- 2) Otherwise, try to resolve from purchasing_order_items
                    SELECT procurement_finalized_item
                    INTO v_finalize_item_id
                    FROM purchasing_order_items
                    WHERE id = p_po_item_id AND isactive = true;
                END IF;

                IF v_finalize_item_id IS NULL THEN
                    RAISE NOTICE 'No matching procurement_finalized_item found for PO item ID %', p_po_item_id;
                    RETURN;
                END IF;

                -- 2 Update pending_purchasing_qty based on type
                IF p_type = 'add' THEN
                    UPDATE procurement_finalize_items
                    SET pending_purchasing_qty = COALESCE(pending_purchasing_qty, 0) + p_quantity,
                        updated_at = p_current_time
                    WHERE id = v_finalize_item_id
                    AND isactive = true;
                ELSIF p_type = 'subtract' THEN
                    UPDATE procurement_finalize_items
                    SET pending_purchasing_qty = GREATEST(COALESCE(pending_purchasing_qty, 0) - p_quantity, 0),
                        updated_at = p_current_time
                    WHERE id = v_finalize_item_id
                    AND isactive = true;
                ELSE
                    RAISE NOTICE 'Unknown p_type: %, skipping update.', p_type;
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
        DB::unprepared(<<<SQL
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS proc_signature
                    FROM pg_proc
                    WHERE proname = 'handle_po_item_count_change'
                LOOP
                    EXECUTE format('DROP PROCEDURE %s CASCADE;', r.proc_signature);
                END LOOP;
            END$$;
        SQL);
    }
};
