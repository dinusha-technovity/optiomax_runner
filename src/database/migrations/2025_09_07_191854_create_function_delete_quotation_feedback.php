<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<SQL
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'delete_quotation_feedback'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION delete_quotation_feedback(
                IN p_procurement_id BIGINT,
                IN p_selected_supplier_id BIGINT,
                IN p_attempt_id BIGINT,
                IN p_causer_id BIGINT DEFAULT NULL,
                IN p_causer_name TEXT DEFAULT NULL,
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_current_time TIMESTAMPTZ DEFAULT NOW()
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                deleted_count INT;
            BEGIN
                -- First delete related tax rates
                DELETE FROM procurement_attempt_request_items_related_tax_rate t
                USING procurement_attempt_request_items i
                WHERE t.procurement_attempt_request_item_id = i.id
                  AND i.procurement_id = p_procurement_id
                  AND i.attempted_id = p_attempt_id
                  AND i.supplier_id = p_selected_supplier_id;

                -- Then delete the items
                DELETE FROM procurement_attempt_request_items
                WHERE procurement_id = p_procurement_id
                  AND attempted_id = p_attempt_id
                  AND supplier_id = p_selected_supplier_id
                RETURNING id INTO deleted_count;

                -- Log the action
                BEGIN
                    PERFORM log_activity(
                        'delete_procurement_attempt_items',
                        format(
                            'Deleted procurement items for procurement ID %s, supplier ID %s, attempt ID %s by %s',
                            p_procurement_id,
                            p_selected_supplier_id,
                            p_attempt_id,
                            p_causer_name
                        ),
                        'procurement_attempt_request_items',
                        NULL,
                        'user',
                        p_causer_id,
                        NULL,
                        p_tenant_id
                    );
                EXCEPTION WHEN OTHERS THEN NULL;
                END;

                RETURN QUERY SELECT
                    'SUCCESS',
                    format('Deleted %s items successfully', COALESCE(deleted_count,0)::TEXT);
            END;
            $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS delete_quotation_feedback(
            BIGINT, BIGINT, BIGINT, BIGINT, TEXT, BIGINT, TIMESTAMPTZ
        );");
    }
};
