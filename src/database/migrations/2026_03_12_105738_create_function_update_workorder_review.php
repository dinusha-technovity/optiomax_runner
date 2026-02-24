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
                v_asset_requisition_id BIGINT;
                v_asset_requisition_type_id BIGINT;
                v_log_type_id BIGINT;
                v_upgrade_asset_requisition_id BIGINT;
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

                -- Handle asset requisition completion if approved
                IF p_review_status = 'approved' THEN
                    -- Get asset_requisition_id from work_order
                    SELECT wo.asset_requisition_id
                    INTO v_asset_requisition_id
                    FROM work_orders wo
                    WHERE wo.id = updated_work_order_id
                    AND wo.tenant_id = p_tenant_id;

                    IF v_asset_requisition_id IS NOT NULL THEN
                        -- Get asset_requisition_type_id and update status to COMPLETE
                        SELECT ar.asset_requisition_type_id
                        INTO v_asset_requisition_type_id
                        FROM asset_requisitions ar
                        WHERE ar.id = v_asset_requisition_id
                        AND ar.tenant_id = p_tenant_id;

                        -- Update asset_requisitions status to COMPLETE
                        UPDATE asset_requisitions
                        SET requisition_status = 'COMPLETE',
                            updated_at = NOW()
                        WHERE id = v_asset_requisition_id
                        AND tenant_id = p_tenant_id;

                        -- If type is 2 (upgrade), update upgrade_asset_requisitions
                        IF v_asset_requisition_type_id = 2 THEN
                            -- Get the upgrade_asset_requisition_id
                            SELECT uar.id
                            INTO v_upgrade_asset_requisition_id
                            FROM upgrade_asset_requisitions uar
                            WHERE uar.asset_requisition_id = v_asset_requisition_id
                            AND uar.tenant_id = p_tenant_id
                            AND uar.work_order_id = updated_work_order_id
                            LIMIT 1;

                            IF v_upgrade_asset_requisition_id IS NOT NULL THEN
                                UPDATE upgrade_asset_requisitions
                                SET status = 'COMPLETED',
                                    updated_at = NOW()
                                WHERE id = v_upgrade_asset_requisition_id
                                AND tenant_id = p_tenant_id;
                            END IF;
                        END IF;

                        -- Log the completion in asset_requisition_logs
                        SELECT id INTO v_log_type_id
                        FROM asset_requisition_log_types
                        WHERE code = 'ASSET_REQUISITION_UPGRADE_COMPLETED'
                        AND (tenant_id IS NULL OR tenant_id = p_tenant_id)
                        LIMIT 1;

                        IF v_log_type_id IS NOT NULL AND p_user_id IS NOT NULL THEN
                            INSERT INTO asset_requisition_logs (
                                tenant_id,
                                asset_requisition_id,
                                log_type_id,
                                action_by,
                                action_at,
                                payload,
                                is_active,
                                created_at,
                                updated_at
                            )
                            VALUES (
                                p_tenant_id,
                                v_asset_requisition_id,
                                v_log_type_id,
                                p_user_id,
                                NOW(),
                                jsonb_build_object(
                                    'work_order_id', updated_work_order_id,
                                    'asset_requisition_type_id', v_asset_requisition_type_id,
                                    'upgrade_asset_requisition_id', v_upgrade_asset_requisition_id,
                                    'review_status', p_review_status,
                                    'responsible_person', p_responsible_person,
                                    'completed_by', p_user_id
                                ),
                                TRUE,
                                NOW(),
                                NOW()
                            );
                        END IF;
                    END IF;
                END IF;

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
