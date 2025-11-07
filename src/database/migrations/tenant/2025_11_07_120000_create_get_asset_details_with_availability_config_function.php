<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<SQL
                CREATE OR REPLACE FUNCTION get_asset_details_with_availability_config(
                    p_tenant_id BIGINT,
                    p_asset_item_id BIGINT DEFAULT NULL
                )
                RETURNS TABLE (
                    status TEXT,
                    message TEXT,
                    id BIGINT,
                    asset_id BIGINT,
                    asset_name VARCHAR,
                    model_number VARCHAR,
                    serial_number VARCHAR,
                    thumbnail_image JSONB,
                    assets_type_id BIGINT,
                    assets_type_name VARCHAR,
                    category_id BIGINT,
                    category_name VARCHAR,
                    sub_category_id BIGINT,
                    sub_category_name VARCHAR,
                    -- asset_availability_configurations columns
                    config_id BIGINT,
                    config_asset_items_id BIGINT,
                    config_visibility_id BIGINT,
                    config_visibility_name VARCHAR,
                    config_approval_type_id BIGINT,
                    config_approval_type_name VARCHAR,
                    config_rate NUMERIC,
                    config_rate_currency_type_id BIGINT,
                    config_rate_currency_symbol VARCHAR,
                    config_rate_period_type_id BIGINT,
                    config_rate_period_type_name VARCHAR,
                    config_deposit_required BOOLEAN,
                    config_deposit_amount NUMERIC,
                    config_attachment JSONB,
                    config_cancellation_enabled BOOLEAN,
                    config_cancellation_notice_period INTEGER,
                    config_cancellation_notice_period_type BIGINT,
                    config_cancellation_notice_period_type_name VARCHAR,
                    config_cancellation_fee_enabled BOOLEAN,
                    config_cancellation_fee_type BIGINT,
                    config_cancellation_fee_type_name VARCHAR,
                    config_cancellation_fee_amount NUMERIC,
                    config_cancellation_fee_percentage NUMERIC,
                    config_asset_booking_cancellation_refund_policy_type BIGINT,
                    config_asset_booking_cancellation_refund_policy_type_name VARCHAR,
                    config_tenant_id BIGINT
                )
                LANGUAGE plpgsql
                AS $$
                BEGIN
                    IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                        RETURN QUERY SELECT 
                            'FAILURE','Invalid tenant ID provided',
                            NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,
                            NULL,NULL,NULL,NULL,NULL,NULL,
                            NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL;
                        RETURN;
                    END IF;

                    RETURN QUERY
                    SELECT DISTINCT
                        'SUCCESS',
                        'Asset items with published schedules and correct visibility retrieved successfully',
                        ai.id, a.id, a.name,
                        ai.model_number, ai.serial_number,
                        ai.thumbnail_image,
                        ac.assets_type AS assets_type_id,
                        ast.name AS assets_type_name,
                        a.category AS category_id,
                        ac.name AS category_name,
                        a.sub_category AS sub_category_id,
                        assc.name AS sub_category_name,
                        cfg.id, cfg.asset_items_id, cfg.visibility_id, vis.name,
                        cfg.approval_type_id, appr.name,
                        cfg.rate,
                        cfg.rate_currency_type_id, curr.symbol,
                        cfg.rate_period_type_id, rate_period.name,
                        cfg.deposit_required, cfg.deposit_amount,
                        cfg.attachment, cfg.cancellation_enabled,
                        cfg.cancellation_notice_period, cfg.cancellation_notice_period_type, cancel_period.name,
                        cfg.cancellation_fee_enabled, cfg.cancellation_fee_type, fee_type.name,
                        cfg.cancellation_fee_amount, cfg.cancellation_fee_percentage,
                        cfg.asset_booking_cancellation_refund_policy_type, refund_policy.name,
                        cfg.tenant_id
                    FROM asset_items ai
                    INNER JOIN assets a ON ai.asset_id = a.id
                    INNER JOIN asset_categories ac ON a.category = ac.id
                    INNER JOIN assets_types ast ON ac.assets_type = ast.id
                    INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
                    LEFT JOIN asset_availability_configurations cfg ON cfg.asset_items_id = ai.id AND cfg.deleted_at IS NULL AND cfg.is_active = TRUE
                    LEFT JOIN asset_availability_visibility_types vis ON cfg.visibility_id = vis.id
                    LEFT JOIN asset_booking_approval_types appr ON cfg.approval_type_id = appr.id
                    LEFT JOIN currencies curr ON cfg.rate_currency_type_id = curr.id
                    LEFT JOIN time_period_entries rate_period ON cfg.rate_period_type_id = rate_period.id
                    LEFT JOIN time_period_entries cancel_period ON cfg.cancellation_notice_period_type = cancel_period.id
                    LEFT JOIN asset_booking_cancelling_fee_types fee_type ON cfg.cancellation_fee_type = fee_type.id
                    LEFT JOIN asset_booking_cancellation_refund_policy_type refund_policy ON cfg.asset_booking_cancellation_refund_policy_type = refund_policy.id
                    WHERE (ai.id = p_asset_item_id OR p_asset_item_id IS NULL OR p_asset_item_id <= 0)
                    AND ai.tenant_id = p_tenant_id
                    AND ai.deleted_at IS NULL
                    AND ai.isactive = TRUE
                    AND a.deleted_at IS NULL
                    AND a.isactive = TRUE
                    AND EXISTS (
                        SELECT 1
                        FROM asset_availability_schedules aas
                        WHERE aas.asset_id = ai.id
                        AND aas.tenant_id = p_tenant_id
                        AND aas.deleted_at IS NULL
                        AND aas.is_active = TRUE
                        AND aas.publish_status = 'PUBLISHED'
                        AND EXISTS (
                            SELECT 1 FROM asset_availability_configurations cfg2
                            WHERE cfg2.asset_items_id = ai.id
                            AND cfg2.visibility_id IN (2, 3)
                        )
                        AND EXISTS (
                            SELECT 1
                            FROM asset_availability_schedule_occurrences o
                            WHERE o.schedule_id = aas.id
                                AND o.deleted_at IS NULL
                                AND o.is_cancelled = FALSE
                                AND o.isactive = TRUE
                        )
                    );
                END;
                $$
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_details_with_availability_config(BIGINT, BIGINT)');
    }
};
