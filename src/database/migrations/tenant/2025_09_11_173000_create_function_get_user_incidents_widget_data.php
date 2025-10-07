<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            -- Create function for work order tickets

            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_user_incidents_widget_data'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
          
            CREATE OR REPLACE FUNCTION get_user_incidents_widget_data(
                IN p_user_id BIGINT DEFAULT NULL,
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_type TEXT DEFAULT 'web',
                IN p_count INT DEFAULT 3
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                widget_data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                all_tickets JSONB;
                incident_reports JSONB;
                maintenance_alerts JSONB;
                all_tickets_count INT;
                incident_reports_count INT;
                maintenance_alerts_count INT;
                all_tickets_remaining INT;
                incident_reports_remaining INT;
                maintenance_alerts_remaining INT;
                result_data JSONB;
            BEGIN
                -- Get total count for all tickets
                SELECT COUNT(*)
                INTO all_tickets_count
                FROM work_order_tickets wot
                JOIN asset_items ai ON wot.asset_id = ai.id
                JOIN assets a ON ai.asset_id = a.id
                WHERE (p_user_id IS NULL OR ai.responsible_person = p_user_id)
                AND (p_tenant_id IS NULL OR ai.tenant_id = p_tenant_id)
                AND wot.deleted_at IS NULL 
                AND wot.isactive = TRUE
                AND ai.deleted_at IS NULL 
                AND ai.isactive = TRUE
                AND wot.is_closed = FALSE
                AND a.deleted_at IS NULL 
                AND a.isactive = TRUE;

                -- Get total count for incident reports
                SELECT COUNT(*)
                INTO incident_reports_count
                FROM work_order_tickets wot
                JOIN asset_items ai ON wot.asset_id = ai.id
                JOIN assets a ON ai.asset_id = a.id
                WHERE wot.type = 'incident_report'
                AND (p_user_id IS NULL OR ai.responsible_person = p_user_id)
                AND (p_tenant_id IS NULL OR ai.tenant_id = p_tenant_id)
                AND wot.deleted_at IS NULL 
                AND wot.isactive = TRUE
                AND ai.deleted_at IS NULL 
                AND ai.isactive = TRUE
                AND wot.is_closed = FALSE
                AND a.deleted_at IS NULL 
                AND a.isactive = TRUE;

                -- Get total count for maintenance alerts
                SELECT COUNT(*)
                INTO maintenance_alerts_count
                FROM work_order_tickets wot
                JOIN asset_items ai ON wot.asset_id = ai.id
                JOIN assets a ON ai.asset_id = a.id
                WHERE wot.type = 'maintenance_alerts'
                AND (p_user_id IS NULL OR ai.responsible_person = p_user_id)
                AND (p_tenant_id IS NULL OR ai.tenant_id = p_tenant_id)
                AND wot.deleted_at IS NULL 
                AND wot.isactive = TRUE
                AND wot.is_closed = FALSE
                AND ai.deleted_at IS NULL 
                AND ai.isactive = TRUE
                AND a.deleted_at IS NULL 
                AND a.isactive = TRUE;

                -- Calculate remaining counts
                all_tickets_remaining := GREATEST(all_tickets_count - p_count, 0);
                incident_reports_remaining := GREATEST(incident_reports_count - p_count, 0);
                maintenance_alerts_remaining := GREATEST(maintenance_alerts_count - p_count, 0);
                -- Get all tickets (newest p_count)
                SELECT COALESCE(jsonb_agg(ticket_data ORDER BY created_at DESC), '[]'::jsonb)
                INTO all_tickets
                FROM (
                    SELECT jsonb_build_object(
                        'id', wot.id,
                        'type', wot.type,
                        'reference_id', wot.reference_id,
                        'asset_id', wot.asset_id,
                        'asset_name', a.name,
                        'asset_tag', ai.asset_tag,
                        'asset_model_number', ai.model_number,
                        'asset_serial_number', ai.serial_number,
                        'responsible_person', u.name,
                        'is_get_action', wot.is_get_action,
                        'is_closed', wot.is_closed,
                        'created_at', wot.created_at,
                        'updated_at', wot.updated_at,
                        'details', CASE 
                            WHEN wot.type = 'incident_report' THEN (
                                SELECT to_jsonb(ir)
                                FROM (
                                    SELECT 
                                        amir.id,
                                        amir.incident_date_time,
                                        amir.incident_description,
                                        amir.priority_level,
                                        pl.value::TEXT AS priority_level_name,
                                        amir.incident_reports_status,
                                        amir.report_number,
                                        amir.created_at,
                                        amir.updated_at
                                    FROM asset_maintenance_incident_reports amir
                                    LEFT JOIN asset_maintenance_incident_report_priority_levels pl ON amir.priority_level = pl.id
                                    WHERE amir.id = wot.reference_id
                                    AND amir.deleted_at IS NULL
                                    AND amir.isactive = TRUE
                                ) AS ir
                            )
                            WHEN wot.type = 'maintenance_alerts' THEN (
                                SELECT to_jsonb(ma)
                                FROM (
                                    SELECT 
                                        aicq.id,
                                        aicq.asset_item,
                                        aicq.source::TEXT AS queries_type,
                                        aicq.created_at,
                                        aicq.updated_at
                                    FROM asset_item_action_queries aicq
                                    WHERE aicq.id = wot.reference_id
                                    AND aicq.deleted_at IS NULL
                                    AND aicq.isactive = TRUE
                                ) AS ma
                            )
                            ELSE NULL
                        END
                    ) AS ticket_data,
                    wot.created_at
                    FROM work_order_tickets wot
                    JOIN asset_items ai ON wot.asset_id = ai.id
                    JOIN assets a ON ai.asset_id = a.id
                    JOIN users u ON ai.responsible_person = u.id
                    WHERE (p_user_id IS NULL OR ai.responsible_person = p_user_id)
                    AND (p_tenant_id IS NULL OR ai.tenant_id = p_tenant_id)
                    AND wot.deleted_at IS NULL 
                    AND wot.isactive = TRUE
                    AND ai.deleted_at IS NULL 
                    AND ai.isactive = TRUE
                    AND a.deleted_at IS NULL 
                    AND a.isactive = TRUE
                    AND wot.is_closed = FALSE
                    ORDER BY wot.created_at DESC
                    LIMIT p_count
                ) AS all_data;

                -- Get incident report tickets (newest p_count)
                SELECT COALESCE(jsonb_agg(ticket_data ORDER BY created_at DESC), '[]'::jsonb)
                INTO incident_reports
                FROM (
                    SELECT jsonb_build_object(
                        'id', wot.id,
                        'type', wot.type,
                        'reference_id', wot.reference_id,
                        'asset_id', wot.asset_id,
                        'asset_name', a.name,
                        'asset_tag', ai.asset_tag,
                        'asset_model_number', ai.model_number,
                        'asset_serial_number', ai.serial_number,
                        'responsible_person', u.name,
                        'is_get_action', wot.is_get_action,
                        'is_closed', wot.is_closed,
                        'created_at', wot.created_at,
                        'updated_at', wot.updated_at,
                        'details', (
                            SELECT to_jsonb(ir)
                            FROM (
                                SELECT 
                                    amir.id,
                                    amir.incident_date_time,
                                    amir.incident_description,
                                    amir.priority_level,
                                    pl.value::TEXT AS priority_level_name,
                                    amir.incident_reports_status,
                                    amir.report_number,
                                    amir.created_at,
                                    amir.updated_at
                                FROM asset_maintenance_incident_reports amir
                                LEFT JOIN asset_maintenance_incident_report_priority_levels pl ON amir.priority_level = pl.id
                                WHERE amir.id = wot.reference_id
                                AND amir.deleted_at IS NULL
                                AND amir.isactive = TRUE
                            ) AS ir
                        )
                    ) AS ticket_data,
                    wot.created_at
                    FROM work_order_tickets wot
                    JOIN asset_items ai ON wot.asset_id = ai.id
                    JOIN assets a ON ai.asset_id = a.id
                    JOIN users u ON ai.responsible_person = u.id
                    WHERE wot.type = 'incident_report'
                    AND (p_user_id IS NULL OR ai.responsible_person = p_user_id)
                    AND (p_tenant_id IS NULL OR ai.tenant_id = p_tenant_id)
                    AND wot.deleted_at IS NULL 
                    AND wot.isactive = TRUE
                    AND wot.is_closed = FALSE
                    AND ai.deleted_at IS NULL 
                    AND ai.isactive = TRUE
                    AND a.deleted_at IS NULL 
                    AND a.isactive = TRUE
                    ORDER BY wot.created_at DESC
                    LIMIT p_count
                ) AS incident_data;

                -- Get maintenance alert tickets (newest p_count)
                SELECT COALESCE(jsonb_agg(ticket_data ORDER BY created_at DESC), '[]'::jsonb)
                INTO maintenance_alerts
                FROM (
                    SELECT jsonb_build_object(
                        'id', wot.id,
                        'type', wot.type,
                        'reference_id', wot.reference_id,
                        'asset_id', wot.asset_id,
                        'asset_name', a.name,
                        'asset_tag', ai.asset_tag,
                        'asset_model_number', ai.model_number,
                        'asset_serial_number', ai.serial_number,
                        'responsible_person', u.name,
                        'is_get_action', wot.is_get_action,
                        'is_closed', wot.is_closed,
                        'created_at', wot.created_at,
                        'updated_at', wot.updated_at,
                        'details', (
                            SELECT to_jsonb(ma)
                            FROM (
                                SELECT 
                                    aicq.id,
                                    aicq.asset_item,
                                    aicq.source::TEXT AS queries_type,
                                    aicq.created_at,
                                    aicq.updated_at
                                FROM asset_item_action_queries aicq
                                WHERE aicq.id = wot.reference_id
                                AND aicq.deleted_at IS NULL
                                AND aicq.isactive = TRUE
                            ) AS ma
                        )
                    ) AS ticket_data,
                    wot.created_at
                    FROM work_order_tickets wot
                    JOIN asset_items ai ON wot.asset_id = ai.id
                    JOIN assets a ON ai.asset_id = a.id
                    JOIN users u ON ai.responsible_person = u.id
                    WHERE wot.type = 'maintenance_alerts'
                    AND (p_user_id IS NULL OR ai.responsible_person = p_user_id)
                    AND (p_tenant_id IS NULL OR ai.tenant_id = p_tenant_id)
                    AND wot.deleted_at IS NULL 
                    AND wot.isactive = TRUE
                    AND ai.deleted_at IS NULL 
                    AND ai.isactive = TRUE
                    AND wot.is_closed = FALSE
                    AND a.deleted_at IS NULL 
                    AND a.isactive = TRUE
                    ORDER BY wot.created_at DESC
                    LIMIT p_count
                ) AS maintenance_data;

                -- Build the final result JSON
                result_data := jsonb_build_object(
                    'all', jsonb_build_object(
                        'data', all_tickets,
                        'total_count', all_tickets_count,
                        'returned_count', jsonb_array_length(all_tickets),
                        'remaining_count', all_tickets_remaining
                    ),
                    'incident_report', jsonb_build_object(
                        'data', incident_reports,
                        'total_count', incident_reports_count,
                        'returned_count', jsonb_array_length(incident_reports),
                        'remaining_count', incident_reports_remaining
                    ),
                    'maintenance_alerts', jsonb_build_object(
                        'data', maintenance_alerts,
                        'total_count', maintenance_alerts_count,
                        'returned_count', jsonb_array_length(maintenance_alerts),
                        'remaining_count', maintenance_alerts_remaining
                    )
                );

                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT,
                    'User incidents widget data fetched successfully'::TEXT,
                    result_data;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_user_incidents_widget_data(BIGINT, BIGINT, TEXT, INT);");
    }
};
