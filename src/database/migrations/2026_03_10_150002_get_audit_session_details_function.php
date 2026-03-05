<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Function: get_audit_session_details - FIXED for actual table structure
     */
    public function up(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_audit_session_details CASCADE');
        
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION get_audit_session_details(
                p_tenant_id BIGINT,
                p_session_id BIGINT,
                p_user_id BIGINT DEFAULT NULL,
                p_log_viewing BOOLEAN DEFAULT FALSE
            )
            RETURNS JSONB
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_result JSONB;
                v_session_exists BOOLEAN;
            BEGIN
                -- Check if session exists
                SELECT EXISTS(
                    SELECT 1 FROM audit_sessions
                    WHERE id = p_session_id
                        AND tenant_id = p_tenant_id
                        AND deleted_at IS NULL
                ) INTO v_session_exists;

                IF NOT v_session_exists THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'Audit session not found'
                    );
                END IF;

                -- Get session details with related data
                SELECT jsonb_build_object(
                    'status', 'SUCCESS',
                    'data', jsonb_build_object(
                        'id', s.id,
                        'session_code', s.session_code,
                        'session_name', s.session_name,
                        'description', s.description,
                        'audit_period_id', s.audit_period_id,
                        'audit_period_name', ap.period_name,
                        'scheduled_date', s.scheduled_date,
                        'actual_start_date', s.actual_start_date,
                        'actual_end_date', s.actual_end_date,
                        'lead_auditor_id', s.lead_auditor_id,
                        'lead_auditor_name', u.name,
                        'status', s.status,
                        'audit_objectives', s.audit_objectives,
                       'audit_scope', s.audit_scope,
                        'audit_criteria', s.audit_criteria,
                        'audit_methodology', s.audit_methodology,
                        'risk_level', s.risk_level,
                        'risk_factors', s.risk_factors,
                        'findings_count', s.findings_count,
                        'critical_findings', s.critical_findings,
                        'major_findings', s.major_findings,
                        'minor_findings', s.minor_findings,
                        'observations', s.observations,
                        'reported_zombie_assets_count', s.reported_zombie_assets_count,
                        'zombie_assets_summary', s.zombie_assets_summary,
                        'report_issued_at', s.report_issued_at,
                        'audit_conclusion', s.audit_conclusion,
                        'recommendations', s.recommendations,
                        'requires_followup', s.requires_followup,
                        'followup_due_date', s.followup_due_date,
                        'opening_meeting_at', s.opening_meeting_at,
                        'closing_meeting_at', s.closing_meeting_at,
                        'meeting_notes', s.meeting_notes,
                        'created_at', s.created_at,
                        'updated_at', s.updated_at,
                        'auditors', (
                            SELECT COALESCE(jsonb_agg(
                                jsonb_build_object(
                                    'auditor_id', asa.user_id,
                                    'auditor_name', ua.name,
                                    'role', asa.role,
                                    'competency_level', asa.competencies->>'competency_level',
                                    'assigned_at', asa.assigned_at
                                )
                            ), '[]'::jsonb)
                            FROM audit_sessions_auditors asa
                            LEFT JOIN users ua ON ua.id = asa.user_id
                            WHERE asa.audit_session_id = s.id
                                AND asa.tenant_id = p_tenant_id
                                AND asa.deleted_at IS NULL
                        ),
                        'audit_groups', (
                            SELECT COALESCE(jsonb_agg(
                                jsonb_build_object(
                                    'group_id', asag.audit_group_id,
                                    'group_name', ag.group_name,
                                    'group_code', ag.group_code,
                                    'assigned_at', asag.assigned_at
                                )
                            ), '[]'::jsonb)
                            FROM audit_sessions_groups asag
                            LEFT JOIN audit_groups ag ON ag.id = asag.audit_group_id
                            WHERE asag.audit_session_id = s.id
                                AND asag.tenant_id = p_tenant_id
                                AND asag.deleted_at IS NULL
                        ),
                        'zombie_assets', (
                            SELECT COALESCE(jsonb_agg(
                                jsonb_build_object(
                                    'id', za.id,
                                    'zombie_code', za.zombie_code,
                                    'description', za.description,
                                    'location', za.location,
                                    'reported_at', za.created_at
                                )
                            ), '[]'::jsonb)
                            FROM zombie_assets za
                            WHERE za.audit_session_id = s.id
                                AND za.tenant_id = p_tenant_id
                                AND za.deleted_at IS NULL
                        )
                    )
                ) INTO v_result
                FROM audit_sessions s
                LEFT JOIN audit_periods ap ON ap.id = s.audit_period_id
                LEFT JOIN users u ON u.id = s.lead_auditor_id
                WHERE s.id = p_session_id
                    AND s.tenant_id = p_tenant_id
                    AND s.deleted_at IS NULL;

                -- Log viewing activity if requested
                IF p_log_viewing AND p_user_id IS NOT NULL THEN
                    BEGIN
                        PERFORM log_activity(
                            'audit_session.viewed',
                            'Audit session viewed: ' || (v_result->'data'->>'session_code'),
                            'audit_sessions',
                            p_session_id,
                            'user',
                            p_user_id,
                            jsonb_build_object(
                                'session_code', (v_result->'data'->>'session_code'),
                                'action', 'view'
                            ),
                            p_tenant_id
                        );
                    EXCEPTION WHEN OTHERS THEN
                        RAISE NOTICE 'Log activity failed: %', SQLERRM;
                    END;
                END IF;

                RETURN v_result;

            EXCEPTION
                WHEN OTHERS THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'Failed to retrieve audit session: ' || SQLERRM
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
        DB::unprepared('DROP FUNCTION IF EXISTS get_audit_session_details CASCADE');
    }
};
