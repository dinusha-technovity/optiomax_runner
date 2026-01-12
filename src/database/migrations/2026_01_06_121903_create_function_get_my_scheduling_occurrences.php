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
                WHERE proname = 'get_authuser_asset_scheduling_occurrences'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_authuser_asset_scheduling_occurrences(
            p_action_type TEXT DEFAULT 'Normal',
            p_tenant_id BIGINT DEFAULT NULL,
            p_timezone TEXT DEFAULT NULL,
            p_schedule_id BIGINT DEFAULT NULL,
            p_asset_id BIGINT DEFAULT NULL,
            p_employee_id BIGINT DEFAULT NULL,
            p_start_datetime TIMESTAMPTZ DEFAULT NULL,
            p_end_datetime TIMESTAMPTZ DEFAULT NULL,
            p_auth_user_id BIGINT DEFAULT NULL
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
            schedule_note TEXT,
            schedule_start_datetime TIMESTAMPTZ,
            schedule_end_datetime TIMESTAMPTZ,
            publish_status TEXT,
            recurring_enabled BOOLEAN,
            recurring_pattern TEXT,
            recurring_config JSONB,
            created_by BIGINT,
            creator_name TEXT,
            assigned_employees JSONB
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
                    NULL::BOOLEAN, NULL::TEXT, NULL::JSONB,
                    NULL::BIGINT, NULL::TEXT, NULL::JSONB;
                RETURN;
            END IF;

            -- Count matching occurrences (via parent schedule filters)
            SELECT COUNT(DISTINCT occ.id)
            INTO occurrence_count
            FROM employee_asset_scheduling_occurrences occ
            JOIN employee_asset_scheduling s ON occ.schedule_id = s.id
            WHERE (p_schedule_id IS NULL OR s.id = p_schedule_id)
            AND s.tenant_id = p_tenant_id
            AND (p_asset_id IS NULL OR s.asset_id = p_asset_id)
            AND (p_employee_id IS NULL OR EXISTS (
                SELECT 1 FROM asset_schedule_related_employees asre 
                WHERE asre.asset_schedule_id = s.id 
                AND asre.employee_id = p_employee_id
            ))
            AND (p_auth_user_id IS NULL OR EXISTS (
                SELECT 1 
                FROM asset_schedule_related_employees asre 
                JOIN employees emp ON asre.employee_id = emp.id
                WHERE asre.asset_schedule_id = s.id 
                AND emp.user_id = p_auth_user_id
                AND emp.deleted_at IS NULL
            ))
            AND (p_start_datetime IS NULL OR occ.occurrence_end >= p_start_datetime)
            AND (p_end_datetime IS NULL OR occ.occurrence_start <= p_end_datetime)
            AND s.deleted_at IS NULL
            AND s.is_active = TRUE
            AND (
                p_action_type = 'Normal'
                OR (p_action_type = 'PublishedInternalOnly' AND s.status = 'PUBLISHED')
                OR (p_action_type = 'PublishedExternalOnly' AND s.status = 'PUBLISHED')
                OR (p_action_type = 'PublishedAll' AND s.status = 'PUBLISHED')
            )
            AND occ.deleted_at IS NULL
            AND occ.isactive = TRUE;

            IF occurrence_count = 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 'No matching occurrences found'::TEXT,
                    NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::TEXT,
                    NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TEXT,
                    NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TEXT,
                    NULL::BOOLEAN, NULL::TEXT, NULL::JSONB,
                    NULL::BIGINT, NULL::TEXT, NULL::JSONB;
                RETURN;
            END IF;

            -- Return results with core schedule and occurrence data
            RETURN QUERY
            SELECT DISTINCT ON (occ.id)
                'SUCCESS'::TEXT AS status,
                'Occurrences fetched successfully'::TEXT AS message,
                occ.id AS occurrence_id,
                s.id AS schedule_id,
                s.asset_id,
                a.name::TEXT AS asset_name,
                occ.occurrence_start::timestamptz,
                occ.occurrence_end::timestamptz,
                s."Note"::TEXT AS schedule_note,
                s.start_datetime::timestamptz AS schedule_start_datetime,
                s.end_datetime::timestamptz AS schedule_end_datetime,
                s.status::TEXT AS publish_status,
                s.recurring_enabled,
                s.recurring_pattern::TEXT,
                s.recurring_config,
                s.created_by,
                u.name::TEXT AS creator_name,
                (
                    SELECT jsonb_agg(
                        jsonb_build_object(
                            'employee_id', e.id,
                            'employee_name', e.employee_name,
                            'employee_number', e.employee_number,
                            'email', e.email
                        )
                    )
                    FROM asset_schedule_related_employees asre2
                    JOIN employees e ON asre2.employee_id = e.id
                    WHERE asre2.asset_schedule_id = s.id
                    AND e.deleted_at IS NULL
                ) AS assigned_employees
            FROM employee_asset_scheduling_occurrences occ
            JOIN employee_asset_scheduling s ON occ.schedule_id = s.id
            LEFT JOIN asset_items ai ON s.asset_id = ai.id
            LEFT JOIN assets a ON ai.asset_id = a.id
            LEFT JOIN users u ON s.created_by = u.id
            WHERE 
                (p_schedule_id IS NULL OR s.id = p_schedule_id)
                AND s.tenant_id = p_tenant_id
                AND (p_asset_id IS NULL OR s.asset_id = p_asset_id)
                AND (p_employee_id IS NULL OR EXISTS (
                    SELECT 1 FROM asset_schedule_related_employees asre 
                    WHERE asre.asset_schedule_id = s.id 
                    AND asre.employee_id = p_employee_id
                ))
                AND (p_auth_user_id IS NULL OR EXISTS (
                    SELECT 1 
                    FROM asset_schedule_related_employees asre 
                    JOIN employees emp ON asre.employee_id = emp.id
                    WHERE asre.asset_schedule_id = s.id 
                    AND emp.user_id = p_auth_user_id
                    AND emp.deleted_at IS NULL
                ))
                AND (p_start_datetime IS NULL OR occ.occurrence_end >= p_start_datetime)
                AND (p_end_datetime IS NULL OR occ.occurrence_start <= p_end_datetime)
                AND s.deleted_at IS NULL
                AND s.is_active = TRUE
                AND (
                    p_action_type = 'Normal'
                    OR (p_action_type = 'PublishedInternalOnly' AND s.status = 'PUBLISHED')
                    OR (p_action_type = 'PublishedExternalOnly' AND s.status = 'PUBLISHED')
                    OR (p_action_type = 'PublishedAll' AND s.status = 'PUBLISHED')
                )
                AND occ.deleted_at IS NULL
                AND occ.isactive = TRUE
            ORDER BY occ.id, occ.occurrence_start;
        END;
        $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_authuser_asset_scheduling_occurrences(TEXT, BIGINT, TEXT, BIGINT, BIGINT, BIGINT, TIMESTAMPTZ, TIMESTAMPTZ, BIGINT);");
    }
};
