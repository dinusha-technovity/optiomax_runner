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
            CREATE OR REPLACE FUNCTION insert_or_update_maintenance_task(
                IN p_asset BIGINT,
                IN p_maintenance_tasks_description TEXT,
                IN p_schedule TEXT,
                IN p_expected_results TEXT,
                IN p_task_type BIGINT,
                IN p_tenant_id BIGINT,
                IN p_current_time TIMESTAMP WITH TIME ZONE,
                IN p_task_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                old_data JSONB,
                new_data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                p_inserted_or_updated_task_id BIGINT;
                old_record JSONB;
                new_record JSONB;
            BEGIN
                -- Validate inputs
                IF p_asset IS NULL OR p_asset = 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Asset ID cannot be null or zero', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;
                
                IF p_schedule IS NULL OR LENGTH(TRIM(p_schedule)) = 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Schedule cannot be null or empty', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;
                
                IF p_expected_results IS NULL OR LENGTH(TRIM(p_expected_results)) = 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Expected results cannot be null or empty', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;
                
                IF p_task_type IS NULL OR p_task_type = 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Task type cannot be null or zero', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;
                
                IF p_task_id IS NULL OR p_task_id = 0 THEN
                    -- Insert new record
                    INSERT INTO maintenance_tasks (
                        asset, 
                        maintenance_tasks_description, 
                        schedule, 
                        expected_results, 
                        task_type, 
                        tenant_id, 
                        created_at, 
                        updated_at
                    )
                    VALUES (
                        p_asset, 
                        p_maintenance_tasks_description, 
                        p_schedule, 
                        p_expected_results, 
                        p_task_type, 
                        p_tenant_id, 
                        p_current_time, 
                        p_current_time
                    )
                    RETURNING id, to_jsonb(maintenance_tasks.*) INTO p_inserted_or_updated_task_id, new_record;
                    
                    RETURN QUERY SELECT 'SUCCESS', 'Record inserted successfully', NULL::JSONB, new_record;
                ELSE
                    -- Fetch old data before updating
                    SELECT to_jsonb(maintenance_tasks.*) INTO old_record
                    FROM maintenance_tasks
                    WHERE id = p_task_id;
                    
                    -- Update existing record
                    UPDATE maintenance_tasks
                    SET 
                        asset = p_asset,
                        maintenance_tasks_description = p_maintenance_tasks_description,
                        schedule = p_schedule,
                        expected_results = p_expected_results,
                        task_type = p_task_type,
                        tenant_id = p_tenant_id,
                        updated_at = p_current_time
                    WHERE id = p_task_id;
                    
                    -- Fetch new data after updating
                    SELECT to_jsonb(maintenance_tasks.*) INTO new_record
                    FROM maintenance_tasks
                    WHERE id = p_task_id;
                    
                    RETURN QUERY SELECT 'SUCCESS', 'Record updated successfully', old_record, new_record;
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
        DB::unprepared('DROP FUNCTION IF EXISTS insert_or_update_maintenance_task');
    }
};
