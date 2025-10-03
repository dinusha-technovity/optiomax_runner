<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_auth_asset_maintenance_incident_reports(
            BIGINT, BIGINT, BIGINT, TEXT
        );");

        DB::unprepared(<<<SQL
        CREATE OR REPLACE FUNCTION get_auth_asset_maintenance_incident_reports(
            p_tenant_id BIGINT,
            p_incident_report_id BIGINT DEFAULT NULL,
            p_causer_id BIGINT DEFAULT NULL,
            p_causer_name TEXT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            id BIGINT,
            asset BIGINT,
            asset_name TEXT,
            asset_model_number TEXT,
            asset_serial_number TEXT,
            asset_asset_tag TEXT,
            asset_thumbnail_image JSONB,
            incident_date_time TIMESTAMP,
            latitude DECIMAL,
            longitude DECIMAL,
            reporter_details JSONB,
            incident_description TEXT,
            immediate_actions JSONB,
            start_time TIMESTAMP,
            end_time TIMESTAMP,
            downtime_duration INTERVAL,
            production_affected BOOLEAN,
            safety_risk BOOLEAN,
            impact_description TEXT,
            requested_actions JSONB,
            priority_level_id BIGINT,
            priority_level_name TEXT,
            attachments JSONB,
            root_cause_analysis TEXT,
            follow_up_actions TEXT,
            verified_by_id BIGINT,
            verified_by_name TEXT,
            verification_date DATE,
            remarks TEXT,
            report_created_by_id BIGINT,
            report_created_by_name TEXT,
            incident_reports_status TEXT,
            report_number TEXT,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )
        LANGUAGE plpgsql
        AS \$\$
        BEGIN
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE', 'Invalid tenant ID', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
                    NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
                    NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
                    NULL, NULL;
                RETURN;
            END IF;

            RETURN QUERY
            WITH data AS (
                SELECT
                    amir.id,
                    amir.asset,
                    a.name::TEXT AS asset_name,
                    ai.model_number::TEXT AS asset_model_number,
                    ai.serial_number::TEXT AS asset_serial_number,
                    ai.asset_tag::TEXT AS asset_asset_tag,
                    ai.thumbnail_image AS asset_thumbnail_image,
                    amir.incident_date_time,
                    amir.latitude,
                    amir.longitude,
                    amir.reporter_details,
                    amir.incident_description,
                    amir.immediate_actions,
                    amir.start_time,
                    amir.end_time,
                    amir.downtime_duration,
                    amir.production_affected,
                    amir.safety_risk,
                    amir.impact_description,
                    amir.requested_actions,
                    amir.priority_level,
                    pl.value::TEXT AS priority_level_name,
                    amir.attachments,
                    amir.root_cause_analysis,
                    amir.follow_up_actions,
                    amir.verified_by,
                    uv.name::TEXT AS verified_by_name,
                    amir.verification_date,
                    amir.remarks,
                    amir.report_created_by,
                    uc.name::TEXT AS report_created_by_name,
                    amir.incident_reports_status::TEXT,
                    amir.report_number::TEXT,
                    amir.created_at,
                    amir.updated_at
                FROM asset_maintenance_incident_reports amir
                LEFT JOIN asset_items ai ON amir.asset = ai.id
                LEFT JOIN assets a ON ai.asset_id = a.id
                LEFT JOIN asset_maintenance_incident_report_priority_levels pl ON amir.priority_level = pl.id
                LEFT JOIN users uv ON amir.verified_by = uv.id
                LEFT JOIN users uc ON amir.report_created_by = uc.id
                WHERE amir.tenant_id = p_tenant_id
                AND (p_incident_report_id IS NULL OR amir.id = p_incident_report_id)
                AND amir.deleted_at IS NULL
                AND amir.report_created_by = p_causer_id
                AND amir.isactive = TRUE
            )
            SELECT
                'SUCCESS',
                'Incident reports retrieved successfully',
                d.id,
                d.asset,
                d.asset_name,
                d.asset_model_number,
                d.asset_serial_number,
                d.asset_asset_tag,
                d.asset_thumbnail_image,
                d.incident_date_time,
                d.latitude,
                d.longitude,
                d.reporter_details,
                d.incident_description,
                d.immediate_actions,
                d.start_time,
                d.end_time,
                d.downtime_duration,
                d.production_affected,
                d.safety_risk,
                d.impact_description,
                d.requested_actions,
                d.priority_level,
                d.priority_level_name,
                d.attachments,
                d.root_cause_analysis,
                d.follow_up_actions,
                d.verified_by,
                d.verified_by_name,
                d.verification_date,
                d.remarks,
                d.report_created_by,
                d.report_created_by_name,
                d.incident_reports_status,
                d.report_number,
                d.created_at,
                d.updated_at
            FROM data d;

            BEGIN
                PERFORM log_activity(
                    'view_incident_report',
                    format('Viewed incident report%s by %s', 
                        CASE 
                            WHEN p_incident_report_id IS NULL THEN ' list'
                            ELSE format(' ID %s', p_incident_report_id)
                        END,
                        p_causer_name
                    ),
                    'asset_maintenance_incident_reports',
                    p_incident_report_id,
                    'user',
                    p_causer_id,
                    NULL,
                    p_tenant_id
                );
            EXCEPTION WHEN OTHERS THEN
                NULL;
            END;
        END;
        \$\$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_auth_asset_maintenance_incident_reports(
            BIGINT, BIGINT, BIGINT, TEXT
        );");
    }
};