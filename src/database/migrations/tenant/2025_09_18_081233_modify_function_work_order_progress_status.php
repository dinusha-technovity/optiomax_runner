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
        
             -- drop all versions of the function if exists
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'work_order_progress_status'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION work_order_progress_status(
            p_work_order_id BIGINT
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                old_status TEXT,
                new_status TEXT
            ) AS $$
            DECLARE
                v_current_status TEXT;
                v_next_status TEXT;
                v_status_updated BOOLEAN := FALSE;
                v_ticket_id BIGINT;
            BEGIN
                -- Get current status with row lock to prevent concurrent updates
                SELECT wo.status, wo.work_order_ticket_id
                INTO v_current_status, v_ticket_id
                FROM work_orders wo
                WHERE wo.id = p_work_order_id
                AND wo.deleted_at IS NULL
                FOR UPDATE;

                -- Check if work order exists
                IF v_current_status IS NULL THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Work order not found or deleted'::TEXT,
                        NULL::TEXT,
                        NULL::TEXT;
                    RETURN;
                END IF;

                -- Determine next status based on current status
                v_next_status := CASE v_current_status
                    WHEN 'draft' THEN 'scheduled'
                    WHEN 'APPROVED' THEN 'IN_PROGRESS'
                    WHEN 'IN_PROGRESS' THEN 'COMPLETED'
                    ELSE NULL -- No progression from 'closed' or unknown status
                END;

                -- Validate we can progress the status
                IF v_next_status IS NULL THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        CASE 
                            WHEN v_current_status = 'closed' THEN 'Cannot progress from closed status'
                            ELSE 'Invalid current status: ' || v_current_status
                        END::TEXT,
                        v_current_status,
                        NULL::TEXT;
                    RETURN;
                END IF;

                -- Update the status and timestamps
                UPDATE work_orders
                SET 
                    status = v_next_status,
                    updated_at = NOW(),
                    actual_work_order_start = CASE 
                        WHEN v_next_status = 'IN_PROGRESS' AND actual_work_order_start IS NULL 
                        THEN NOW() 
                        ELSE actual_work_order_start 
                    END,
                    actual_work_order_end = CASE 
                        WHEN v_next_status = 'COMPLETED' AND actual_work_order_end IS NULL
                        THEN NOW() 
                        ELSE actual_work_order_end 
                    END
                WHERE id = p_work_order_id
                RETURNING 1 INTO v_status_updated;

                IF v_status_updated AND v_next_status = 'COMPLETED' AND v_ticket_id IS NOT NULL THEN
                    UPDATE work_order_tickets
                    SET is_closed = TRUE,
                        updated_at = NOW()
                    WHERE id = v_ticket_id;
                END IF;

                -- Return success if updated
                IF v_status_updated THEN
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT,
                        'Status progressed successfully'::TEXT,
                        v_current_status,
                        v_next_status;
                ELSE
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Failed to update work order status'::TEXT,
                        v_current_status,
                        NULL::TEXT;
                END IF;
            END;
            $$ LANGUAGE plpgsql;
        SQL);
    } 

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS work_order_progress_status');
    }
};