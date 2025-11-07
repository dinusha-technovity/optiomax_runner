<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
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
                WHERE proname = 'get_asset_availability_schedule_occurrences'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_asset_availability_schedule_occurrences(
            p_action_type TEXT DEFAULT 'Normal',
            p_tenant_id BIGINT DEFAULT NULL,
            p_timezone TEXT DEFAULT NULL,
            p_schedule_id BIGINT DEFAULT NULL,
            p_asset_id BIGINT DEFAULT NULL,
            p_start_datetime TIMESTAMPTZ DEFAULT NULL,
            p_end_datetime TIMESTAMPTZ DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            occurrence_id BIGINT,
            schedule_id BIGINT,
            asset_id BIGINT,
            asset_name TEXT,
            occurrence_start TIMESTAMPTZ,
            occurrence_end TIMESTAMPTZ,
            schedule_title TEXT,
            schedule_start_datetime TIMESTAMPTZ,
            schedule_end_datetime TIMESTAMPTZ,
            publish_status TEXT,
            schedule_description TEXT,
            recurring_enabled BOOLEAN,
            recurring_pattern TEXT,
            recurring_config JSONB,
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
                    NULL::TEXT, NULL::BOOLEAN, NULL::TEXT, NULL::JSONB,
                    NULL::BIGINT, NULL::TEXT;
                RETURN;
            END IF;

            -- Count matching occurrences (via parent schedule filters)
            SELECT COUNT(*)
            INTO occurrence_count
            FROM asset_availability_schedule_occurrences occ
            JOIN asset_availability_schedules s ON occ.schedule_id = s.id
            WHERE (p_schedule_id IS NULL OR s.id = p_schedule_id)
            AND s.tenant_id = p_tenant_id
            AND (p_asset_id IS NULL OR s.asset_id = p_asset_id)
            AND (p_start_datetime IS NULL OR occ.occurrence_end >= p_start_datetime)
            AND (p_end_datetime IS NULL OR occ.occurrence_start <= p_end_datetime)
            AND s.deleted_at IS NULL
            AND s.is_active = TRUE
            AND (
                p_action_type = 'Normal'
                OR (p_action_type = 'PublishedInternalOnly' AND s.publish_status = 'PUBLISHED')
                OR (p_action_type = 'PublishedExternalOnly' AND s.publish_status = 'PUBLISHED')
                OR (p_action_type = 'PublishedAll' AND s.publish_status = 'PUBLISHED')
            )
            AND occ.deleted_at IS NULL
            AND occ.isactive = TRUE;

            IF occurrence_count = 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 'No matching occurrences found'::TEXT,
                    NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::TEXT,
                    NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TEXT,
                    NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TEXT,
                    NULL::TEXT, NULL::BOOLEAN, NULL::TEXT, NULL::JSONB,
                    NULL::BIGINT, NULL::TEXT;
                RETURN;
            END IF;

            -- Return results with core schedule and occurrence data only
            RETURN QUERY
            SELECT
                'SUCCESS'::TEXT AS status,
                'Occurrences fetched successfully'::TEXT AS message,
                occ.id AS occurrence_id,
                s.id AS schedule_id,
                s.asset_id,
                a.name::TEXT AS asset_name,
                occ.occurrence_start::timestamptz,
                occ.occurrence_end::timestamptz,
                s.title::TEXT AS schedule_title,
                s.start_datetime::timestamptz AS schedule_start_datetime,
                s.end_datetime::timestamptz AS schedule_end_datetime,
                s.publish_status::TEXT,
                s.description,
                s.recurring_enabled,
                s.recurring_pattern::TEXT,
                s.recurring_config,
                s.created_by,
                u.name::TEXT AS creator_name
            FROM asset_availability_schedule_occurrences occ
            JOIN asset_availability_schedules s ON occ.schedule_id = s.id
            LEFT JOIN asset_items ai ON s.asset_id = ai.id
            LEFT JOIN assets a ON ai.asset_id = a.id
            LEFT JOIN users u ON s.created_by = u.id
            WHERE 
                (p_schedule_id IS NULL OR s.id = p_schedule_id)
                AND s.tenant_id = p_tenant_id
                AND (p_asset_id IS NULL OR s.asset_id = p_asset_id)
                AND (p_start_datetime IS NULL OR occ.occurrence_end >= p_start_datetime)
                AND (p_end_datetime IS NULL OR occ.occurrence_start <= p_end_datetime)
                AND s.deleted_at IS NULL
                AND s.is_active = TRUE
                AND (
                    p_action_type = 'Normal'
                    OR (p_action_type = 'PublishedInternalOnly' AND s.publish_status = 'PUBLISHED')
                    OR (p_action_type = 'PublishedExternalOnly' AND s.publish_status = 'PUBLISHED')
                    OR (p_action_type = 'PublishedAll' AND s.publish_status = 'PUBLISHED')
                )
                AND occ.deleted_at IS NULL
                AND occ.isactive = TRUE;
        END;
        $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_asset_availability_schedule_occurrences(TEXT, BIGINT, TEXT, BIGINT, BIGINT, TIMESTAMPTZ, TIMESTAMPTZ);");
    }
};
