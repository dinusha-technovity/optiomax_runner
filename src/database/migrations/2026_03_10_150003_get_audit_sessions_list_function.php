<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Function: get_audit_sessions_list - FIXED for actual table structure
     */
    public function up(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_audit_sessions_list CASCADE');
        
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION get_audit_sessions_list(
                p_tenant_id BIGINT,
                p_page INT DEFAULT 1,
                p_per_page INT DEFAULT 10,
                p_search TEXT DEFAULT NULL,
                p_status VARCHAR DEFAULT NULL,
                p_audit_period_id BIGINT DEFAULT NULL,
                p_lead_auditor_id BIGINT DEFAULT NULL,
                p_start_date DATE DEFAULT NULL,
                p_end_date DATE DEFAULT NULL,
                p_sort_by VARCHAR DEFAULT 'newest'
            )
            RETURNS JSONB
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_offset INT;
                v_total_count INT;
                v_total_pages INT;
                v_records JSONB;
                v_order_clause TEXT;
            BEGIN
                -- Calculate offset
                v_offset := (p_page - 1) * p_per_page;

                -- Determine sort order
                v_order_clause := CASE p_sort_by
                    WHEN 'oldest' THEN 'created_at ASC'
                    WHEN 'az' THEN 'session_name ASC'
                    WHEN 'za' THEN 'session_name DESC'
                    WHEN 'scheduled_asc' THEN 'scheduled_date ASC NULLS LAST'
                    WHEN 'scheduled_desc' THEN 'scheduled_date DESC NULLS LAST'
                    ELSE 'created_at DESC' -- 'newest'
                END;

                -- Get total count with filters
                SELECT COUNT(*)
                INTO v_total_count
                FROM audit_sessions s
                WHERE s.tenant_id = p_tenant_id
                    AND s.deleted_at IS NULL
                    AND (p_search IS NULL OR (
                        s.session_code ILIKE '%' || p_search || '%' OR
                        s.session_name ILIKE '%' || p_search || '%' OR
                        s.description ILIKE '%' || p_search || '%'
                    ))
                    AND (p_status IS NULL OR s.status = p_status)
                    AND (p_audit_period_id IS NULL OR s.audit_period_id = p_audit_period_id)
                    AND (p_lead_auditor_id IS NULL OR s.lead_auditor_id = p_lead_auditor_id)
                    AND (p_start_date IS NULL OR s.scheduled_date >= p_start_date)
                    AND (p_end_date IS NULL OR s.scheduled_date <= p_end_date);

                -- Calculate total pages
                v_total_pages := CEIL(v_total_count::NUMERIC / p_per_page);

                -- Get paginated records
                EXECUTE format('
                    SELECT COALESCE(jsonb_agg(row_to_json), ''[]''::jsonb)
                    FROM (
                        SELECT
                            s.id,
                            s.session_code,
                            s.session_name,
                            s.description,
                            s.audit_period_id,
                            ap.period_name as audit_period_name,
                            s.scheduled_date,
                            s.actual_start_date,
                            s.actual_end_date,
                            s.lead_auditor_id,
                            u.name as lead_auditor_name,
                            s.status,
                            s.risk_level,
                            s.findings_count,
                            s.critical_findings,
                            s.major_findings,
                            s.minor_findings,
                            s.reported_zombie_assets_count,
                            s.created_at,
                            s.updated_at,
                            -- Assigned auditors with details
                            COALESCE(
                                (SELECT jsonb_agg(
                                    jsonb_build_object(
                                        ''id'', au.id,
                                        ''user_name'', au.user_name,
                                        ''name'', au.name,
                                        ''email'', au.email,
                                        ''profile_image'', au.profile_image,
                                        ''designation'', d.designation,
                                        ''role'', asa.role
                                    )
                                )
                                FROM audit_sessions_auditors asa
                                INNER JOIN users au ON au.id = asa.user_id
                                LEFT JOIN designations d ON d.id = au.designation_id
                                WHERE asa.audit_session_id = s.id
                                    AND asa.deleted_at IS NULL),
                                ''[]''::jsonb
                            ) as assigned_auditors,
                            -- Assigned auditors count
                            (SELECT COUNT(*)
                             FROM audit_sessions_auditors asa
                             WHERE asa.audit_session_id = s.id
                                AND asa.deleted_at IS NULL) as assigned_auditors_count,
                            -- Assigned audit groups count
                            (SELECT COUNT(*)
                             FROM audit_sessions_groups asg
                             WHERE asg.audit_session_id = s.id
                                AND asg.deleted_at IS NULL) as assigned_groups_count,
                            -- Zombie assets count (uses denormalised column; legacy table was dropped)
                            COALESCE(s.reported_zombie_assets_count, 0) as zombie_assets_count
                        FROM audit_sessions s
                        LEFT JOIN audit_periods ap ON ap.id = s.audit_period_id
                        LEFT JOIN users u ON u.id = s.lead_auditor_id
                        WHERE s.tenant_id = $1
                            AND s.deleted_at IS NULL
                            AND ($2 IS NULL OR (
                                s.session_code ILIKE ''%%'' || $2 || ''%%'' OR
                                s.session_name ILIKE ''%%'' || $2 || ''%%'' OR
                                s.description ILIKE ''%%'' || $2 || ''%%''
                            ))
                            AND ($3 IS NULL OR s.status = $3)
                            AND ($4 IS NULL OR s.audit_period_id = $4)
                            AND ($5 IS NULL OR s.lead_auditor_id = $5)
                            AND ($6 IS NULL OR s.scheduled_date >= $6)
                            AND ($7 IS NULL OR s.scheduled_date <= $7)
                        ORDER BY %s
                        LIMIT $8 OFFSET $9
                    ) row_to_json
                ', v_order_clause)
                INTO v_records
                USING p_tenant_id, p_search, p_status, p_audit_period_id, 
                      p_lead_auditor_id, p_start_date, p_end_date, 
                      p_per_page, v_offset;

                RETURN jsonb_build_object(
                    'status', 'SUCCESS',
                    'records', v_records,
                    'pagination', jsonb_build_object(
                        'current_page', p_page,
                        'per_page', p_per_page,
                        'total', v_total_count,
                        'total_pages', v_total_pages
                    )
                );

            EXCEPTION
                WHEN OTHERS THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'Failed to retrieve audit sessions list: ' || SQLERRM
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
        DB::unprepared('DROP FUNCTION IF EXISTS get_audit_sessions_list CASCADE');
    }
};
