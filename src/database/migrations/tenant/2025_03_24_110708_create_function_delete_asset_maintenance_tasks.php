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
            CREATE OR REPLACE FUNCTION delete_maintenance_task(
                p_task_id BIGINT,
                p_tenant_id BIGINT,
                p_deleted_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
            )
            RETURNS TABLE (
                status TEXT, 
                message TEXT,
                deleted_data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                deleted_data JSONB;
                task_exists BOOLEAN;
                affected_rows INT;
            BEGIN
                -- Validate p_task_id
                IF p_task_id IS NULL OR p_task_id = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Task ID cannot be null or zero'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                    RETURN;
                END IF;

                -- Validate p_tenant_id
                IF p_tenant_id IS NULL OR p_tenant_id = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Tenant ID cannot be null or zero'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                    RETURN;
                END IF;

                -- Check if the task exists and is not already deleted
                SELECT EXISTS(
                    SELECT 1 FROM maintenance_tasks
                    WHERE id = p_task_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL
                ) INTO task_exists;

                IF NOT task_exists THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Task with the given ID does not exist or is already deleted'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                    RETURN;
                END IF;

                -- Fetch task data before deletion
                SELECT jsonb_build_object(
                    'id', id,
                    'asset', asset,
                    'maintenance_tasks_description', maintenance_tasks_description,
                    'schedule', schedule,
                    'expected_results', expected_results,
                    'task_type', task_type,
                    'tenant_id', tenant_id,
                    'created_at', created_at,
                    'updated_at', updated_at
                ) INTO deleted_data
                FROM maintenance_tasks
                WHERE id = p_task_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

                -- Soft delete the task
                UPDATE maintenance_tasks
                SET 
                    deleted_at = p_deleted_at, 
                    isactive = FALSE
                WHERE id = p_task_id;

                -- Check if any row was affected
                GET DIAGNOSTICS affected_rows = ROW_COUNT;
                IF affected_rows = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No task deleted. It may not exist or was already deleted'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                    RETURN;
                END IF;

                -- Return success response
                RETURN QUERY SELECT 
                    'SUCCESS'::TEXT AS status, 
                    'Task deleted successfully'::TEXT AS message,
                    deleted_data;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS delete_maintenance_task');
    }
};
