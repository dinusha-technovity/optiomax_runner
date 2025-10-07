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
        
        CREATE OR REPLACE FUNCTION update_work_order_completion(
            p_work_order_id BIGINT,
            p_actual_start TIMESTAMP,
            p_actual_end TIMESTAMP,
            p_completion_images JSON,
            p_completion_note TEXT,
            p_technician_comment TEXT,
            p_approved_supervisor_id BIGINT,
            p_actual_used_materials JSON,
            p_status TEXT
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            before_data JSONB,
            after_data JSONB
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            rows_updated INT;
            data_before JSONB;
            data_after JSONB;
        BEGIN
            -- Fetch the work order data before update
            SELECT to_jsonb(work_orders) INTO data_before
            FROM work_orders
            WHERE id = p_work_order_id
            AND deleted_at IS NULL
            LIMIT 1;

            -- If work order doesn't exist, return failure
            IF data_before IS NULL THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT AS status, 
                    'Work order not found or deleted'::TEXT AS message,
                    NULL::JSONB AS before_data,
                    NULL::JSONB AS after_data;
                RETURN;
            END IF;

            -- Update the work order
            UPDATE work_orders
            SET 
                actual_work_order_start = COALESCE(p_actual_start, work_orders.actual_work_order_start),
                actual_work_order_end = COALESCE(p_actual_end, work_orders.actual_work_order_end),
                completion_images = COALESCE(p_completion_images, work_orders.completion_images),
                completion_note = COALESCE(p_completion_note, work_orders.completion_note),
                technician_comment = COALESCE(p_technician_comment, work_orders.technician_comment),
                approved_supervisor_id = COALESCE(p_approved_supervisor_id, work_orders.approved_supervisor_id),
                actual_used_materials = COALESCE(p_actual_used_materials, work_orders.actual_used_materials),
                status = COALESCE(p_status, work_orders.status),
                updated_at = NOW()
            WHERE id = p_work_order_id;

            -- Capture the number of rows updated
            GET DIAGNOSTICS rows_updated = ROW_COUNT;

            -- Fetch data after the update if rows were updated
            IF rows_updated > 0 THEN
                SELECT to_jsonb(work_orders) INTO data_after
                FROM work_orders
                WHERE id = p_work_order_id
                LIMIT 1;

                -- Return success with before and after data
                RETURN QUERY SELECT 
                    'SUCCESS'::TEXT AS status, 
                    'Work order updated successfully'::TEXT AS message,
                    data_before,
                    data_after;
            ELSE
                -- Return failure message with before data and null after data
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT AS status, 
                    'No rows updated'::TEXT AS message,
                    data_before,
                    NULL::JSONB AS after_data;
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
        DB::unprepared('DROP FUNCTION IF EXISTS update_work_order_completion');
    }
};
