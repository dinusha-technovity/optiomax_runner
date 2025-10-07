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
            CREATE OR REPLACE FUNCTION get_workflow_request_types_by_action(
                p_tenant_id BIGINT DEFAULT NULL,
                p_action TEXT DEFAULT 'all'
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                request_type TEXT,
                description TEXT
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Tenant check
                IF p_tenant_id IS NOT NULL AND p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE', 'Invalid tenant ID provided',
                        NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL;
                    RETURN;
                END IF;
                
                IF p_action = 'unused' THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS' AS status,
                        'Unreferenced workflow request types retrieved successfully' AS message,
                        wrt.id,
                        wrt.request_type::TEXT,
                        wrt.description
                    FROM workflow_request_types wrt
                    WHERE NOT EXISTS (
                        SELECT 1 FROM workflows w
                        WHERE w.workflow_request_type_id = wrt.id
                        AND w.deleted_at IS NULL
                    )
                    AND (p_tenant_id IS NULL OR wrt.tenant_id = p_tenant_id);

                ELSE
                    RETURN QUERY
                    SELECT
                        'SUCCESS' AS status,
                        'All workflow request types retrieved successfully' AS message,
                        wrt.id,
                        wrt.request_type::TEXT,
                        wrt.description
                    FROM workflow_request_types wrt
                    WHERE (p_tenant_id IS NULL OR wrt.tenant_id = p_tenant_id);
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
        DB::unprepared("DROP FUNCTION IF EXISTS get_workflow_request_types_by_action(
            BIGINT,
            TEXT
        );");
    }
};