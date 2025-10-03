<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // DB::unprepared(<<<SQL
        //                     CREATE OR REPLACE PROCEDURE STORE_PROCEDURE_RETRIEVE_WORKFLOW(
        //                         IN _tenant_id BIGINT, 
        //                         IN p_workflow_id INT DEFAULT NULL
        //                     )
        //                     LANGUAGE plpgsql
        //                     AS $$
        //                     BEGIN
        //                         DROP TABLE IF EXISTS workflow_from_store_procedure;
                            
        //                         CREATE TEMP TABLE workflow_from_store_procedure AS
        //                         SELECT
        //                             w.id,
        //                             w.workflow_request_type_id,
        //                             wrt.request_type,
        //                             w.workflow_name,
        //                             w.workflow_description,
        //                             w.workflow_status,
        //                             w.created_at,
        //                             w.updated_at
        //                         FROM
        //                             workflows w
        //                         INNER JOIN
        //                             workflow_request_types wrt ON w.workflow_request_type_id = wrt.id
        //                         WHERE
        //                             (w.id = p_workflow_id OR p_workflow_id IS NULL OR p_workflow_id = 0)
        //                             AND w.tenant_id = _tenant_id
        //                             AND w.deleted_at IS NULL
        //                             AND w.isactive = TRUE; 
        //                     END;
        //                     $$;
        //                     SQL
        //                 );

        // DB::unprepared(<<<SQL
        //     CREATE OR REPLACE FUNCTION get_workflows(
        //         _tenant_id BIGINT,
        //         p_workflow_id INT DEFAULT NULL
        //     )
        //     RETURNS TABLE (
        //         status TEXT,
        //         message TEXT,
        //         id BIGINT,
        //         workflow_request_type_id BIGINT,
        //         request_type TEXT,
        //         workflow_name TEXT,
        //         workflow_description TEXT,
        //         workflow_status BOOLEAN,
        //         is_published BOOLEAN,
        //         created_at TIMESTAMP,
        //         updated_at TIMESTAMP
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     DECLARE
        //         workflow_count INT;
        //     BEGIN
        //         -- Validate tenant ID
        //         IF _tenant_id IS NULL OR _tenant_id <= 0 THEN
        //             RETURN QUERY SELECT 
        //                 'FAILURE'::TEXT AS status,
        //                 'Invalid tenant ID provided'::TEXT AS message,
        //                 NULL::BIGINT AS id,
        //                 NULL::BIGINT AS workflow_request_type_id,
        //                 NULL::TEXT AS request_type,
        //                 NULL::TEXT AS workflow_name,
        //                 NULL::TEXT AS workflow_description,
        //                 NULL::BOOLEAN AS workflow_status,
        //                 NULL::BOOLEAN AS is_published,
        //                 NULL::TIMESTAMP AS created_at,
        //                 NULL::TIMESTAMP AS updated_at;
        //             RETURN;
        //         END IF;
        
        //         -- Validate workflow ID (optional)
        //         IF p_workflow_id IS NOT NULL AND p_workflow_id < 0 THEN
        //             RETURN QUERY SELECT 
        //                 'FAILURE'::TEXT AS status,
        //                 'Invalid workflow ID provided'::TEXT AS message,
        //                 NULL::BIGINT AS id,
        //                 NULL::BIGINT AS workflow_request_type_id,
        //                 NULL::TEXT AS request_type,
        //                 NULL::TEXT AS workflow_name,
        //                 NULL::TEXT AS workflow_description,
        //                 NULL::BOOLEAN AS workflow_status,
        //                 NULL::BOOLEAN AS is_published,
        //                 NULL::TIMESTAMP AS created_at,
        //                 NULL::TIMESTAMP AS updated_at;
        //             RETURN;
        //         END IF;
        
        //         -- Check if any matching records exist
        //         SELECT COUNT(*) INTO workflow_count
        //         FROM workflows w
        //         WHERE (p_workflow_id IS NULL OR p_workflow_id = 0 OR w.id = p_workflow_id)
        //         AND w.tenant_id = _tenant_id
        //         AND w.deleted_at IS NULL
        //         AND w.workflow_status = TRUE;
        
        //         IF workflow_count = 0 THEN
        //             RETURN QUERY SELECT 
        //                 'FAILURE'::TEXT AS status,
        //                 'No matching workflows found'::TEXT AS message,
        //                 NULL::BIGINT AS id,
        //                 NULL::BIGINT AS workflow_request_type_id,
        //                 NULL::TEXT AS request_type,
        //                 NULL::TEXT AS workflow_name,
        //                 NULL::TEXT AS workflow_description,
        //                 NULL::BOOLEAN AS workflow_status,
        //                 NULL::BOOLEAN AS is_published,
        //                 NULL::TIMESTAMP AS created_at,
        //                 NULL::TIMESTAMP AS updated_at;
        //             RETURN;
        //         END IF;
        
        //         -- Return the matching records
        //         RETURN QUERY
        //         SELECT
        //             'SUCCESS'::TEXT AS status,
        //             'Workflows fetched successfully'::TEXT AS message,
        //             w.id,
        //             w.workflow_request_type_id::BIGINT,
        //             wrt.request_type::TEXT,
        //             w.workflow_name::TEXT,
        //             w.workflow_description,
        //             w.workflow_status::BOOLEAN,
        //             w.is_published::BOOLEAN,
        //             w.created_at,
        //             w.updated_at
        //         FROM
        //             workflows w
        //         INNER JOIN
        //             workflow_request_types wrt ON w.workflow_request_type_id = wrt.id
        //         WHERE
        //             (p_workflow_id IS NULL OR p_workflow_id = 0 OR w.id = p_workflow_id)
        //             AND w.tenant_id = _tenant_id
        //             AND w.deleted_at IS NULL;
        //     END;
        //     $$;
        // SQL);

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_workflows(
                _tenant_id BIGINT,
                p_workflow_id INT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                workflow_request_type_id BIGINT,
                request_type TEXT,
                workflow_name TEXT,
                workflow_description TEXT,
                workflow_status BOOLEAN,
                is_published BOOLEAN,
                created_at TIMESTAMP,
                updated_at TIMESTAMP,
                request_available BOOLEAN
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                workflow_count INT;
            BEGIN
                -- Validate tenant ID
                IF _tenant_id IS NULL OR _tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::BIGINT AS workflow_request_type_id,
                        NULL::TEXT AS request_type,
                        NULL::TEXT AS workflow_name,
                        NULL::TEXT AS workflow_description,
                        NULL::BOOLEAN AS workflow_status,
                        NULL::BOOLEAN AS is_published,
                        NULL::TIMESTAMP AS created_at,
                        NULL::TIMESTAMP AS updated_at,
                        NULL::BOOLEAN AS request_available;
                    RETURN;
                END IF;

                -- Validate workflow ID (optional)
                IF p_workflow_id IS NOT NULL AND p_workflow_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid workflow ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::BIGINT AS workflow_request_type_id,
                        NULL::TEXT AS request_type,
                        NULL::TEXT AS workflow_name,
                        NULL::TEXT AS workflow_description,
                        NULL::BOOLEAN AS workflow_status,
                        NULL::BOOLEAN AS is_published,
                        NULL::TIMESTAMP AS created_at,
                        NULL::TIMESTAMP AS updated_at,
                        NULL::BOOLEAN AS request_available;
                    RETURN;
                END IF;

                -- Check if any matching records exist
                SELECT COUNT(*) INTO workflow_count
                FROM workflows w
                WHERE (p_workflow_id IS NULL OR p_workflow_id = 0 OR w.id = p_workflow_id)
                AND w.tenant_id = _tenant_id
                AND w.deleted_at IS NULL
                AND w.workflow_status = TRUE;

                IF workflow_count = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'No matching workflows found'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::BIGINT AS workflow_request_type_id,
                        NULL::TEXT AS request_type,
                        NULL::TEXT AS workflow_name,
                        NULL::TEXT AS workflow_description,
                        NULL::BOOLEAN AS workflow_status,
                        NULL::BOOLEAN AS is_published,
                        NULL::TIMESTAMP AS created_at,
                        NULL::TIMESTAMP AS updated_at,
                        NULL::BOOLEAN AS request_available;
                    RETURN;
                END IF;

                -- Return the matching records with request availability check
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Workflows fetched successfully'::TEXT AS message,
                    w.id,
                    w.workflow_request_type_id::BIGINT,
                    wrt.request_type::TEXT,
                    w.workflow_name::TEXT,
                    w.workflow_description,
                    w.workflow_status::BOOLEAN,
                    w.is_published::BOOLEAN,
                    w.created_at,
                    w.updated_at,
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 
                            FROM workflow_request_queues wrq 
                            WHERE wrq.workflow_id = w.id
                            -- AND wrq.tenant_id = _tenant_id
                            AND wrq.deleted_at IS NULL
                            AND wrq.workflow_request_status NOT IN ('APPROVED', 'REJECT')
                        ) THEN TRUE
                        ELSE FALSE
                    END AS request_available
                FROM
                    workflows w
                INNER JOIN
                    workflow_request_types wrt ON w.workflow_request_type_id = wrt.id
                WHERE
                    (p_workflow_id IS NULL OR p_workflow_id = 0 OR w.id = p_workflow_id)
                    AND w.tenant_id = _tenant_id
                    AND w.deleted_at IS NULL;
            END;
            $$;
        SQL);

    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS retrieve_workflow');
    }
};