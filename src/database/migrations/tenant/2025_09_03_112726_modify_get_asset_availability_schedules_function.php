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
                    WHERE proname = 'get_asset_availability_schedules'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION get_asset_availability_schedules(
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
                id BIGINT,
                asset_id BIGINT,
                asset_name TEXT,
                start_datetime TIMESTAMPTZ,
                end_datetime TIMESTAMPTZ,
                title TEXT,
                publish_status TEXT,
                visibility_id BIGINT,
                visibility_name TEXT,
                approval_type_id BIGINT,
                approval_type_name TEXT,
                term_type_id BIGINT,
                term_type_name TEXT,
                rate NUMERIC,
                rate_currency_type_id BIGINT,
                rate_currency_name TEXT,
                rate_period_type_id BIGINT,
                rate_period_name TEXT,
                deposit_required BOOLEAN,
                deposit_amount NUMERIC,
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
                schedule_count INT;
            BEGIN
                -- Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT, 'Invalid tenant ID provided'::TEXT,
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TEXT,
                        NULL::TEXT, NULL::BIGINT, NULL::TEXT,
                        NULL::BIGINT, NULL::TEXT, NULL::BIGINT,
                        NULL::TEXT, NULL::NUMERIC, NULL::BIGINT,
                        NULL::TEXT, NULL::BIGINT, NULL::TEXT,
                        NULL::BOOLEAN, NULL::NUMERIC, NULL::TEXT,
                        NULL::BOOLEAN, NULL::TEXT, NULL::JSONB,
                        NULL::JSONB, NULL::BIGINT, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ;
                    RETURN;
                END IF;

                -- Count matching schedules
                SELECT COUNT(*) INTO schedule_count
                FROM asset_availability_schedules s
                LEFT JOIN asset_items ai ON s.asset_id = ai.id
                LEFT JOIN assets a ON ai.asset_id = a.id
                WHERE (p_schedule_id IS NULL OR s.id = p_schedule_id)
                AND s.tenant_id = p_tenant_id
                AND (p_asset_id IS NULL OR s.asset_id = p_asset_id)
                AND (p_start_datetime IS NULL OR s.end_datetime > p_start_datetime)
                AND (p_end_datetime IS NULL OR s.start_datetime < p_end_datetime)
                AND s.deleted_at IS NULL
                AND s.is_active = TRUE
                AND (
                    p_action_type = 'Normal'
                    OR (p_action_type = 'PublishedInternalOnly' AND s.publish_status = 'PUBLISHED' AND s.visibility_id IN (1, 3))
                    OR (p_action_type = 'PublishedExternalOnly' AND s.publish_status = 'PUBLISHED' AND s.visibility_id IN (2, 3))
                );

                IF schedule_count = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT, 'No matching schedules found'::TEXT,
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TEXT,
                        NULL::TEXT, NULL::BIGINT, NULL::TEXT,
                        NULL::BIGINT, NULL::TEXT, NULL::BIGINT,
                        NULL::TEXT, NULL::NUMERIC, NULL::BIGINT,
                        NULL::TEXT, NULL::BIGINT, NULL::TEXT,
                        NULL::BOOLEAN, NULL::NUMERIC, NULL::TEXT,
                        NULL::BOOLEAN, NULL::TEXT, NULL::JSONB,
                        NULL::JSONB, NULL::BIGINT, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ;
                    RETURN;
                END IF;

                -- Return results
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Schedules fetched successfully'::TEXT AS message,
                    s.id,
                    s.asset_id,
                    a.name::TEXT AS asset_name,
                    s.start_datetime::timestamptz AS start_datetime,
                    s.end_datetime::timestamptz AS end_datetime,
                    s.title::TEXT,
                    s.publish_status::TEXT,
                    s.visibility_id,
                    avt.name::TEXT AS visibility_name,
                    s.approval_type_id,
                    abt.name::TEXT AS approval_type_name,
                    s.term_type_id,
                    att.name::TEXT AS term_type_name,
                    s.rate,
                    s.rate_currency_type_id,
                    c.name::TEXT AS rate_currency_name,
                    s.rate_period_type_id,
                    tpe.name::TEXT AS rate_period_name,
                    s.deposit_required,
                    s.deposit_amount,
                    s.description,
                    s.recurring_enabled,
                    s.recurring_pattern::TEXT,
                    s.recurring_config::JSONB,
                    s.attachment::JSONB,
                    s.created_by,
                    u.name::TEXT AS creator_name,
                    s.created_at::timestamptz AS created_at,
                    s.updated_at::timestamptz AS updated_at
                FROM asset_availability_schedules s
                LEFT JOIN asset_items ai ON s.asset_id = ai.id
                LEFT JOIN assets a ON ai.asset_id = a.id
                LEFT JOIN asset_availability_visibility_types avt ON s.visibility_id = avt.id
                LEFT JOIN asset_booking_approval_types abt ON s.approval_type_id = abt.id
                LEFT JOIN asset_availability_term_types att ON s.term_type_id = att.id
                LEFT JOIN currencies c ON s.rate_currency_type_id = c.id
                LEFT JOIN time_period_entries tpe ON s.rate_period_type_id = tpe.id
                LEFT JOIN users u ON s.created_by = u.id
                WHERE 
                    (p_schedule_id IS NULL OR s.id = p_schedule_id)
                    AND s.tenant_id = p_tenant_id
                    AND (p_asset_id IS NULL OR s.asset_id = p_asset_id)
                    AND (p_start_datetime IS NULL OR s.end_datetime > p_start_datetime)
                    AND (p_end_datetime IS NULL OR s.start_datetime < p_end_datetime)
                    AND s.deleted_at IS NULL
                    AND s.is_active = TRUE
                    AND (
                        p_action_type = 'Normal'
                        OR (p_action_type = 'PublishedInternalOnly' AND s.publish_status = 'PUBLISHED' AND s.visibility_id IN (1, 3))
                        OR (p_action_type = 'PublishedExternalOnly' AND s.publish_status = 'PUBLISHED' AND s.visibility_id IN (2, 3))
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
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_availability_schedules(BIGINT, TEXT, TEXT, BIGINT, BIGINT, TIMESTAMPTZ, TIMESTAMPTZ);');
    }
};