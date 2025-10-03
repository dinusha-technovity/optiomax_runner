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
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'insert_or_update_workflow'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION insert_or_update_workflow(
                IN p_workflow_request_type_id BIGINT,
                IN p_workflow_name TEXT,
                IN p_workflow_description TEXT,
                IN p_tenant_id BIGINT,
                IN p_current_time TIMESTAMP WITH TIME ZONE,
                IN p_workflow_id BIGINT DEFAULT NULL,
                IN p_workflow_status BOOLEAN DEFAULT true,
                IN p_causer_id BIGINT DEFAULT NULL,
                IN p_causer_name TEXT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                old_workflow JSONB,
                new_workflow JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                p_inserted_or_updated_workflow_id BIGINT;
                existing_count INT;
                old_data JSONB;
                new_data JSONB;
            BEGIN
                -- Validate inputs
                IF p_workflow_request_type_id IS NULL OR p_workflow_request_type_id = 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Workflow request type ID cannot be null or zero', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                IF p_workflow_name IS NULL OR LENGTH(TRIM(p_workflow_name)) = 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Workflow name cannot be null or empty', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                IF p_workflow_description IS NULL THEN
                    RETURN QUERY SELECT 'FAILURE', 'Workflow description cannot be null', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                -- Check for existing workflow_request_type_id (for this tenant, not deleted)
                IF p_workflow_id IS NULL OR p_workflow_id = 0 THEN
                    IF EXISTS (
                        SELECT 1 FROM workflows
                        WHERE workflow_request_type_id = p_workflow_request_type_id
                        AND tenant_id = p_tenant_id
                        AND deleted_at IS NULL
                    ) THEN
                        RETURN QUERY SELECT 'FAILURE', 'Workflow request type ID already exists for this tenant', NULL::JSONB, NULL::JSONB;
                        RETURN;
                    END IF;
                ELSE
                    IF EXISTS (
                        SELECT 1 FROM workflows
                        WHERE workflow_request_type_id = p_workflow_request_type_id
                        AND tenant_id = p_tenant_id
                        AND id != p_workflow_id
                        AND deleted_at IS NULL
                    ) THEN
                        RETURN QUERY SELECT 'FAILURE', 'Workflow request type ID already exists for this tenant', NULL::JSONB, NULL::JSONB;
                        RETURN;
                    END IF;
                END IF;

                -- Check for existing workflow name
                IF p_workflow_id IS NULL OR p_workflow_id = 0 THEN
                    SELECT COUNT(*) INTO existing_count
                    FROM workflows
                    WHERE workflow_name = p_workflow_name
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;
                ELSE
                    SELECT COUNT(*) INTO existing_count
                    FROM workflows
                    WHERE workflow_name = p_workflow_name
                    AND tenant_id = p_tenant_id
                    AND id != p_workflow_id
                    AND deleted_at IS NULL;
                END IF;

                IF existing_count > 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Workflow name already exists', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                IF p_workflow_id IS NULL OR p_workflow_id = 0 THEN
                    -- Insert new workflow
                    INSERT INTO workflows (
                        workflow_request_type_id, 
                        workflow_name, 
                        workflow_description, 
                        workflow_status, 
                        tenant_id, 
                        created_at, 
                        updated_at
                    )
                    VALUES (
                        p_workflow_request_type_id, 
                        p_workflow_name, 
                        p_workflow_description, 
                        p_workflow_status, 
                        p_tenant_id, 
                        p_current_time, 
                        p_current_time
                    )
                    RETURNING id, to_jsonb(workflows.*) INTO p_inserted_or_updated_workflow_id, new_data;

                    -- Log activity for insert
                    BEGIN
                        PERFORM log_activity(
                            'insert_workflow',
                            format(
                                'Inserted workflow "%s" (ID: %s) for tenant %s by %s',
                                p_workflow_name,
                                p_inserted_or_updated_workflow_id,
                                p_tenant_id,
                                p_causer_name
                            ),
                            'workflows',
                            p_inserted_or_updated_workflow_id,
                            'user',
                            p_causer_id,
                            new_data,
                            p_tenant_id
                        );
                    EXCEPTION WHEN OTHERS THEN NULL;
                    END;

                    RETURN QUERY SELECT 'SUCCESS', 'Workflow inserted successfully', NULL::JSONB, new_data;
                ELSE
                    -- Fetch old data before updating
                    SELECT to_jsonb(workflows.*) INTO old_data
                    FROM workflows
                    WHERE id = p_workflow_id;

                    -- Update existing workflow
                    UPDATE workflows
                    SET 
                        workflow_request_type_id = p_workflow_request_type_id,
                        workflow_name = p_workflow_name,
                        workflow_description = p_workflow_description,
                        workflow_status = p_workflow_status,
                        updated_at = p_current_time
                    WHERE id = p_workflow_id;

                    -- Fetch new data after updating
                    SELECT to_jsonb(workflows.*) INTO new_data
                    FROM workflows
                    WHERE id = p_workflow_id;

                    -- Log activity for update
                    BEGIN
                        PERFORM log_activity(
                            'update_workflow',
                            format(
                                'Updated workflow "%s" (ID: %s) for tenant %s by %s',
                                p_workflow_name,
                                p_workflow_id,
                                p_tenant_id,
                                p_causer_name
                            ),
                            'workflows',
                            p_workflow_id,
                            'user',
                            p_causer_id,
                            jsonb_build_object('old', old_data, 'new', new_data),
                            p_tenant_id
                        );
                    EXCEPTION WHEN OTHERS THEN NULL;
                    END;

                    RETURN QUERY SELECT 'SUCCESS', 'Workflow updated successfully', old_data, new_data;
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
        DB::unprepared("DROP FUNCTION IF EXISTS insert_or_update_workflow;");
    }
};
