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
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_asset_availability_blockout_schedules'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION get_asset_availability_blockout_schedules(
                p_tenant_id BIGINT,
                p_timezone TEXT,
                p_blockout_id BIGINT DEFAULT NULL,
                p_asset_id BIGINT DEFAULT NULL,
                p_start_datetime TIMESTAMPTZ DEFAULT NULL,
                p_end_datetime TIMESTAMPTZ DEFAULT NULL,
                p_action_type TEXT DEFAULT 'Normal'
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                asset_id BIGINT,
                asset_name TEXT,
                block_start_datetime TIMESTAMPTZ,
                block_end_datetime TIMESTAMPTZ,
                title TEXT,
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
                created_at TIMESTAMPTZ,
                updated_at TIMESTAMPTZ
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                blockout_count INT;
            BEGIN
                -- Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT, 'Invalid tenant ID provided'::TEXT,
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TEXT,
                        NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::BOOLEAN, NULL::TEXT, NULL::JSONB,
                        NULL::JSONB, NULL::BIGINT, NULL::TEXT, NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ;
                    RETURN;
                END IF;

                -- Count matching blockouts
                SELECT COUNT(*) INTO blockout_count
                FROM asset_availability_blockout_schedules b
                WHERE (p_blockout_id IS NULL OR b.id = p_blockout_id)
                AND b.tenant_id = p_tenant_id
                AND (p_asset_id IS NULL OR b.asset_id = p_asset_id)
                AND (p_start_datetime IS NULL OR b.block_end_datetime > p_start_datetime)
                AND (p_end_datetime IS NULL OR b.block_start_datetime < p_end_datetime)
                AND b.deleted_at IS NULL
                AND b.is_active = TRUE
                AND (
                    p_action_type IS NULL OR
                    p_action_type = 'Normal' OR
                    (p_action_type = 'PublishedOnly' AND b.publish_status = 'PUBLISHED')
                );

                IF blockout_count = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT, 'No matching blockout schedules found'::TEXT,
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TEXT,
                        NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::BOOLEAN, NULL::TEXT, NULL::JSONB,
                        NULL::JSONB, NULL::BIGINT, NULL::TEXT, NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ;
                    RETURN;
                END IF;

                -- Return results with action_type filter
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Blockout schedules fetched successfully'::TEXT AS message,
                    b.id,
                    b.asset_id,
                    a.name::TEXT AS asset_name,
                    b.block_start_datetime::timestamptz AS block_start_datetime,
                    b.block_end_datetime::timestamptz AS block_end_datetime,
                    b.title::TEXT,
                    b.publish_status::TEXT,
                    b.reason_type_id,
                    COALESCE(r.name, '')::TEXT AS reason_type_name,
                    b.custom_reason::TEXT,
                    b.description::TEXT,
                    b.recurring_enabled,
                    b.recurring_pattern::TEXT,
                    b.recurring_config::JSONB,
                    b.attachment::JSONB,
                    b.created_by,
                    u.name::TEXT AS creator_name,
                    b.created_at::timestamptz AS created_at,
                    b.updated_at::timestamptz AS updated_at
                FROM asset_availability_blockout_schedules b
                LEFT JOIN asset_items ai ON b.asset_id = ai.id
                LEFT JOIN assets a ON ai.asset_id = a.id
                LEFT JOIN asset_availability_blockout_reason_types r ON b.reason_type_id = r.id
                LEFT JOIN users u ON b.created_by = u.id
                WHERE 
                    (p_blockout_id IS NULL OR b.id = p_blockout_id)
                    AND b.tenant_id = p_tenant_id
                    AND (p_asset_id IS NULL OR b.asset_id = p_asset_id)
                    AND (p_start_datetime IS NULL OR b.block_end_datetime > p_start_datetime)
                    AND (p_end_datetime IS NULL OR b.block_start_datetime < p_end_datetime)
                    AND b.deleted_at IS NULL
                    AND b.is_active = TRUE
                    AND (
                        p_action_type IS NULL OR
                        p_action_type = 'Normal' OR
                        (p_action_type = 'PublishedOnly' AND b.publish_status = 'PUBLISHED')
                    );
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_availability_blockout_schedules(BIGINT, TEXT, BIGINT, BIGINT, TIMESTAMPTZ, TIMESTAMPTZ, TEXT)');
    }
};
