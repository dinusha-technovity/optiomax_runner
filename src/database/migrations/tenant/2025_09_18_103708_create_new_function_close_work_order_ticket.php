<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<SQL
        
             -- drop all versions of the function if exists
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'close_work_order_ticket'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION close_work_order_ticket(
                p_ticket_id BIGINT,
                p_tenant_id BIGINT,
                p_current_time TIMESTAMPTZ,
                p_user_id BIGINT,
                p_user_name VARCHAR
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                rows_updated INT;
                v_existing_data JSONB;
            BEGIN
                -- Snapshot existing row
                SELECT to_jsonb(work_order_tickets) INTO v_existing_data
                FROM work_order_tickets
                WHERE id = p_ticket_id;

                -- Update
                UPDATE work_order_tickets
                SET 
                    is_closed = TRUE,
                    updated_at = p_current_time
                WHERE id = p_ticket_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

                GET DIAGNOSTICS rows_updated = ROW_COUNT;

                IF rows_updated > 0 THEN
                    RETURN QUERY SELECT 'SUCCESS', 'Ticket closed successfully';
                ELSE
                    RETURN QUERY SELECT 'FAILURE', 'No rows updated. Ticket not found or already closed/deleted.';
                END IF;
            END;
            $$;

        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS close_work_order_ticket');
    }
};
