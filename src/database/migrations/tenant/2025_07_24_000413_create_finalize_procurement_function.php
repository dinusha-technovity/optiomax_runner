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
            CREATE OR REPLACE FUNCTION finalize_procurement(
                IN p_id BIGINT,
                IN p_selected_items JSONB,
                IN p_procurement_status VARCHAR(191),
                IN p_updated_at TIMESTAMP,
                IN p_user_id INT,
                IN p_tenant_id BIGINT,
                OUT status TEXT,
                OUT message TEXT,
                OUT procurement_id BIGINT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                old_data JSONB;
                new_data JSONB;
                log_success BOOLEAN := FALSE;
                item JSONB;
                v_item_id BIGINT;
                v_supplier_id BIGINT;
            BEGIN
                SELECT to_jsonb(p) INTO old_data
                FROM procurements p
                WHERE p.id = p_id;

                IF NOT FOUND THEN
                    status := 'FAILURE';
                    message := format('Procurement with id %s not found', p_id);
                    procurement_id := NULL;
                    RETURN;
                END IF;

                UPDATE procurements
                SET
                    selected_items = p_selected_items,
                    procurement_status = p_procurement_status,
                    updated_at = p_updated_at
                WHERE id = p_id
                RETURNING id, to_jsonb(procurements.*) INTO procurement_id, new_data;

                FOR item IN SELECT * FROM jsonb_array_elements(p_selected_items)
                LOOP
                    v_item_id := (item ->> 'item_id')::BIGINT;
                    v_supplier_id := (item ->> 'supplier_id')::BIGINT;

                    UPDATE procurement_attempt_request_items AS pari
                    SET is_selected_for_finalization = TRUE
                    WHERE pari.procurement_id = p_id
                    AND pari.item_id = v_item_id
                    AND pari.supplier_id = v_supplier_id;
                END LOOP;

                BEGIN
                    PERFORM log_activity(
                        'finalize_procurement',
                        'Procurement finalized',
                        'procurement',
                        procurement_id,
                        'user',
                        p_user_id,
                        jsonb_build_object('before', old_data, 'after', new_data),
                        p_tenant_id
                    );
                    log_success := TRUE;
                EXCEPTION WHEN OTHERS THEN
                    log_success := FALSE;
                END;

                status := 'SUCCESS';
                message := 'Procurement finalized and saved';
                RETURN;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS finalize_procurement;");
    }
};