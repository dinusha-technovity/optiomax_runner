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
                        SELECT oid::regprocedure::text AS func_signature
                        FROM pg_proc
                        WHERE proname = 'resubmit_purchase_order'
                    LOOP
                        EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                    END LOOP;
                END$$;

                CREATE OR REPLACE FUNCTION resubmit_purchase_order(
                    IN p_id BIGINT,
                    IN p_updated_by BIGINT,
                    IN p_tenant_id BIGINT,
                    IN p_submit_officer_comment TEXT DEFAULT NULL,
                    IN p_due_date TIMESTAMPTZ DEFAULT NULL,
                    IN p_current_time TIMESTAMPTZ DEFAULT NOW()

                )
                RETURNS TABLE (
                    status TEXT,
                    message TEXT,
                    po_id BIGINT,
                    purch_order_number TEXT
                )
                LANGUAGE plpgsql
                AS $$
                DECLARE
                    v_po_id BIGINT;
                    v_po_number TEXT;
                    v_exists BOOLEAN := FALSE;
                    v_po_data JSONB;
                    v_log_success BOOLEAN := FALSE;
                    v_error_message TEXT;
                BEGIN
                    -- Validate required fields
                    IF p_id IS NULL THEN
                        RETURN QUERY SELECT 
                            'ERROR'::TEXT,
                            'Purchase Order ID is required'::TEXT,
                            NULL::BIGINT,
                            NULL::TEXT;
                        RETURN;
                    END IF;

                    IF p_tenant_id IS NULL THEN
                        RETURN QUERY SELECT 
                            'ERROR'::TEXT,
                            'Tenant ID is required'::TEXT,
                            NULL::BIGINT,
                            NULL::TEXT;
                        RETURN;
                    END IF;

                    IF p_updated_by IS NULL THEN
                        RETURN QUERY SELECT 
                            'ERROR'::TEXT,
                            'Updated by user ID is required'::TEXT,
                            NULL::BIGINT,
                            NULL::TEXT;
                        RETURN;
                    END IF;

                    -- Check if purchasing order exists
                    SELECT EXISTS (
                        SELECT 1 FROM purchasing_orders 
                        WHERE id = p_id 
                        AND tenant_id = p_tenant_id
                        AND deleted_at IS NULL
                    ) INTO v_exists;
                    
                    IF NOT v_exists THEN
                        RETURN QUERY SELECT 
                            'ERROR'::TEXT,
                            'Purchasing order not found or access denied'::TEXT,
                            NULL::BIGINT,
                            NULL::TEXT;
                        RETURN;
                    END IF;

                    -- Get existing PO number
                    SELECT po_number INTO v_po_number
                    FROM purchasing_orders
                    WHERE id = p_id;

                    -- Update only submit_officer_comment and due_date
                    UPDATE purchasing_orders SET
                        submit_officer_comment = p_submit_officer_comment,
                        due_date = p_due_date,
                        updated_by = p_updated_by,
                        updated_at = p_current_time,
                        status = 'Pending'
                    WHERE id = p_id
                    RETURNING id INTO v_po_id;

                    -- Prepare PO data for logging
                    v_po_data := jsonb_build_object(
                        'po_id', v_po_id,
                        'po_number', v_po_number,
                        'submit_officer_comment', p_submit_officer_comment,
                        'due_date', p_due_date,
                        'action', 'resubmit'
                    );

                    -- Log the activity (with error handling)
                    BEGIN
                        PERFORM log_activity(
                            'purchasing_order.resubmitted',
                            'Purchasing Order resubmitted: ' || v_po_number,
                            'purchasing_order',
                            v_po_id,
                            'user',
                            p_updated_by,
                            v_po_data,
                            p_tenant_id
                        );
                        v_log_success := TRUE;
                    EXCEPTION WHEN OTHERS THEN
                        v_log_success := FALSE;
                        v_error_message := 'Logging failed: ' || SQLERRM;
                    END;

                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT,
                        'Purchasing order resubmitted successfully'::TEXT,
                        v_po_id,
                        v_po_number;

                EXCEPTION
                    WHEN OTHERS THEN
                        RETURN QUERY SELECT 
                            'ERROR'::TEXT,
                            ('Database error: ' || SQLERRM)::TEXT,
                            NULL::BIGINT,
                            NULL::TEXT;
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
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'resubmit_purchase_order'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        SQL);
    }
};
