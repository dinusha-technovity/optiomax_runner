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
            CREATE OR REPLACE FUNCTION get_asset_maintain_schedule_params(
                IN p_tenant_id BIGINT,
                IN p_asset_maintain_schedule_parameters_id BIGINT DEFAULT NULL,
                IN p_asset_maintain_schedule_type_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                name TEXT,
                asset_maintain_schedule_type BIGINT,
                asset_maintain_schedule_type_name TEXT,
                tenant_id BIGINT
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::BIGINT AS asset_maintain_schedule_type,
                        NULL::TEXT AS asset_maintain_schedule_type_name,
                        NULL::BIGINT AS tenant_id;
                    RETURN;
                END IF;

                -- Validate asset maintain schedule parameters ID (optional)
                IF p_asset_maintain_schedule_parameters_id IS NOT NULL AND p_asset_maintain_schedule_parameters_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid asset maintain schedule parameters ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::BIGINT AS asset_maintain_schedule_type,
                        NULL::TEXT AS asset_maintain_schedule_type_name,
                        NULL::BIGINT AS tenant_id;
                    RETURN;
                END IF;

                -- Validate asset maintain schedule type ID (optional)
                IF p_asset_maintain_schedule_type_id IS NOT NULL AND p_asset_maintain_schedule_type_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid asset maintain schedule type ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::BIGINT AS asset_maintain_schedule_type,
                        NULL::TEXT AS asset_maintain_schedule_type_name,
                        NULL::BIGINT AS tenant_id;
                    RETURN;
                END IF;

                -- Return only the specified asset_maintain_schedule_parameters_id if provided
                IF p_asset_maintain_schedule_parameters_id IS NOT NULL THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT AS status,
                        'Asset maintain schedule parameter fetched successfully'::TEXT AS message,
                        a.id,
                        a.name::TEXT,
                        a.asset_maintain_schedule_type,
                        t.name::TEXT AS asset_maintain_schedule_type_name,
                        a.tenant_id
                    FROM asset_maintain_schedule_parameters a
                    JOIN asset_maintain_schedule_types t ON a.asset_maintain_schedule_type = t.id
                    WHERE a.tenant_id = p_tenant_id
                    AND a.id = p_asset_maintain_schedule_parameters_id
                    AND a.deleted_at IS NULL
                    AND a.isactive = TRUE;
                    RETURN;
                END IF;

                -- Return matching records if no specific asset_maintain_schedule_parameters_id is provided
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Asset maintain schedule parameters fetched successfully'::TEXT AS message,
                    a.id,
                    a.name::TEXT,
                    a.asset_maintain_schedule_type,
                    t.name::TEXT AS asset_maintain_schedule_type_name,
                    a.tenant_id
                FROM asset_maintain_schedule_parameters a
                JOIN asset_maintain_schedule_types t ON a.asset_maintain_schedule_type = t.id
                WHERE a.tenant_id = p_tenant_id
                AND (p_asset_maintain_schedule_type_id IS NULL OR a.asset_maintain_schedule_type = p_asset_maintain_schedule_type_id)
                AND a.deleted_at IS NULL
                AND a.isactive = TRUE;

            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_all_workflow_condition_system_variable');
    }
};