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
        // DB::unprepared(<<<SQL
        //     CREATE OR REPLACE FUNCTION get_asset_item_action_queries_details(
        //         p_user_id BIGINT,
        //         p_tenant_id BIGINT,
        //         p_asset_item_id BIGINT DEFAULT NULL,
        //         action_queries_id BIGINT DEFAULT NULL
        //     ) 
        //     RETURNS TABLE (
        //         status TEXT,
        //         message TEXT,
        //         id BIGINT,
        //         asset_group_name TEXT,
        //         asset_item BIGINT,
        //         asset_item_serial_number TEXT,
        //         warranty TEXT,
        //         warranty_exparing_at,
        //         insurance_number TEXT,
        //         insurance_exparing_at,
        //         queries_type TEXT,
        //         maintain_schedule_parameters BIGINT,
        //         maintain_schedule_parameters_name TEXT,
        //         maintain_schedule_table TEXT,
        //         limit_or_value TEXT,
        //         operator TEXT,
        //         reading_parameters TEXT,
        //         created_at TIMESTAMP,
        //         updated_at TIMESTAMP
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     DECLARE
        //         record_count INT;
        //     BEGIN
        //         IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
        //             RETURN QUERY SELECT 
        //                 'FAILURE', 'No matching records found', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL;
        //             RETURN;
        //         END IF;

        //         IF p_asset_item_id IS NOT NULL AND p_asset_item_id < 0 THEN
        //             RETURN QUERY SELECT 
        //                 'FAILURE', 'No matching records found', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL;
        //             RETURN;
        //         END IF;

        //         IF action_queries_id IS NOT NULL AND action_queries_id < 0 THEN
        //             RETURN QUERY SELECT 
        //                 'FAILURE', 'No matching records found', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL;
        //             RETURN;
        //         END IF;

        //         SELECT COUNT(*) INTO record_count
        //         FROM asset_item_action_queries aicq
        //         JOIN asset_items ai ON aicq.asset_item = ai.id
        //         JOIN assets a ON ai.asset_id = a.id
        //         JOIN users u ON ai.responsible_person = u.id

        //         LEFT JOIN asset_item_manufacturer_recommendation_maintain_schedules aimrms
        //             ON ai.id = aimrms.asset_item AND aicq.source = 'manufacturer'
        //         LEFT JOIN asset_maintain_schedule_parameters amsp ON aimrms.maintain_schedule_parameters = amsp.id

        //         LEFT JOIN asset_item_usage_based_maintain_schedules aiubms
        //             ON ai.id = aiubms.asset_item AND aicq.source = 'usage'
        //         LEFT JOIN asset_maintain_schedule_parameters ausp ON aiubms.maintain_schedule_parameters = ausp.id

        //         LEFT JOIN asset_manufacturer_recommendation_maintain_schedules amrms
        //             ON ai.asset_id = amrms.asset AND aicq.source = 'asset_group_manufacturer'
        //         LEFT JOIN asset_maintain_schedule_parameters agmsp ON amrms.maintain_schedule_parameters = agmsp.id

        //         LEFT JOIN asset_usage_based_maintain_schedules aubms
        //             ON ai.asset_id = aubms.asset AND aicq.source = 'asset_group_usage'
        //         LEFT JOIN asset_maintain_schedule_parameters agusp ON aubms.maintain_schedule_parameters = agusp.id

        //         WHERE (p_asset_item_id IS NULL OR ai.id = p_asset_item_id)
        //         AND (action_queries_id IS NULL OR aicq.id = action_queries_id)
        //         AND u.id = p_user_id
        //         AND u.tenant_id = p_tenant_id;

        //         IF record_count = 0 THEN
        //             RETURN QUERY SELECT 
        //                 'FAILURE', 'No matching records found', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL;
        //             RETURN;
        //         END IF;

        //         RETURN QUERY
        //         SELECT
        //             'SUCCESS',
        //             'Query successful',
        //             aicq.id,
        //             a.name::TEXT AS asset_group_name,
        //             aicq.asset_item,
        //             ai.serial_number::TEXT,
        //             ai.warranty::TEXT,
        //             ai.warranty_exparing_at,
        //             ai.insurance_number::TEXT
        //             ai.insurance_exparing_at,
        //             aicq.source::TEXT AS queries_type,
        //             CASE 
        //                 WHEN aicq.source = 'manufacturer' THEN aimrms.maintain_schedule_parameters
        //                 WHEN aicq.source = 'usage' THEN aiubms.maintain_schedule_parameters
        //                 WHEN aicq.source = 'asset_group_manufacturer' THEN amrms.maintain_schedule_parameters
        //                 WHEN aicq.source = 'asset_group_usage' THEN aubms.maintain_schedule_parameters
        //                 ELSE NULL
        //             END,
        //             CASE 
        //                 WHEN aicq.source = 'manufacturer' THEN amsp.name::TEXT
        //                 WHEN aicq.source = 'usage' THEN ausp.name::TEXT
        //                 WHEN aicq.source = 'asset_group_manufacturer' THEN agmsp.name::TEXT
        //                 WHEN aicq.source = 'asset_group_usage' THEN agusp.name::TEXT
        //                 ELSE NULL
        //             END,
        //             CASE
        //                 WHEN aicq.source = 'manufacturer' THEN 'asset_item_manufacturer_recommendation_maintain_schedules'
        //                 WHEN aicq.source = 'usage' THEN 'asset_item_usage_based_maintain_schedules'
        //                 WHEN aicq.source = 'asset_group_manufacturer' THEN 'asset_manufacturer_recommendation_maintain_schedules'
        //                 WHEN aicq.source = 'asset_group_usage' THEN 'asset_usage_based_maintain_schedules'
        //                 ELSE NULL
        //             END,
        //             CASE 
        //                 WHEN aicq.source = 'manufacturer' THEN aimrms.limit_or_value
        //                 WHEN aicq.source = 'usage' THEN aiubms.limit_or_value
        //                 WHEN aicq.source = 'asset_group_manufacturer' THEN amrms.limit_or_value
        //                 WHEN aicq.source = 'asset_group_usage' THEN aubms.limit_or_value
        //                 ELSE NULL
        //             END::TEXT,
        //             CASE 
        //                 WHEN aicq.source = 'manufacturer' THEN aimrms.operator
        //                 WHEN aicq.source = 'usage' THEN aiubms.operator
        //                 WHEN aicq.source = 'asset_group_manufacturer' THEN amrms.operator
        //                 WHEN aicq.source = 'asset_group_usage' THEN aubms.operator
        //                 ELSE NULL
        //             END::TEXT,
        //             CASE 
        //                 WHEN aicq.source = 'manufacturer' THEN aimrms.reading_parameters
        //                 WHEN aicq.source = 'usage' THEN aiubms.reading_parameters
        //                 WHEN aicq.source = 'asset_group_manufacturer' THEN amrms.reading_parameters
        //                 WHEN aicq.source = 'asset_group_usage' THEN aubms.reading_parameters
        //                 ELSE NULL
        //             END::TEXT,
        //             aicq.created_at,
        //             aicq.updated_at
        //         FROM asset_item_action_queries aicq
        //         JOIN asset_items ai ON aicq.asset_item = ai.id
        //         JOIN assets a ON ai.asset_id = a.id
        //         JOIN users u ON ai.responsible_person = u.id

        //         LEFT JOIN asset_item_manufacturer_recommendation_maintain_schedules aimrms
        //             ON ai.id = aimrms.asset_item AND aicq.source = 'manufacturer'
        //         LEFT JOIN asset_maintain_schedule_parameters amsp ON aimrms.maintain_schedule_parameters = amsp.id

        //         LEFT JOIN asset_item_usage_based_maintain_schedules aiubms
        //             ON ai.id = aiubms.asset_item AND aicq.source = 'usage'
        //         LEFT JOIN asset_maintain_schedule_parameters ausp ON aiubms.maintain_schedule_parameters = ausp.id

        //         LEFT JOIN asset_manufacturer_recommendation_maintain_schedules amrms
        //             ON ai.asset_id = amrms.asset AND aicq.source = 'asset_group_manufacturer'
        //         LEFT JOIN asset_maintain_schedule_parameters agmsp ON amrms.maintain_schedule_parameters = agmsp.id

        //         LEFT JOIN asset_usage_based_maintain_schedules aubms
        //             ON ai.asset_id = aubms.asset AND aicq.source = 'asset_group_usage'
        //         LEFT JOIN asset_maintain_schedule_parameters agusp ON aubms.maintain_schedule_parameters = agusp.id

        //         WHERE (p_asset_item_id IS NULL OR ai.id = p_asset_item_id)
        //         AND (action_queries_id IS NULL OR aicq.id = action_queries_id)
        //         AND u.id = p_user_id
        //         AND u.tenant_id = p_tenant_id;
        //     END;
        //     $$;
        // SQL);

        // DB::unprepared(<<<SQL
        //     CREATE OR REPLACE FUNCTION get_asset_item_action_queries_details(
        //         p_user_id BIGINT,
        //         p_tenant_id BIGINT,
        //         p_asset_item_id BIGINT DEFAULT NULL,
        //         action_queries_id BIGINT DEFAULT NULL
        //     ) 
        //     RETURNS TABLE (
        //         status TEXT,
        //         message TEXT,
        //         id BIGINT,
        //         asset_group_name TEXT,
        //         asset_item BIGINT,
        //         asset_item_serial_number TEXT,
        //         warranty TEXT,
        //         warranty_exparing_at TIMESTAMP,
        //         insurance_number TEXT,
        //         insurance_exparing_at TIMESTAMP,
        //         queries_type TEXT,
        //         maintain_schedule_parameters BIGINT,
        //         maintain_schedule_parameters_name TEXT,
        //         maintain_schedule_table TEXT,
        //         limit_or_value TEXT,
        //         operator TEXT,
        //         reading_parameters TEXT,
        //         assessment_description TEXT,
        //         schedule TEXT,
        //         expected_results TEXT,
        //         comments TEXT,
        //         created_at TIMESTAMP,
        //         updated_at TIMESTAMP
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     DECLARE
        //         record_count INT;
        //     BEGIN
        //         IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
        //             RETURN QUERY SELECT 
        //                 'FAILURE', 'No matching records found', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL;
        //             RETURN;
        //         END IF;

        //         IF p_asset_item_id IS NOT NULL AND p_asset_item_id < 0 THEN
        //             RETURN QUERY SELECT 
        //                 'FAILURE', 'No matching records found', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL;
        //             RETURN;
        //         END IF;

        //         IF action_queries_id IS NOT NULL AND action_queries_id < 0 THEN
        //             RETURN QUERY SELECT 
        //                 'FAILURE', 'No matching records found', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL;
        //             RETURN;
        //         END IF;

        //         SELECT COUNT(*) INTO record_count
        //         FROM asset_item_action_queries aicq
        //         JOIN asset_items ai ON aicq.asset_item = ai.id
        //         JOIN users u ON ai.responsible_person = u.id
        //         WHERE (p_asset_item_id IS NULL OR ai.id = p_asset_item_id)
        //         AND (action_queries_id IS NULL OR aicq.id = action_queries_id)
        //         AND (p_user_id IS NULL OR u.id = p_user_id)
        //         AND (p_user_id IS NULL OR u.tenant_id = p_tenant_id);

        //         IF record_count = 0 THEN
        //             RETURN QUERY SELECT 
        //                 'FAILURE', 'No matching records found', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL;
        //             RETURN;
        //         END IF;

        //         RETURN QUERY
        //         SELECT
        //             'SUCCESS',
        //             'Query successful',
        //             aicq.id,
        //             a.name::TEXT AS asset_group_name,
        //             aicq.asset_item,
        //             ai.serial_number::TEXT,
        //             ai.warranty::TEXT,
        //             ai.warranty_exparing_at::TIMESTAMP,
        //             ai.insurance_number::TEXT,
        //             ai.insurance_exparing_at::TIMESTAMP,
        //             aicq.source::TEXT AS queries_type,
        //             CASE 
        //                 WHEN aicq.source = 'manufacturer' THEN aimrms.maintain_schedule_parameters
        //                 WHEN aicq.source = 'usage' THEN aiubms.maintain_schedule_parameters
        //                 WHEN aicq.source = 'asset_group_manufacturer' THEN amrms.maintain_schedule_parameters
        //                 WHEN aicq.source = 'asset_group_usage' THEN aubms.maintain_schedule_parameters
        //                 ELSE NULL
        //             END,
        //             CASE 
        //                 WHEN aicq.source = 'manufacturer' THEN amsp.name::TEXT
        //                 WHEN aicq.source = 'usage' THEN ausp.name::TEXT
        //                 WHEN aicq.source = 'asset_group_manufacturer' THEN agmsp.name::TEXT
        //                 WHEN aicq.source = 'asset_group_usage' THEN agusp.name::TEXT
        //                 ELSE NULL
        //             END,
        //             CASE
        //                 WHEN aicq.source = 'manufacturer' THEN 'asset_item_manufacturer_recommendation_maintain_schedules'
        //                 WHEN aicq.source = 'usage' THEN 'asset_item_usage_based_maintain_schedules'
        //                 WHEN aicq.source = 'asset_group_manufacturer' THEN 'asset_manufacturer_recommendation_maintain_schedules'
        //                 WHEN aicq.source = 'asset_group_usage' THEN 'asset_usage_based_maintain_schedules'
        //                 WHEN aicq.source = 'asset_item_critically_based_schedule_check' THEN 'asset_item_critically_based_maintain_schedules'
        //                 ELSE NULL
        //             END,
        //             NULL,
        //             NULL,
        //             NULL,
        //             CASE 
        //                 WHEN aicq.source = 'asset_item_critically_based_schedule_check' THEN aicbms.assessment_description
        //                 ELSE NULL
        //             END,
        //             CASE 
        //                 WHEN aicq.source = 'asset_item_critically_based_schedule_check' THEN aicbms.schedule
        //                 ELSE NULL
        //             END,
        //             CASE 
        //                 WHEN aicq.source = 'asset_item_critically_based_schedule_check' THEN aicbms.expected_results
        //                 ELSE NULL
        //             END,
        //             CASE 
        //                 WHEN aicq.source = 'asset_item_critically_based_schedule_check' THEN aicbms.comments
        //                 ELSE NULL
        //             END,
        //             aicq.created_at::TIMESTAMP,
        //             aicq.updated_at::TIMESTAMP
        //         FROM asset_item_action_queries aicq
        //         JOIN asset_items ai ON aicq.asset_item = ai.id
        //         JOIN assets a ON ai.asset_id = a.id
        //         JOIN users u ON ai.responsible_person = u.id

        //         LEFT JOIN asset_item_manufacturer_recommendation_maintain_schedules aimrms
        //             ON ai.id = aimrms.asset_item AND aicq.source = 'manufacturer'
        //         LEFT JOIN asset_maintain_schedule_parameters amsp ON aimrms.maintain_schedule_parameters = amsp.id

        //         LEFT JOIN asset_item_usage_based_maintain_schedules aiubms
        //             ON ai.id = aiubms.asset_item AND aicq.source = 'usage'
        //         LEFT JOIN asset_maintain_schedule_parameters ausp ON aiubms.maintain_schedule_parameters = ausp.id

        //         LEFT JOIN asset_manufacturer_recommendation_maintain_schedules amrms
        //             ON ai.asset_id = amrms.asset AND aicq.source = 'asset_group_manufacturer'
        //         LEFT JOIN asset_maintain_schedule_parameters agmsp ON amrms.maintain_schedule_parameters = agmsp.id

        //         LEFT JOIN asset_usage_based_maintain_schedules aubms
        //             ON ai.asset_id = aubms.asset AND aicq.source = 'asset_group_usage'
        //         LEFT JOIN asset_maintain_schedule_parameters agusp ON aubms.maintain_schedule_parameters = agusp.id

        //         LEFT JOIN asset_item_critically_based_maintain_schedules aicbms
        //             ON ai.id = aicbms.asset_item AND aicq.source = 'asset_item_critically_based_schedule_check'

        //         WHERE (p_asset_item_id IS NULL OR ai.id = p_asset_item_id)
        //         AND (action_queries_id IS NULL OR aicq.id = action_queries_id)
        //         AND (p_user_id IS NULL OR u.id = p_user_id)
        //         AND (p_user_id IS NULL OR u.tenant_id = p_tenant_id);
        //     END;
        //     $$;
        // SQL);

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_asset_item_action_queries_details(
                p_user_id BIGINT DEFAULT NULL,
                p_tenant_id BIGINT DEFAULT NULL,
                p_asset_item_id BIGINT DEFAULT NULL,
                action_queries_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                asset_group_name TEXT,
                asset_item BIGINT,
                asset_item_serial_number TEXT,
                responsible_personal BIGINT,
                responsible_personal_name TEXT,
                responsible_personal_email TEXT,
                warranty TEXT,
                warranty_exparing_at TIMESTAMP,
                insurance_number TEXT,
                insurance_exparing_at TIMESTAMP,
                queries_type TEXT,
                maintain_schedule_parameters BIGINT,
                maintain_schedule_parameters_name TEXT,
                maintain_schedule_table TEXT,
                limit_or_value TEXT,
                operator TEXT,
                reading_parameters TEXT,
                assessment_description TEXT,
                schedule TEXT,
                expected_results TEXT,
                comments TEXT,
                maintenance_task_type_name TEXT,
                created_at TIMESTAMP,
                updated_at TIMESTAMP
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                record_count INT;
            BEGIN
                RETURN QUERY
                SELECT
                    'SUCCESS',
                    'Query successful',
                    aicq.id,
                    a.name::TEXT,
                    aicq.asset_item,
                    ai.serial_number::TEXT,
                    ai.responsible_person,
                    u.name::TEXT AS responsible_personal_name,
                    u.email::TEXT AS responsible_personal_email,
                    ai.warranty::TEXT,
                    ai.warranty_exparing_at::timestamp,
                    ai.insurance_number::TEXT,
                    ai.insurance_exparing_at::timestamp,
                    aicq.source::TEXT,
            
                    -- maintain_schedule_parameters
                    CASE 
                        WHEN aicq.source = 'manufacturer' THEN aimrms.maintain_schedule_parameters
                        WHEN aicq.source = 'usage' THEN aiubms.maintain_schedule_parameters
                        WHEN aicq.source = 'asset_group_manufacturer' THEN amrms.maintain_schedule_parameters
                        WHEN aicq.source = 'asset_group_usage' THEN aubms.maintain_schedule_parameters
                        ELSE NULL
                    END,
            
                    -- maintain_schedule_parameters_name
                    CASE 
                        WHEN aicq.source = 'manufacturer' THEN amsp.name::TEXT
                        WHEN aicq.source = 'usage' THEN ausp.name::TEXT
                        WHEN aicq.source = 'asset_group_manufacturer' THEN agmsp.name::TEXT
                        WHEN aicq.source = 'asset_group_usage' THEN agusp.name::TEXT
                        WHEN aicq.source = 'asset_item_critically_based_schedule_check' THEN aicbms.schedule::TEXT
                        WHEN aicq.source = 'asset_critically_based_schedule_check' THEN acbms.schedule::TEXT
                        ELSE NULL
                    END,
            
                    -- maintain_schedule_table
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
                    END,
            
                    -- limit_or_value / expected_results
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
                    END::TEXT,
            
                    -- operator
                    CASE 
                        WHEN aicq.source = 'manufacturer' THEN aimrms.operator
                        WHEN aicq.source = 'usage' THEN aiubms.operator
                        WHEN aicq.source = 'asset_group_manufacturer' THEN amrms.operator
                        WHEN aicq.source = 'asset_group_usage' THEN aubms.operator
                        ELSE NULL
                    END::TEXT,
            
                    -- reading_parameters / comments
                    CASE 
                        WHEN aicq.source = 'manufacturer' THEN aimrms.reading_parameters
                        WHEN aicq.source = 'usage' THEN aiubms.reading_parameters
                        WHEN aicq.source = 'asset_group_manufacturer' THEN amrms.reading_parameters
                        WHEN aicq.source = 'asset_group_usage' THEN aubms.reading_parameters
                        WHEN aicq.source = 'asset_item_critically_based_schedule_check' THEN aicbms.comments
                        WHEN aicq.source = 'asset_critically_based_schedule_check' THEN acbms.comments
                        ELSE NULL
                    END::TEXT,
            
                    -- assessment_description
                    CASE 
                        WHEN aicq.source = 'asset_item_critically_based_schedule_check' THEN aicbms.assessment_description
                        WHEN aicq.source = 'asset_critically_based_schedule_check' THEN acbms.assessment_description
                        WHEN aicq.source = 'asset_item_maintenance_tasks_schedule_check' THEN aimt.maintenance_tasks_description
                        WHEN aicq.source = 'asset_maintenance_tasks_schedule_check' THEN mt.maintenance_tasks_description
                        ELSE NULL
                    END::TEXT,
            
                    -- schedule
                    CASE 
                        WHEN aicq.source = 'asset_item_critically_based_schedule_check' THEN aicbms.schedule
                        WHEN aicq.source = 'asset_critically_based_schedule_check' THEN acbms.schedule
                        WHEN aicq.source = 'asset_item_maintenance_tasks_schedule_check' THEN aimt.schedule
                        WHEN aicq.source = 'asset_maintenance_tasks_schedule_check' THEN mt.schedule
                        ELSE NULL
                    END::TEXT,
            
                    -- expected_results
                    CASE 
                        WHEN aicq.source = 'asset_item_critically_based_schedule_check' THEN aicbms.expected_results
                        WHEN aicq.source = 'asset_critically_based_schedule_check' THEN acbms.expected_results
                        WHEN aicq.source = 'asset_item_maintenance_tasks_schedule_check' THEN aimt.expected_results
                        WHEN aicq.source = 'asset_maintenance_tasks_schedule_check' THEN mt.expected_results
                        ELSE NULL
                    END::TEXT,
            
                    -- comments
                    CASE 
                        WHEN aicq.source = 'asset_item_critically_based_schedule_check' THEN aicbms.comments
                        WHEN aicq.source = 'asset_critically_based_schedule_check' THEN acbms.comments
                        ELSE NULL
                    END::TEXT,

                    -- maintenance_task_type_name
                    CASE 
                        WHEN aicq.source = 'asset_item_maintenance_tasks_schedule_check' THEN mtt.name
                        WHEN aicq.source = 'asset_maintenance_tasks_schedule_check' THEN mtt2.name
                        ELSE NULL
                    END::TEXT,
            
                    aicq.created_at,
                    aicq.updated_at
            
                FROM asset_item_action_queries aicq
                JOIN asset_items ai ON aicq.asset_item = ai.id
                JOIN assets a ON ai.asset_id = a.id
                JOIN users u ON ai.responsible_person = u.id
            
                LEFT JOIN asset_item_manufacturer_recommendation_maintain_schedules aimrms
                    ON ai.id = aimrms.asset_item AND aicq.source = 'manufacturer'
                LEFT JOIN asset_maintain_schedule_parameters amsp ON aimrms.maintain_schedule_parameters = amsp.id
            
                LEFT JOIN asset_item_usage_based_maintain_schedules aiubms
                    ON ai.id = aiubms.asset_item AND aicq.source = 'usage'
                LEFT JOIN asset_maintain_schedule_parameters ausp ON aiubms.maintain_schedule_parameters = ausp.id
            
                LEFT JOIN asset_manufacturer_recommendation_maintain_schedules amrms
                    ON ai.asset_id = amrms.asset AND aicq.source = 'asset_group_manufacturer'
                LEFT JOIN asset_maintain_schedule_parameters agmsp ON amrms.maintain_schedule_parameters = agmsp.id
            
                LEFT JOIN asset_usage_based_maintain_schedules aubms
                    ON ai.asset_id = aubms.asset AND aicq.source = 'asset_group_usage'
                LEFT JOIN asset_maintain_schedule_parameters agusp ON aubms.maintain_schedule_parameters = agusp.id
            
                LEFT JOIN asset_item_critically_based_maintain_schedules aicbms
                    ON aicq.recommendation_id = aicbms.id AND aicq.source = 'asset_item_critically_based_schedule_check'
            
                LEFT JOIN asset_critically_based_maintain_schedules acbms
                    ON aicq.recommendation_id = acbms.id AND aicq.source = 'asset_critically_based_schedule_check'

                LEFT JOIN asset_item_maintenance_tasks aimt ON aicq.recommendation_id = aimt.id AND aicq.source = 'asset_item_maintenance_tasks_schedule_check'
                LEFT JOIN maintenance_tasks_type mtt ON aimt.task_type = mtt.id

                LEFT JOIN maintenance_tasks mt ON aicq.recommendation_id = mt.id AND aicq.source = 'asset_maintenance_tasks_schedule_check'
                LEFT JOIN maintenance_tasks_type mtt2 ON mt.task_type = mtt2.id
            
                WHERE (p_asset_item_id IS NULL OR ai.id = p_asset_item_id)
                AND (action_queries_id IS NULL OR aicq.id = action_queries_id)
                AND (p_user_id IS NULL OR u.id = p_user_id)
                AND (p_tenant_id IS NULL OR u.tenant_id = p_tenant_id)
                AND (p_tenant_id IS NULL OR aicq.tenant_id = p_tenant_id)
                AND aicq.is_get_action = FALSE;
            END;
            $$;
        SQL);
        
    
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_item_action_queries_details');
    }
};