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
            DROP FUNCTION IF EXISTS get_user_related_work_order_tickets(BIGINT, BIGINT);

            CREATE OR REPLACE FUNCTION get_user_related_work_order_tickets(
                IN p_user_id BIGINT DEFAULT NULL,
                IN p_tenant_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                ticket_id BIGINT,
                ticket_type TEXT,
                is_get_action BOOLEAN,
                is_closed BOOLEAN,
                asset_id BIGINT,
                asset_name TEXT,
                asset_tag TEXT,
                asset_model_number TEXT,
                asset_serial_number TEXT,
                asset_responsible_person TEXT,
                team_id BIGINT,
                team_name TEXT,
                details JSONB
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT,
                    'Work order details fetched successfully'::TEXT,
                    wot.id AS ticket_id,
                    wot.type::TEXT AS ticket_type,
                    wot.is_get_action,
                    wot.is_closed,
                    a.id AS asset_id,
                    a.name::TEXT AS asset_name,
                    ai.asset_tag::TEXT AS asset_tag,
                    ai.model_number::TEXT AS asset_model_number,
                    ai.serial_number::TEXT AS asset_serial_number,
                    u.name::TEXT AS asset_responsible_person,
                    mt.id AS team_id,
                    mt.team_name::TEXT,
                    CASE 
                        WHEN wot.type = 'incident_report' THEN (
                            SELECT to_jsonb(ir)
                            FROM (
                                SELECT 
                                    amir.id,
                                    amir.asset,
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
                                    amir.incident_reports_status,
                                    amir.report_number,
                                    amir.created_at,
                                    amir.updated_at
                                FROM asset_maintenance_incident_reports amir
                                LEFT JOIN asset_maintenance_incident_report_priority_levels pl ON amir.priority_level = pl.id
                                LEFT JOIN users uv ON amir.verified_by = uv.id
                                LEFT JOIN users uc ON amir.report_created_by = uc.id
                                WHERE amir.asset = wot.asset_id
                                AND amir.deleted_at IS NULL
                                AND amir.isactive = TRUE
                                ORDER BY amir.created_at DESC
                                LIMIT 1
                            ) AS ir
                        )
                        WHEN wot.type = 'maintenance_alerts' THEN (
                            SELECT to_jsonb(ma)
                            FROM (
                                SELECT 
                                    aicq.id,
                                    a.name::TEXT AS asset_group_name,
                                    aicq.asset_item,
                                    ai.serial_number::TEXT AS asset_item_serial_number,
                                    ai.responsible_person,
                                    u.name::TEXT AS responsible_personal_name,
                                    u.email::TEXT AS responsible_personal_email,
                                    ai.warranty::TEXT,
                                    ai.warranty_exparing_at,
                                    ai.insurance_number::TEXT,
                                    ai.insurance_exparing_at,
                                    aicq.source::TEXT AS queries_type,
                                    CASE 
                                        WHEN aicq.source = 'manufacturer' THEN aimrms.maintain_schedule_parameters
                                        WHEN aicq.source = 'usage' THEN aiubms.maintain_schedule_parameters
                                        WHEN aicq.source = 'asset_group_manufacturer' THEN amrms.maintain_schedule_parameters
                                        WHEN aicq.source = 'asset_group_usage' THEN aubms.maintain_schedule_parameters
                                        ELSE NULL
                                    END AS maintain_schedule_parameters,
                                    CASE 
                                        WHEN aicq.source = 'manufacturer' THEN amsp.name::TEXT
                                        WHEN aicq.source = 'usage' THEN ausp.name::TEXT
                                        WHEN aicq.source = 'asset_group_manufacturer' THEN agmsp.name::TEXT
                                        WHEN aicq.source = 'asset_group_usage' THEN agusp.name::TEXT
                                        WHEN aicq.source = 'asset_item_critically_based_schedule_check' THEN aicbms.schedule::TEXT
                                        WHEN aicq.source = 'asset_critically_based_schedule_check' THEN acbms.schedule::TEXT
                                        ELSE NULL
                                    END AS maintain_schedule_parameters_name,
                                    CASE
                                        WHEN aicq.source = 'manufacturer' THEN 'asset_item_manufacturer_recommendation_maintain_schedules'
                                        WHEN aicq.source = 'usage' THEN 'asset_item_usage_based_maintain_schedules'
                                        WHEN aicq.source = 'asset_group_manufacturer' THEN 'asset_manufacturer_recommendation_maintain_schedules'
                                        WHEN aicq.source = 'asset_group_usage' THEN 'asset_usage_based_maintain_schedules'
                                        WHEN aicq.source = 'asset_item_critically_based_schedule_check' THEN 'asset_item_critically_based_maintain_schedules'
                                        WHEN aicq.source = 'asset_critically_based_schedule_check' THEN 'asset_critically_based_maintain_schedules'
                                        WHEN aicq.source = 'asset_item_maintenance_tasks_schedule_check' THEN 'asset_item_maintenance_tasks'
                                        WHEN aicq.source = 'asset_maintenance_tasks_schedule_check' THEN 'maintenance_tasks'
                                        ELSE NULL
                                    END AS maintain_schedule_table,
                                    CASE 
                                        WHEN aicq.source = 'manufacturer' THEN aimrms.limit_or_value
                                        WHEN aicq.source = 'usage' THEN aiubms.limit_or_value
                                        WHEN aicq.source = 'asset_group_manufacturer' THEN amrms.limit_or_value
                                        WHEN aicq.source = 'asset_group_usage' THEN aubms.limit_or_value
                                        WHEN aicq.source = 'asset_item_critically_based_schedule_check' THEN aicbms.expected_results
                                        WHEN aicq.source = 'asset_critically_based_schedule_check' THEN acbms.expected_results
                                        WHEN aicq.source = 'asset_item_maintenance_tasks_schedule_check' THEN aimt.expected_results
                                        WHEN aicq.source = 'asset_maintenance_tasks_schedule_check' THEN mt.expected_results
                                        ELSE NULL
                                    END::TEXT AS limit_or_value,
                                    CASE 
                                        WHEN aicq.source = 'manufacturer' THEN aimrms.operator
                                        WHEN aicq.source = 'usage' THEN aiubms.operator
                                        WHEN aicq.source = 'asset_group_manufacturer' THEN amrms.operator
                                        WHEN aicq.source = 'asset_group_usage' THEN aubms.operator
                                        ELSE NULL
                                    END::TEXT AS operator,
                                    CASE 
                                        WHEN aicq.source = 'manufacturer' THEN aimrms.reading_parameters
                                        WHEN aicq.source = 'usage' THEN aiubms.reading_parameters
                                        WHEN aicq.source = 'asset_group_manufacturer' THEN amrms.reading_parameters
                                        WHEN aicq.source = 'asset_group_usage' THEN aubms.reading_parameters
                                        WHEN aicq.source = 'asset_item_critically_based_schedule_check' THEN aicbms.comments
                                        WHEN aicq.source = 'asset_critically_based_schedule_check' THEN acbms.comments
                                        ELSE NULL
                                    END::TEXT AS reading_parameters,
                                    CASE 
                                        WHEN aicq.source = 'asset_item_critically_based_schedule_check' THEN aicbms.assessment_description
                                        WHEN aicq.source = 'asset_critically_based_schedule_check' THEN acbms.assessment_description
                                        WHEN aicq.source = 'asset_item_maintenance_tasks_schedule_check' THEN aimt.maintenance_tasks_description
                                        WHEN aicq.source = 'asset_maintenance_tasks_schedule_check' THEN mt.maintenance_tasks_description
                                        ELSE NULL
                                    END::TEXT AS assessment_description,
                                    CASE 
                                        WHEN aicq.source = 'asset_item_critically_based_schedule_check' THEN aicbms.schedule
                                        WHEN aicq.source = 'asset_critically_based_schedule_check' THEN acbms.schedule
                                        WHEN aicq.source = 'asset_item_maintenance_tasks_schedule_check' THEN aimt.schedule
                                        WHEN aicq.source = 'asset_maintenance_tasks_schedule_check' THEN mt.schedule
                                        ELSE NULL
                                    END::TEXT AS schedule,
                                    CASE 
                                        WHEN aicq.source = 'asset_item_maintenance_tasks_schedule_check' THEN mtt.name
                                        WHEN aicq.source = 'asset_maintenance_tasks_schedule_check' THEN mtt2.name
                                        ELSE NULL
                                    END::TEXT AS maintenance_task_type_name,
                                    aicq.created_at,
                                    aicq.updated_at
                                FROM asset_item_action_queries aicq
                                JOIN asset_items ai ON aicq.asset_item = ai.id
                                JOIN assets a ON ai.asset_id = a.id
                                JOIN users u ON ai.responsible_person = u.id
                                LEFT JOIN asset_item_manufacturer_recommendation_maintain_schedules aimrms ON ai.id = aimrms.asset_item
                                LEFT JOIN asset_maintain_schedule_parameters amsp ON aimrms.maintain_schedule_parameters = amsp.id
                                LEFT JOIN asset_item_usage_based_maintain_schedules aiubms ON ai.id = aiubms.asset_item
                                LEFT JOIN asset_maintain_schedule_parameters ausp ON aiubms.maintain_schedule_parameters = ausp.id
                                LEFT JOIN asset_manufacturer_recommendation_maintain_schedules amrms ON ai.asset_id = amrms.asset
                                LEFT JOIN asset_maintain_schedule_parameters agmsp ON amrms.maintain_schedule_parameters = agmsp.id
                                LEFT JOIN asset_usage_based_maintain_schedules aubms ON ai.asset_id = aubms.asset
                                LEFT JOIN asset_maintain_schedule_parameters agusp ON aubms.maintain_schedule_parameters = agusp.id
                                LEFT JOIN asset_item_critically_based_maintain_schedules aicbms ON aicq.recommendation_id = aicbms.id
                                LEFT JOIN asset_critically_based_maintain_schedules acbms ON aicq.recommendation_id = acbms.id
                                LEFT JOIN asset_item_maintenance_tasks aimt ON aicq.recommendation_id = aimt.id
                                LEFT JOIN maintenance_tasks mt ON aicq.recommendation_id = mt.id
                                LEFT JOIN maintenance_tasks_type mtt ON aimt.task_type = mtt.id
                                LEFT JOIN maintenance_tasks_type mtt2 ON mt.task_type = mtt2.id
                                WHERE aicq.asset_item = wot.asset_id
                                AND aicq.is_get_action = FALSE
                                AND aicq.deleted_at IS NULL
                                AND aicq.isactive = TRUE
                                ORDER BY aicq.created_at DESC
                                LIMIT 1
                            ) AS ma
                        )
                        ELSE NULL
                    END AS details
                FROM maintenance_team_members mtm
                JOIN maintenance_teams mt ON mt.id = mtm.team_id
                JOIN maintenance_team_related_asset_groups mtag ON mtag.team_id = mt.id
                JOIN assets a ON a.id = mtag.asset_group_id
                JOIN asset_items ai ON ai.asset_id = a.id
                JOIN work_order_tickets wot ON wot.asset_id = ai.id
                JOIN users u ON ai.responsible_person = u.id
                WHERE (p_user_id IS NULL OR mtm.user_id = p_user_id)
                AND (p_tenant_id IS NULL OR mtm.tenant_id = p_tenant_id)
                AND mtm.deleted_at IS NULL AND mtm.isactive = TRUE
                AND mt.deleted_at IS NULL AND mt.isactive = TRUE
                AND mtag.deleted_at IS NULL AND mtag.isactive = TRUE
                AND a.deleted_at IS NULL AND a.isactive = TRUE
                AND wot.deleted_at IS NULL AND wot.isactive = TRUE;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_user_related_work_order_tickets(BIGINT, BIGINT);");
    }
};