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
            CREATE OR REPLACE FUNCTION get_all_workflow_condition_query_tag(
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_workflow_condition_query_tag_id INT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                name TEXT,
                value TEXT,
                query TEXT,
                type TEXT,
                params JSONB
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- If both parameters are NULL, return all records
                IF p_tenant_id IS NULL AND p_workflow_condition_query_tag_id IS NULL THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT AS status,
                        'All query tag fetched successfully'::TEXT AS message,
                        wcqt.id,
                        wcqt.name::TEXT,
                        wcqt.value::TEXT,
                        wcqt.query::TEXT,
                        wcqt.type::TEXT,
                        wcqt.params::JSONB
                    FROM workflow_condition_query_tag wcqt
                    WHERE wcqt.deleted_at IS NULL
                    AND wcqt.isactive = TRUE;
                    RETURN;
                END IF;

                -- Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::TEXT AS value,
                        NULL::TEXT AS query,
                        NULL::TEXT AS type,
                        NULL::JSONB AS params;
                    RETURN;
                END IF;

                -- Validate availability type ID (optional)
                IF p_workflow_condition_query_tag_id IS NOT NULL AND p_workflow_condition_query_tag_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid p workflow condition query_tag id ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::TEXT AS value,
                        NULL::TEXT AS query,
                        NULL::TEXT AS type,
                        NULL::JSONB AS params;
                    RETURN;
                END IF;

                -- Return matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Asset query tag fetched successfully'::TEXT AS message,
                    wcqt.id,
                    wcqt.name::TEXT,
                    wcqt.value::TEXT,
                    wcqt.query::TEXT,
                    wcqt.type::TEXT,
                    wcqt.params::JSONB
                FROM
                    workflow_condition_query_tag wcqt
                WHERE (p_workflow_condition_query_tag_id IS NULL OR wcqt.id = p_workflow_condition_query_tag_id)
                AND wcqt.tenant_id = p_tenant_id
                AND wcqt.deleted_at IS NULL
                AND wcqt.isactive = TRUE;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_all_workflow_condition_query_tag');
    }
};