<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION workflow_publish(
                p_workflow_id BIGINT,
                p_tenant_id BIGINT,
                p_publish_at TIMESTAMP WITH TIME ZONE
            )
            RETURNS TABLE (
                status TEXT, 
                message TEXT,
                workflow_data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                rows_updated INT;        -- Variable to capture affected rows
                workflow_data JSONB;      -- Variable to store data before the update
            BEGIN
                -- Fetch data before the update
                SELECT row_to_json(w) INTO workflow_data
                FROM workflows w
                WHERE id = p_workflow_id;

                -- Check if the workflow exists
                IF workflow_data IS NULL THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No Workflow published. Workflow not found.'::TEXT AS message,
                        NULL::JSONB AS workflow_data;
                    RETURN; -- Exit early
                END IF;

                -- Publish the workflow
                UPDATE workflows
                SET 
                    is_published = true,
                    updated_at = p_publish_at -- Use published_at instead of deleted_at
                WHERE id = p_workflow_id;

                -- Capture the number of rows updated
                GET DIAGNOSTICS rows_updated = ROW_COUNT;

                -- Check if the update was successful
                IF rows_updated > 0 THEN
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status, 
                        'Workflow published successfully'::TEXT AS message,
                        workflow_data;
                ELSE
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No workflow published. Workflow not found.'::TEXT AS message,
                        NULL::JSONB AS workflow_data;
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
        DB::unprepared('DROP FUNCTION IF EXISTS workflow_publish');
    }
};
