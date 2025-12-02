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
            -- Drop existing procedures and/or functions with the given name
            FOR r IN
                SELECT
                    p.prokind,
                    format('%I.%I(%s)', n.nspname, p.proname, pg_get_function_identity_arguments(p.oid)) AS identity_signature
                FROM pg_proc p
                JOIN pg_namespace n ON n.oid = p.pronamespace
                WHERE p.proname = 'set_pending_quantity_in_po'
            LOOP
                IF r.prokind = 'p' THEN
                    EXECUTE format('DROP PROCEDURE %s CASCADE;', r.identity_signature);
                ELSIF r.prokind = 'f' THEN
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.identity_signature);
                END IF;
            END LOOP;
        END$$;

        CREATE OR REPLACE PROCEDURE set_pending_quantity_in_po(
            p_queue_id BIGINT,
            p_request_type INTEGER
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_data JSONB;
            v_item JSONB;
            v_quantity NUMERIC;
            v_finalized_item_id BIGINT;
        BEGIN
            -- Fetch the requisition_data_object JSON
            SELECT requisition_data_object::jsonb
            INTO v_data
            FROM workflow_request_queues
            WHERE id = p_queue_id
            AND workflow_request_type = p_request_type;

            IF v_data IS NULL THEN
                RAISE EXCEPTION 'No requisition_data_object found for workflow queue id %', p_queue_id;
            END IF;

            -- Loop through items inside JSON: responseData -> items
            FOR v_item IN
                SELECT jsonb_array_elements(v_data -> 'responseData' -> 'items')
            LOOP
                -- Extract fields
                v_quantity := (v_item ->> 'quantity')::NUMERIC;
                v_finalized_item_id := (v_item ->> 'procurement_finalized_item')::BIGINT;

                IF v_finalized_item_id IS NULL THEN
                    RAISE NOTICE 'Skipping item: procurement_finalized_item is NULL';
                    CONTINUE;
                END IF;

                -- Update procurement_finalize_items table
                UPDATE procurement_finalize_items
                SET 
                    pending_purchasing_qty = COALESCE(pending_purchasing_qty, 0) + v_quantity,
                    -- finalized_qty = COALESCE(finalized_qty, 0) - v_quantity,
                    updated_at = NOW()
                WHERE id = v_finalized_item_id;

                IF NOT FOUND THEN
                    RAISE NOTICE 'No matching procurement_finalize_item for ID %', v_finalized_item_id;
                END IF;

            END LOOP;
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
            -- Drop existing procedures and/or functions with the given name
            FOR r IN
                SELECT
                    p.prokind,
                    format('%I.%I(%s)', n.nspname, p.proname, pg_get_function_identity_arguments(p.oid)) AS identity_signature
                FROM pg_proc p
                JOIN pg_namespace n ON n.oid = p.pronamespace
                WHERE p.proname = 'set_pending_quantity_in_po'
            LOOP
                IF r.prokind = 'p' THEN
                    EXECUTE format('DROP PROCEDURE %s CASCADE;', r.identity_signature);
                ELSIF r.prokind = 'f' THEN
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.identity_signature);
                END IF;
            END LOOP;
        END$$;
        SQL);
    }
};
