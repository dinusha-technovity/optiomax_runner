<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_completed_work_orders_by_responsible_user(
                p_tenant_id BIGINT,
                p_user_id BIGINT
            )
            RETURNS TABLE (
                id BIGINT,
                work_order_number TEXT,
                title TEXT,
                status TEXT,
                description TEXT,
                asset_item_id BIGINT,
                asset_name TEXT,
                model_number TEXT,
                serial_number TEXT,
                thumbnail_image JSONB,
                actual_work_order_end TIMESTAMP,
                completion_note TEXT
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RAISE EXCEPTION 'Invalid tenant ID';
                END IF;

                IF p_user_id IS NULL OR p_user_id <= 0 THEN
                    RAISE EXCEPTION 'Invalid user ID';
                END IF;

                RETURN QUERY
                SELECT
                    wo.id,
                    wo.work_order_number::TEXT,
                    wo.title::TEXT,
                    wo.status::TEXT,
                    wo.description::TEXT,
                    wo.asset_item_id,
                    a.name::TEXT AS asset_name,
                    ai.model_number::TEXT,
                    ai.serial_number::TEXT,
                    ai.thumbnail_image,
                    wo.actual_work_order_end,
                    wo.completion_note::TEXT
                FROM work_orders wo
                JOIN asset_items ai ON ai.id = wo.asset_item_id
                JOIN assets a ON a.id = ai.asset_id
                WHERE wo.status = 'COMPLETED'
                AND ai.responsible_person = p_user_id
                AND wo.tenant_id = p_tenant_id
                AND wo.deleted_at IS NULL
                AND wo.isactive = true
                AND ai.deleted_at IS NULL
                AND ai.isactive = true;
            END;
            $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_completed_work_orders_by_responsible_user(BIGINT, BIGINT);');
    }
};