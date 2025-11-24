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
                    WHERE proname = 'get_workflows'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
        
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
                request_available BOOLEAN,
                approved_request_count BIGINT,
                pending_request_count BIGINT
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
                        NULL::BOOLEAN AS request_available,
                        NULL::BIGINT AS approved_request_count,
                        NULL::BIGINT AS pending_request_count;
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
                        NULL::BOOLEAN AS request_available,
                        NULL::BIGINT AS approved_request_count,
                        NULL::BIGINT AS pending_request_count;
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
                        NULL::BOOLEAN AS request_available,
                        NULL::BIGINT AS approved_request_count,
                        NULL::BIGINT AS pending_request_count;
                    RETURN;
                END IF;

                -- Return the matching records with request availability check and counts
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
                            AND wrq.workflow_request_status NOT IN ('APPROVED', 'REJECT', 'CANCELLED')
                        ) THEN TRUE
                        ELSE FALSE
                    END AS request_available,
                    COALESCE((
                        SELECT COUNT(*)::BIGINT
                        FROM workflow_request_queues wrq
                        WHERE wrq.workflow_id = w.id
                        AND wrq.deleted_at IS NULL
                        AND wrq.workflow_request_status = 'APPROVED'
                    ), 0) AS approved_request_count,
                    COALESCE((
                        SELECT COUNT(*)::BIGINT
                        FROM workflow_request_queues wrq
                        WHERE wrq.workflow_id = w.id
                        AND wrq.deleted_at IS NULL
                        AND wrq.workflow_request_status = 'PENDING'
                    ), 0) AS pending_request_count
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