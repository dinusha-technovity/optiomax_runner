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
            CREATE OR REPLACE FUNCTION insert_or_update_asset_item_usage_maintain_schedules(
                IN p_asset_item BIGINT,
                IN p_maintain_schedule_parameters BIGINT,
                IN p_limit_or_value TEXT,
                IN p_operator TEXT,
                IN p_reading_parameters TEXT,
                IN p_tenant_id BIGINT,
                IN p_current_time TIMESTAMP WITH TIME ZONE,
                IN p_schedule_id BIGINT DEFAULT NULL
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
                p_inserted_or_updated_schedule_id BIGINT;
                existing_count INT;
                old_record JSONB;
                new_record JSONB;
            BEGIN
                -- Validate inputs
                IF p_asset_item IS NULL OR p_asset_item = 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Asset ID cannot be null or zero', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                IF p_maintain_schedule_parameters IS NULL OR p_maintain_schedule_parameters = 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Maintain schedule parameters cannot be null or zero', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                IF p_limit_or_value IS NULL OR LENGTH(TRIM(p_limit_or_value)) = 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'limit_or_value cannot be null or empty', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                IF p_operator IS NULL OR LENGTH(TRIM(p_operator)) = 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Operator cannot be null or empty', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                IF p_reading_parameters IS NULL OR LENGTH(TRIM(p_reading_parameters)) = 0 THEN
                    RETURN QUERY SELECT 'FAILURE', 'Reading parameters cannot be null or empty', NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                IF p_schedule_id IS NULL OR p_schedule_id = 0 THEN
                    -- Insert new record
                    INSERT INTO asset_item_usage_based_maintain_schedules (
                        asset_item, 
                        maintain_schedule_parameters, 
                        limit_or_value, 
                        operator, 
                        reading_parameters, 
                        tenant_id, 
                        created_at, 
                        updated_at
                    )
                    VALUES (
                        p_asset_item, 
                        p_maintain_schedule_parameters, 
                        p_limit_or_value, 
                        p_operator, 
                        p_reading_parameters, 
                        p_tenant_id, 
                        p_current_time, 
                        p_current_time
                    )
                    RETURNING id, to_jsonb(asset_item_usage_based_maintain_schedules.*) INTO p_inserted_or_updated_schedule_id, new_record;

                    RETURN QUERY SELECT 'SUCCESS', 'Usage based maintain schedules created successfully', NULL::JSONB, new_record;
                ELSE
                    -- Fetch old data before updating
                    SELECT to_jsonb(asset_item_usage_based_maintain_schedules.*) INTO old_record
                    FROM asset_item_usage_based_maintain_schedules
                    WHERE id = p_schedule_id;

                    -- Update existing record
                    UPDATE asset_item_usage_based_maintain_schedules
                    SET 
                        asset_item = p_asset_item,
                        maintain_schedule_parameters = p_maintain_schedule_parameters,
                        limit_or_value = p_limit_or_value,
                        operator = p_operator,
                        reading_parameters = p_reading_parameters,
                        updated_at = p_current_time
                    WHERE id = p_schedule_id;

                    -- Fetch new data after updating
                    SELECT to_jsonb(asset_item_usage_based_maintain_schedules.*) INTO new_record
                    FROM asset_item_usage_based_maintain_schedules
                    WHERE id = p_schedule_id;

                    RETURN QUERY SELECT 'SUCCESS', 'Usage based maintain schedules updated successfully', old_record, new_record;
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
        DB::unprepared('DROP FUNCTION IF EXISTS insert_or_update_asset_item_usage_maintain_schedules');
    }
};
