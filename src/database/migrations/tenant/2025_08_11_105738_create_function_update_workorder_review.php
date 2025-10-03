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
            CREATE OR REPLACE FUNCTION update_workorder_review(
                IN p_work_order_id BIGINT,
                IN p_responsible_person BIGINT,
                IN p_responsible_person_note TEXT,
                IN p_review_status TEXT,
                IN p_tenant_id BIGINT,
                IN p_user_id BIGINT DEFAULT NULL,
                IN p_user_name VARCHAR(255) DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                updated_work_order_id BIGINT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_old_data JSONB;
                v_new_data JSONB;
                v_log_data JSONB;
                v_log_success BOOLEAN;
                v_error_message TEXT;
            BEGIN
                -- Validate status
                IF p_review_status NOT IN ('approved', 'rejected') THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Invalid review status, must be "approved" or "rejected"'::TEXT,
                        NULL::BIGINT;
                    RETURN;
                END IF;

                -- Check if record exists and store old data
                SELECT jsonb_build_object(
                    'id', wo.id,
                    'asset_responsible_person', wo.asset_responsible_person,
                    'asset_responsible_person_note', wo.asset_responsible_person_note,
                    'status', wo.status,
                    'is_deliverd', wo.is_deliverd
                )
                INTO v_old_data
                FROM work_orders wo
                WHERE wo.id = p_work_order_id
                AND wo.tenant_id = p_tenant_id;

                IF v_old_data IS NULL THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Work order not found for update'::TEXT,
                        NULL::BIGINT;
                    RETURN;
                END IF;

                -- Perform update
                UPDATE work_orders wo
                SET
                    asset_responsible_person = p_responsible_person,
                    asset_responsible_person_note = p_responsible_person_note,
                    status = CASE 
                                WHEN p_review_status = 'approved' THEN 'CLOSE'
                                ELSE 'REOPEN'
                            END,
                    is_deliverd = CASE
                                    WHEN p_review_status = 'approved' THEN TRUE
                                    ELSE FALSE
                                END,
                    updated_at = NOW()
                WHERE wo.id = p_work_order_id
                AND wo.tenant_id = p_tenant_id
                RETURNING wo.id INTO updated_work_order_id;

                -- Build new data for logging
                SELECT jsonb_build_object(
                    'id', updated_work_order_id,
                    'asset_responsible_person', p_responsible_person,
                    'asset_responsible_person_note', p_responsible_person_note,
                    'status', CASE WHEN p_review_status = 'approved' THEN 'CLOSE' ELSE 'REOPEN' END,
                    'is_deliverd', CASE WHEN p_review_status = 'approved' THEN TRUE ELSE FALSE END
                ) INTO v_new_data;

                v_log_data := jsonb_build_object(
                    'work_order_id', updated_work_order_id,
                    'old_data', v_old_data,
                    'new_data', v_new_data
                );

                -- Log activity if user info is present
                IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
                    BEGIN
                        PERFORM log_activity(
                            'work_order.reviewed',
                            'Work order reviewed by ' || p_user_name || ' with status ' || p_review_status,
                            'work_order',
                            updated_work_order_id,
                            'user',
                            p_user_id,
                            v_log_data,
                            p_tenant_id
                        );
                        v_log_success := TRUE;
                    EXCEPTION WHEN OTHERS THEN
                        v_log_success := FALSE;
                        v_error_message := 'Logging failed: ' || SQLERRM;
                    END;
                END IF;

                RETURN QUERY SELECT 
                    'SUCCESS'::TEXT,
                    'Work order updated successfully'::TEXT,
                    updated_work_order_id;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS update_workorder_review(BIGINT, BIGINT, TEXT, TEXT, BIGINT, BIGINT, VARCHAR)');
    }
};