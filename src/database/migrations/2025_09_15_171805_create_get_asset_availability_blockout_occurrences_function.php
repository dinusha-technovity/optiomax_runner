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
            CREATE OR REPLACE FUNCTION get_asset_availability_blockout_occurrences(
                p_action_type TEXT DEFAULT 'Normal',
                p_tenant_id BIGINT DEFAULT NULL,
                p_timezone TEXT DEFAULT NULL,
                p_blockout_id BIGINT DEFAULT NULL,
                p_asset_id BIGINT DEFAULT NULL,
                p_start_datetime TIMESTAMPTZ DEFAULT NULL,
                p_end_datetime TIMESTAMPTZ DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                occurrence_id BIGINT,
                blockout_id BIGINT,
                asset_id BIGINT,
                asset_name TEXT,
                occurrence_start TIMESTAMPTZ,
                occurrence_end TIMESTAMPTZ,
                blockout_title TEXT,
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
                creator_name TEXT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                occurrence_count INT;
            BEGIN
                -- Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT, 'Invalid tenant ID provided'::TEXT,
                        NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TEXT,
                        NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT,
                        NULL::BOOLEAN, NULL::TEXT, NULL::JSONB, NULL::JSONB,
                        NULL::BIGINT, NULL::TEXT;
                    RETURN;
                END IF;

                -- Count matching occurrences (via parent blockout filters)
                SELECT COUNT(*)
                INTO occurrence_count
                FROM asset_availability_blockout_occurrences occ
                JOIN asset_availability_blockout_schedules b ON occ.blockout_id = b.id
                WHERE (p_blockout_id IS NULL OR b.id = p_blockout_id)
                AND b.tenant_id = p_tenant_id
                AND (p_asset_id IS NULL OR b.asset_id = p_asset_id)
                AND (p_start_datetime IS NULL OR occ.occurrence_end >= p_start_datetime)
                AND (p_end_datetime IS NULL OR occ.occurrence_start <= p_end_datetime)
                AND b.deleted_at IS NULL
                AND b.is_active = TRUE
                AND (
                    p_action_type = 'Normal'
                    OR (p_action_type = 'PublishedOnly' AND b.publish_status = 'PUBLISHED')
                );

                IF occurrence_count = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT, 'No matching blockout occurrences found'::TEXT,
                        NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TEXT,
                        NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT,
                        NULL::BOOLEAN, NULL::TEXT, NULL::JSONB, NULL::JSONB,
                        NULL::BIGINT, NULL::TEXT;
                    RETURN;
                END IF;

                -- Return results with all joined fields
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Blockout occurrences fetched successfully'::TEXT AS message,
                    occ.id AS occurrence_id,
                    b.id AS blockout_id,
                    b.asset_id,
                    a.name::TEXT AS asset_name,
                    occ.occurrence_start::timestamptz,
                    occ.occurrence_end::timestamptz,
                    b.title::TEXT AS blockout_title,
                    b.block_start_datetime::timestamptz,
                    b.block_end_datetime::timestamptz,
                    b.publish_status::TEXT,
                    b.reason_type_id,
                    COALESCE(r.name, '')::TEXT AS reason_type_name,
                    b.custom_reason::TEXT,
                    b.description::TEXT,
                    b.recurring_enabled,
                    b.recurring_pattern::TEXT,
                    b.recurring_config,
                    b.attachment,
                    b.created_by,
                    u.name::TEXT AS creator_name
                FROM asset_availability_blockout_occurrences occ
                JOIN asset_availability_blockout_schedules b ON occ.blockout_id = b.id
                LEFT JOIN asset_items ai ON b.asset_id = ai.id
                LEFT JOIN assets a ON ai.asset_id = a.id
                LEFT JOIN asset_availability_blockout_reason_types r ON b.reason_type_id = r.id
                LEFT JOIN users u ON b.created_by = u.id
                WHERE 
                    (p_blockout_id IS NULL OR b.id = p_blockout_id)
                    AND b.tenant_id = p_tenant_id
                    AND (p_asset_id IS NULL OR b.asset_id = p_asset_id)
                    AND (p_start_datetime IS NULL OR occ.occurrence_end >= p_start_datetime)
                    AND (p_end_datetime IS NULL OR occ.occurrence_start <= p_end_datetime)
                    AND b.deleted_at IS NULL
                    AND b.is_active = TRUE
                    AND (
                        p_action_type = 'Normal'
                        OR (p_action_type = 'PublishedOnly' AND b.publish_status = 'PUBLISHED')
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
        DB::unprepared("DROP FUNCTION IF EXISTS get_asset_availability_blockout_occurrences(TEXT, BIGINT, TEXT, BIGINT, BIGINT, TIMESTAMPTZ, TIMESTAMPTZ);");
    }
};