<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS insert_or_update_asset_maintenance_incident_report(
            BIGINT, TIMESTAMPTZ, TIMESTAMPTZ, DECIMAL, DECIMAL, JSONB, TEXT, JSONB, TIMESTAMPTZ, TIMESTAMPTZ,
            INTERVAL, BOOLEAN, BOOLEAN, TEXT, JSONB, BIGINT, JSONB, TEXT, TEXT, BIGINT, DATE, TEXT, BOOLEAN, 
            BIGINT, BIGINT, BIGINT, TEXT);");

        DB::unprepared(<<<SQL
        CREATE OR REPLACE FUNCTION insert_or_update_asset_maintenance_incident_report(
            IN p_asset BIGINT,
            IN p_current_time TIMESTAMPTZ,
            IN p_incident_date_time TIMESTAMPTZ DEFAULT NULL,
            IN p_latitude DECIMAL DEFAULT NULL,
            IN p_longitude DECIMAL DEFAULT NULL,
            IN p_reporter_details JSONB DEFAULT NULL,
            IN p_incident_description TEXT DEFAULT NULL,
            IN p_immediate_actions JSONB DEFAULT NULL,
            IN p_start_time TIMESTAMPTZ DEFAULT NULL,
            IN p_end_time TIMESTAMPTZ DEFAULT NULL,
            IN p_downtime_duration INTERVAL DEFAULT NULL,
            IN p_production_affected BOOLEAN DEFAULT FALSE,
            IN p_safety_risk BOOLEAN DEFAULT FALSE,
            IN p_impact_description TEXT DEFAULT NULL,
            IN p_requested_actions JSONB DEFAULT NULL,
            IN p_priority_level BIGINT DEFAULT NULL,
            IN p_attachments JSONB DEFAULT NULL,
            IN p_root_cause_analysis TEXT DEFAULT NULL,
            IN p_follow_up_actions TEXT DEFAULT NULL,
            IN p_verified_by BIGINT DEFAULT NULL,
            IN p_verification_date DATE DEFAULT NULL,
            IN p_remarks TEXT DEFAULT NULL,
            IN p_isactive BOOLEAN DEFAULT TRUE,
            IN p_tenant_id BIGINT DEFAULT NULL,
            IN p_id BIGINT DEFAULT NULL,
            IN p_causer_id BIGINT DEFAULT NULL,
            IN p_causer_name TEXT DEFAULT NULL,
            IN p_prefix TEXT DEFAULT 'INCI'
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            incident_id BIGINT,
            incident_report_no TEXT
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            return_id BIGINT;
            inserted_row JSONB;
            updated_row JSONB;
            new_incident_report_no VARCHAR(50);
            curr_val INT;
            supplier_id BIGINT;
        BEGIN
            IF p_id IS NULL OR p_id = 0 THEN
                SELECT nextval('incident_report_number_seq') INTO curr_val;
                new_incident_report_no := p_prefix || '-' || LPAD(curr_val::TEXT, 4, '0');

                INSERT INTO public.asset_maintenance_incident_reports (
                    asset, incident_date_time, latitude, longitude, reporter_details,
                    incident_description, immediate_actions, start_time, end_time, downtime_duration,
                    production_affected, safety_risk, impact_description, requested_actions,
                    priority_level, attachments, root_cause_analysis, follow_up_actions,
                    verified_by, verification_date, remarks, report_created_by, isactive,
                    tenant_id, created_at, updated_at, report_number
                ) VALUES (
                    p_asset, p_incident_date_time, p_latitude, p_longitude, p_reporter_details,
                    p_incident_description, p_immediate_actions, p_start_time, p_end_time, p_downtime_duration,
                    p_production_affected, p_safety_risk, p_impact_description, p_requested_actions,
                    p_priority_level, p_attachments, p_root_cause_analysis, p_follow_up_actions,
                    p_verified_by, p_verification_date, p_remarks, p_causer_id, p_isactive,
                    p_tenant_id, p_current_time, p_current_time, new_incident_report_no
                ) RETURNING id, to_jsonb(asset_maintenance_incident_reports) INTO return_id, inserted_row;

                BEGIN
                    INSERT INTO public.work_order_tickets (
                        asset_id, reference_id, type, isactive, tenant_id, created_at, updated_at
                    ) VALUES (
                        p_asset, return_id, 'incident_report', p_isactive, p_tenant_id, p_current_time, p_current_time
                    );
                EXCEPTION WHEN OTHERS THEN
                    RAISE NOTICE 'Failed to insert work order ticket for new incident: %', SQLERRM;
                END;

                BEGIN
                    PERFORM log_activity(
                        'Create_incident_report',
                        format('Inserted incident report ID %s by %s', return_id, p_causer_name),
                        'asset_maintenance_incident_reports',
                        return_id,
                        'user',
                        p_causer_id,
                        inserted_row,
                        p_tenant_id
                    );
                EXCEPTION WHEN OTHERS THEN NULL;
                END;

                BEGIN
                    SELECT supplier INTO supplier_id FROM asset_items ai WHERE ai.id = p_asset;
                    IF supplier_id IS NOT NULL THEN
                        PERFORM calculate_supplier_rating((supplier_id)::BIGINT, 'INCIDENT_REPORTED', '{}'::JSONB, p_tenant_id);
                    END IF;
                EXCEPTION WHEN OTHERS THEN NULL;
                END;

                RETURN QUERY SELECT 'SUCCESS', 'Incident report submitted successfully', return_id, new_incident_report_no::TEXT;

            ELSE
                UPDATE public.asset_maintenance_incident_reports SET
                    asset = p_asset,
                    incident_date_time = p_incident_date_time,
                    latitude = p_latitude,
                    longitude = p_longitude,
                    reporter_details = p_reporter_details,
                    incident_description = p_incident_description,
                    immediate_actions = p_immediate_actions,
                    start_time = p_start_time,
                    end_time = p_end_time,
                    downtime_duration = p_downtime_duration,
                    production_affected = p_production_affected,
                    safety_risk = p_safety_risk,
                    impact_description = p_impact_description,
                    requested_actions = p_requested_actions,
                    priority_level = p_priority_level,
                    attachments = p_attachments,
                    root_cause_analysis = p_root_cause_analysis,
                    follow_up_actions = p_follow_up_actions,
                    verified_by = p_verified_by,
                    verification_date = p_verification_date,
                    remarks = p_remarks,
                    report_created_by = p_causer_id,
                    isactive = p_isactive,
                    tenant_id = p_tenant_id,
                    updated_at = p_current_time
                WHERE id = p_id
                RETURNING id, to_jsonb(asset_maintenance_incident_reports) INTO return_id, updated_row;

                IF FOUND THEN
                    BEGIN
                        INSERT INTO public.work_order_tickets (
                            asset_id, reference_id, type, isactive, tenant_id, created_at, updated_at
                        ) VALUES (
                            p_asset, return_id, 'incident_report', p_isactive, p_tenant_id, p_current_time, p_current_time
                        );
                    EXCEPTION WHEN OTHERS THEN
                        RAISE NOTICE 'Failed to insert work order ticket for updated incident: %', SQLERRM;
                    END;

                    BEGIN
                        PERFORM log_activity(
                            'update_incident_report',
                            format('Updated incident report ID %s by %s', return_id, p_causer_name),
                            'asset_maintenance_incident_reports',
                            return_id,
                            'user',
                            p_causer_id,
                            updated_row,
                            p_tenant_id
                        );
                    EXCEPTION WHEN OTHERS THEN NULL;
                    END;

                    RETURN QUERY SELECT 'SUCCESS', 'Incident report updated successfully', return_id, (updated_row ->> 'report_number');

                ELSE
                    RETURN QUERY SELECT 'FAILURE', format('Incident report with ID %s not found', p_id), NULL::BIGINT, NULL::TEXT;
                END IF;
            END IF;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS insert_or_update_asset_maintenance_incident_report');
    }
};
