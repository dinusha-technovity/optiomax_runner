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
            CREATE OR REPLACE FUNCTION get_asset_item_maintenance_tasks(
                _tenant_id BIGINT,
                p_asset_item_id BIGINT DEFAULT NULL,
                p_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                asset_item BIGINT,
                maintenance_tasks_description TEXT,
                schedule TEXT,
                expected_results TEXT,
                task_type BIGINT,
                task_type_name TEXT,
                deleted_at TIMESTAMP,
                isactive BOOLEAN,
                tenant_id BIGINT,
                created_at TIMESTAMP,
                updated_at TIMESTAMP
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                record_count INT;
            BEGIN
                -- Validate tenant ID
                IF _tenant_id IS NULL OR _tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::BIGINT, NULL::TIMESTAMP, NULL::BOOLEAN, NULL::BIGINT, NULL::TIMESTAMP, NULL::TIMESTAMP;
                    RETURN;
                END IF;

                -- Validate ID (optional)
                IF p_id IS NOT NULL AND p_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid ID provided'::TEXT AS message,
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::BIGINT, NULL::TIMESTAMP, NULL::BOOLEAN, NULL::BIGINT, NULL::TIMESTAMP, NULL::TIMESTAMP;
                    RETURN;
                END IF;

                IF p_asset_item_id IS NOT NULL AND p_asset_item_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid Asset ID provided'::TEXT AS message,
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::BIGINT, NULL::TIMESTAMP, NULL::BOOLEAN, NULL::BIGINT, NULL::TIMESTAMP, NULL::TIMESTAMP;
                    RETURN;
                END IF;

                -- Check if any matching records exist
                SELECT COUNT(*) INTO record_count
                FROM asset_item_maintenance_tasks mt
                WHERE (p_id IS NULL OR mt.id = p_id)
                AND mt.tenant_id = _tenant_id
                AND mt.deleted_at IS NULL;

                IF record_count = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'No matching records found'::TEXT AS message,
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::BIGINT, NULL::TIMESTAMP, NULL::BOOLEAN, NULL::BIGINT, NULL::TIMESTAMP, NULL::TIMESTAMP;
                    RETURN;
                END IF;

                -- Return the matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Data fetched successfully'::TEXT AS message,
                    mt.id,
                    mt.asset_item,
                    mt.maintenance_tasks_description::TEXT,
                    mt.schedule::TEXT,
                    mt.expected_results::TEXT,
                    mt.task_type,
                    mtt.name::TEXT AS task_type_name,
                    mt.deleted_at,
                    mt.isactive,
                    mt.tenant_id,
                    mt.created_at,
                    mt.updated_at
                FROM asset_item_maintenance_tasks mt
                JOIN maintenance_tasks_type mtt ON mt.task_type = mtt.id
                WHERE (p_id IS NULL OR mt.id = p_id)
                AND (p_asset_item_id IS NULL OR mt.asset_item = p_asset_item_id)
                AND mt.tenant_id = _tenant_id
                AND mt.deleted_at IS NULL;
            END;
            $$;
        SQL);  
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_item_maintenance_tasks');
    }
};