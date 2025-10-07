<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // DB::unprepared(
        //     "CREATE OR REPLACE PROCEDURE STORE_PROCEDURE_RETRIEVE_WORKFLOW_DETAILS(
        //         IN p_workflow_id INT DEFAULT NULL
        //     )
        //     AS $$
        //     BEGIN
        //         DROP TABLE IF EXISTS workflow_details_from_store_procedure;
            
        //         CREATE TEMP TABLE workflow_details_from_store_procedure AS
        //         SELECT
        //             w.id AS workflow_id,
        //             wd.id AS workflow_detail_id,
        //             wd.workflow_detail_parent_id AS workflow_detail_parent_id,
        //             wd.workflow_detail_type_id,
        //             wd.workflow_detail_behavior_type_id,
        //             wd.workflow_detail_order,
        //             wd.workflow_detail_level,
        //             wd.workflow_detail_data_object::jsonb,
        //             wrt.request_type AS workflow_request_type,
        //             wt.workflow_type AS workflow_detail_type,
        //             wbt.workflow_behavior_type AS workflow_detail_behavior_type
        //         FROM
        //             workflows w
        //         INNER JOIN
        //             workflow_details wd ON w.id = wd.workflow_id
        //         INNER JOIN
        //             workflow_request_types wrt ON w.workflow_request_type_id = wrt.id
        //         INNER JOIN
        //             workflow_types wt ON wd.workflow_detail_type_id = wt.id
        //         INNER JOIN
        //             workflow_behavior_types wbt ON wd.workflow_detail_behavior_type_id = wbt.id
        //         WHERE
        //             (w.id = p_workflow_id OR p_workflow_id IS NULL OR p_workflow_id = 0)
        //             AND w.deleted_at IS NULL
        //             AND w.isactive = TRUE
        //         GROUP BY
        //             w.id, wd.id, wrt.request_type, wt.workflow_type, wbt.workflow_behavior_type;
        //     END;
        //     $$ LANGUAGE plpgsql;"
        // ); 
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_workflow_details(
                p_workflow_id BIGINT DEFAULT NULL,
                p_tenant_id BIGINT DEFAULT NULL,
                p_workflow_node_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                workflow_id BIGINT,
                workflow_detail_id BIGINT,
                workflow_detail_parent_id BIGINT,
                workflow_detail_type_id BIGINT,
                workflow_detail_behavior_type_id BIGINT,
                workflow_detail_order BIGINT,
                workflow_detail_level BIGINT,
                workflow_detail_data_object JSONB,
                workflow_request_type TEXT,
                workflow_detail_type TEXT,
                workflow_detail_behavior_type TEXT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                workflow_count INT;
            BEGIN
                -- Validate tenant ID
                IF p_tenant_id IS NOT NULL AND p_tenant_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT AS workflow_id,
                        NULL::BIGINT AS workflow_detail_id,
                        NULL::BIGINT AS workflow_detail_parent_id,
                        NULL::BIGINT AS workflow_detail_type_id,
                        NULL::BIGINT AS workflow_detail_behavior_type_id,
                        NULL::BIGINT AS workflow_detail_order,
                        NULL::BIGINT AS workflow_detail_level,
                        NULL::JSONB AS workflow_detail_data_object,
                        NULL::TEXT AS workflow_request_type,
                        NULL::TEXT AS workflow_detail_type,
                        NULL::TEXT AS workflow_detail_behavior_type;
                    RETURN;
                END IF;
        
                -- Validate workflow ID
                IF p_workflow_id IS NOT NULL AND p_workflow_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid workflow ID provided'::TEXT AS message,
                        NULL::BIGINT AS workflow_id,
                        NULL::BIGINT AS workflow_detail_id,
                        NULL::BIGINT AS workflow_detail_parent_id,
                        NULL::BIGINT AS workflow_detail_type_id,
                        NULL::BIGINT AS workflow_detail_behavior_type_id,
                        NULL::BIGINT AS workflow_detail_order,
                        NULL::BIGINT AS workflow_detail_level,
                        NULL::JSONB AS workflow_detail_data_object,
                        NULL::TEXT AS workflow_request_type,
                        NULL::TEXT AS workflow_detail_type,
                        NULL::TEXT AS workflow_detail_behavior_type;
                    RETURN;
                END IF;

                -- Validate workflow ID
                IF p_workflow_node_id IS NOT NULL AND p_workflow_node_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid workflow Node ID provided'::TEXT AS message,
                        NULL::BIGINT AS workflow_id,
                        NULL::BIGINT AS workflow_detail_id,
                        NULL::BIGINT AS workflow_detail_parent_id,
                        NULL::BIGINT AS workflow_detail_type_id,
                        NULL::BIGINT AS workflow_detail_behavior_type_id,
                        NULL::BIGINT AS workflow_detail_order,
                        NULL::BIGINT AS workflow_detail_level,
                        NULL::JSONB AS workflow_detail_data_object,
                        NULL::TEXT AS workflow_request_type,
                        NULL::TEXT AS workflow_detail_type,
                        NULL::TEXT AS workflow_detail_behavior_type;
                    RETURN;
                END IF;
        
                -- Check if any matching records exist
                SELECT COUNT(*) INTO workflow_count
                FROM workflows w
                WHERE (w.id = p_workflow_id OR p_workflow_id IS NULL OR p_workflow_id = 0)
                AND w.deleted_at IS NULL
                AND w.tenant_id = p_tenant_id;
        
                IF workflow_count = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'No matching workflows found'::TEXT AS message,
                        NULL::BIGINT AS workflow_id,
                        NULL::BIGINT AS workflow_detail_id,
                        NULL::BIGINT AS workflow_detail_parent_id,
                        NULL::BIGINT AS workflow_detail_type_id,
                        NULL::BIGINT AS workflow_detail_behavior_type_id,
                        NULL::BIGINT AS workflow_detail_order,
                        NULL::BIGINT AS workflow_detail_level,
                        NULL::JSONB AS workflow_detail_data_object,
                        NULL::TEXT AS workflow_request_type,
                        NULL::TEXT AS workflow_detail_type,
                        NULL::TEXT AS workflow_detail_behavior_type;
                    RETURN;
                END IF;
        
                -- Return the matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Workflow details fetched successfully'::TEXT AS message,
                    w.id AS workflow_id,
                    wd.id AS workflow_detail_id,
                    wd.workflow_detail_parent_id,
                    wd.workflow_detail_type_id,
                    wd.workflow_detail_behavior_type_id,
                    wd.workflow_detail_order::BIGINT,
                    wd.workflow_detail_level::BIGINT,
                    wd.workflow_detail_data_object::jsonb,
                    wrt.request_type::TEXT,
                    wt.workflow_type::TEXT,
                    wbt.workflow_behavior_type::TEXT
                FROM
                    workflows w
                INNER JOIN
                    workflow_details wd ON w.id = wd.workflow_id
                INNER JOIN
                    workflow_request_types wrt ON w.workflow_request_type_id = wrt.id
                INNER JOIN
                    workflow_types wt ON wd.workflow_detail_type_id = wt.id
                INNER JOIN
                    workflow_behavior_types wbt ON wd.workflow_detail_behavior_type_id = wbt.id
                WHERE
                    (w.id = p_workflow_id OR p_workflow_id IS NULL OR p_workflow_id = 0)
                    AND (wd.id = p_workflow_node_id OR p_workflow_node_id IS NULL OR p_workflow_node_id = 0)
                    AND w.tenant_id = p_tenant_id
                    AND w.deleted_at IS NULL
                    AND wd.deleted_at IS NULL
                GROUP BY
                    w.id, wd.id, wrt.request_type, wt.workflow_type, wbt.workflow_behavior_type;
            END;
            $$;
        SQL);

    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_workflow_details');
    }
};