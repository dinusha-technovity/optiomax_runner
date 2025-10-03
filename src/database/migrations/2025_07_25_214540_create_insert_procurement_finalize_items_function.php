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
            CREATE OR REPLACE FUNCTION insert_procurement_finalize_items_from_json(
                IN p_data JSONB,
                IN p_user_id BIGINT,
                IN p_current_time TIMESTAMPTZ DEFAULT now()
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                inserted_count INTEGER
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                supplier JSONB;
                inserted_rows INT := 0;
                procurement_id BIGINT;
                asset_requisition_id BIGINT;
                new_id BIGINT;
                row_json JSONB;
            BEGIN
                procurement_id := (p_data -> 'procurement_context' ->> 'procurement_id')::BIGINT;

                FOR supplier IN SELECT * FROM jsonb_array_elements(p_data -> 'selected_suppliers')
                LOOP
                    INSERT INTO procurement_finalize_items (
                        procurement_id,
                        finalize_items_id,
                        supplier_id,
                        asset_requisitions_id,
                        asset_requisitions_item_id,
                        isactive,
                        tenant_id,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        procurement_id,
                        (supplier -> 'selected_quotation' ->> 'id')::BIGINT,
                        (supplier ->> 'supplier_id')::BIGINT,
                        (
                            SELECT id
                            FROM asset_requisitions
                            WHERE requisition_id = (supplier -> 'selected_item_data' ->> 'requisition_id')
                            LIMIT 1
                        ),
                        (supplier -> 'selected_item_data' ->> 'id')::BIGINT,
                        TRUE,
                        (supplier -> 'selected_quotation' ->> 'tenant_id')::BIGINT,
                        p_current_time,
                        p_current_time
                    )
                    RETURNING id, to_jsonb(procurement_finalize_items.*) INTO new_id, row_json;

                    BEGIN
                        PERFORM log_activity(
                            'insert_finalize_item',
                            'Inserted Procurement Finalize Item',
                            'procurement_finalize_items',
                            new_id,
                            'user',
                            p_user_id,
                            jsonb_build_object('after', row_json),
                            (supplier -> 'selected_quotation' ->> 'tenant_id')::BIGINT
                        );
                    EXCEPTION WHEN OTHERS THEN
                        -- Skip logging if error occurs
                        NULL;
                    END;

                    inserted_rows := inserted_rows + 1;
                END LOOP;

                RETURN QUERY SELECT 
                    'SUCCESS',
                    format('Inserted %s procurement_finalize_items rows.', inserted_rows),
                    inserted_rows;
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
