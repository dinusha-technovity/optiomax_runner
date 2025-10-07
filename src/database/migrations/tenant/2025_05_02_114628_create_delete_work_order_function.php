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
        CREATE OR REPLACE FUNCTION delete_work_order(
            p_work_order_id BIGINT,
            p_tenant_id BIGINT,
            p_current_time TIMESTAMP WITH TIME ZONE,
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
            v_deleted_data JSONB;
            v_log_success BOOLEAN;
        BEGIN
            -- Get old work order data for logging
            SELECT to_jsonb(work_orders) INTO v_deleted_data
            FROM work_orders
            WHERE id = p_work_order_id
            AND tenant_id = p_tenant_id
            AND deleted_at IS NULL;

            -- Soft delete the work order
            UPDATE work_orders
            SET deleted_at = p_current_time
            WHERE id = p_work_order_id
            AND tenant_id = p_tenant_id
            AND deleted_at IS NULL;

            -- Log the activity
            BEGIN
                PERFORM log_activity(
                    'work_order.soft_delete',
                    'Work Order deleted by ' || p_user_name,
                    'work_order',
                    p_work_order_id,
                    'user',
                    p_user_id,
                    v_deleted_data,
                    p_tenant_id
                );
                v_log_success := TRUE;
            EXCEPTION WHEN OTHERS THEN
                v_log_success := FALSE;
            END;

            -- Get number of rows affected
            GET DIAGNOSTICS rows_updated = ROW_COUNT;

            IF rows_updated > 0 THEN
                RETURN QUERY SELECT 
                    'SUCCESS'::TEXT AS status, 
                    'Work order deleted successfully'::TEXT AS message;
            ELSE
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT AS status, 
                    'No rows updated. Work order not found or already deleted.'::TEXT AS message;
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
         DB::unprepared('DROP FUNCTION IF EXISTS delete_work_order');
    }
};
