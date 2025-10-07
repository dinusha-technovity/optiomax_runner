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
            CREATE OR REPLACE FUNCTION get_asset_availability_blockout_schedule_full(
                p_blockout_schedule_id BIGINT DEFAULT NULL,
                p_tenant_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                blockout_schedule_id BIGINT,
                asset_id BIGINT,
                asset_name TEXT,
                title TEXT,
                block_start_datetime TIMESTAMPTZ,
                block_end_datetime TIMESTAMPTZ,
                publish_status TEXT,
                reason_type_id BIGINT,
                reason_type_name TEXT,
                custom_reason TEXT,
                description TEXT,
                recurring_enabled BOOLEAN,
                recurring_pattern TEXT,
                recurring_config JSONB,
                attachment JSONB,
                created_by BIGINT,
                creator_name TEXT,
                occurrences JSONB
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TEXT,
                        NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT,
                        NULL::BOOLEAN, NULL::TEXT, NULL::JSONB, NULL::JSONB,
                        NULL::BIGINT, NULL::TEXT, NULL::JSONB;
                    RETURN;
                END IF;

                -- Validate blockout schedule ID
                IF p_blockout_schedule_id IS NULL OR p_blockout_schedule_id <= 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT AS status,
                        'Invalid blockout schedule ID provided'::TEXT AS message,
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TEXT,
                        NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT,
                        NULL::BOOLEAN, NULL::TEXT, NULL::JSONB, NULL::JSONB,
                        NULL::BIGINT, NULL::TEXT, NULL::JSONB;
                    RETURN;
                END IF;

                -- Return the blockout schedule with all related data
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Blockout schedule fetched successfully'::TEXT AS message,
                    b.id AS blockout_schedule_id,
                    b.asset_id,
                    a.name::TEXT AS asset_name,
                    b.title::TEXT,
                    b.block_start_datetime::timestamptz,
                    b.block_end_datetime::timestamptz,
                    b.publish_status::TEXT,
                    b.reason_type_id,
                    r.name::TEXT AS reason_type_name,
                    b.custom_reason::TEXT,
                    b.description::TEXT,
                    b.recurring_enabled,
                    b.recurring_pattern::TEXT,
                    b.recurring_config,
                    b.attachment,
                    b.created_by,
                    u.name::TEXT AS creator_name,
                    (
                        SELECT COALESCE(jsonb_agg(jsonb_build_object(
                            'id', o.id,
                            'blockout_id', o.blockout_id,
                            'asset_id', o.asset_id,
                            'occurrence_start', o.occurrence_start,
                            'occurrence_end', o.occurrence_end,
                            'is_cancelled', o.is_cancelled,
                            'isactive', o.isactive,
                            'created_at', o.created_at,
                            'updated_at', o.updated_at,
                            'deleted_at', o.deleted_at
                        ) ORDER BY o.occurrence_start), '[]'::jsonb)
                        FROM asset_availability_blockout_occurrences o
                        WHERE o.blockout_id = b.id
                    ) AS occurrences
                FROM asset_availability_blockout_schedules b
                LEFT JOIN asset_items ai ON b.asset_id = ai.id
                LEFT JOIN assets a ON ai.asset_id = a.id
                LEFT JOIN asset_availability_blockout_reason_types r ON b.reason_type_id = r.id
                LEFT JOIN users u ON b.created_by = u.id
                WHERE b.id = p_blockout_schedule_id
                AND b.tenant_id = p_tenant_id
                AND b.deleted_at IS NULL;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_availability_blockout_schedule_full(BIGINT, BIGINT)');
    }
};