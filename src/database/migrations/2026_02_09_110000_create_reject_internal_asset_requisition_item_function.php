<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            DO $$
            DECLARE
                r RECORD;
            BEGIN 
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'reject_internal_asset_requisition_item'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION reject_internal_asset_requisition_item(
                p_item_id BIGINT,
                p_rejection_reason TEXT,
                p_responsible_person_id BIGINT,
                p_tenant_id BIGINT
            )
            RETURNS TABLE(
                success BOOLEAN,
                message TEXT,
                item_data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_item_exists BOOLEAN;
                v_already_rejected BOOLEAN;
                v_updated_item JSONB;
            BEGIN
                -- Check if item exists and belongs to tenant
                SELECT EXISTS(
                    SELECT 1 
                    FROM internal_asset_requisitions_items iari
                    INNER JOIN internal_asset_requisitions iar 
                        ON iari.internal_asset_requisition_id = iar.id
                    WHERE iari.id = p_item_id 
                        AND iari.tenant_id = p_tenant_id
                        AND iar.targeted_responsible_person = p_responsible_person_id
                        AND iari.isactive = true
                        AND iari.deleted_at IS NULL
                ) INTO v_item_exists;

                IF NOT v_item_exists THEN
                    RETURN QUERY SELECT 
                        false,
                        'Item not found or you are not authorized to reject this item'::TEXT,
                        NULL::JSONB;
                    RETURN;
                END IF;

                -- Check if already rejected
                SELECT is_rejected_by_responsible_person 
                INTO v_already_rejected
                FROM internal_asset_requisitions_items
                WHERE id = p_item_id;

                IF v_already_rejected THEN
                    RETURN QUERY SELECT 
                        false,
                        'Item has already been rejected'::TEXT,
                        NULL::JSONB;
                    RETURN;
                END IF;

                -- Update the item with rejection details
                UPDATE internal_asset_requisitions_items
                SET 
                    is_rejected_by_responsible_person = true,
                    rejection_reason = p_rejection_reason,
                    updated_at = NOW()
                WHERE id = p_item_id
                    AND tenant_id = p_tenant_id;

                -- Get updated item data
                SELECT jsonb_build_object(
                    'id', iari.id,
                    'internal_asset_requisition_id', iari.internal_asset_requisition_id,
                    'asset_item_id', iari.asset_item_id,
                    'item_name', iari.item_name,
                    'required_quantity', iari.required_quantity,
                    'fulfilled_quantity', iari.fulfilled_quantity,
                    'required_date', iari.required_date,
                    'priority', iari.priority,
                    'department', iari.department,
                    'reason_for_requirement', iari.reason_for_requirement,
                    'additional_notes', iari.additional_notes,
                    'is_rejected_by_responsible_person', iari.is_rejected_by_responsible_person,
                    'rejection_reason', iari.rejection_reason,
                    'updated_at', iari.updated_at,
                    'requisition_id', iar.requisition_id,
                    'requisition_by_name', u.name
                )
                INTO v_updated_item
                FROM internal_asset_requisitions_items iari
                INNER JOIN internal_asset_requisitions iar 
                    ON iari.internal_asset_requisition_id = iar.id
                LEFT JOIN users u 
                    ON iar.requisition_by = u.id
                WHERE iari.id = p_item_id;

                RETURN QUERY SELECT 
                    true,
                    'Item rejected successfully'::TEXT,
                    v_updated_item;

            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS reject_internal_asset_requisition_item(BIGINT, TEXT, BIGINT, BIGINT);');
    }
};