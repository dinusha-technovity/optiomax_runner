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
            CREATE OR REPLACE FUNCTION get_asset_item_manufacturer_maintain_schedules(
                _tenant_id BIGINT,
                p_asset_item_id BIGINT DEFAULT NULL,
                p_id BIGINT DEFAULT NULL
            ) 
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                asset_item BIGINT,
                asset_item_serial_number TEXT,
                maintain_schedule_parameters BIGINT,
                maintain_schedule_parameters_name TEXT,
                limit_or_value TEXT,
                operator TEXT,
                reading_parameters TEXT,
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
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TIMESTAMP, NULL::BOOLEAN, NULL::BIGINT, NULL::TIMESTAMP, NULL::TIMESTAMP;
                    RETURN;
                END IF;

                -- Validate ID (optional)
                IF p_asset_item_id IS NOT NULL AND p_asset_item_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid Asset ID provided'::TEXT AS message,
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TIMESTAMP, NULL::BOOLEAN, NULL::BIGINT, NULL::TIMESTAMP, NULL::TIMESTAMP;
                    RETURN;
                END IF;

                -- Validate ID (optional)
                IF p_id IS NOT NULL AND p_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid ID provided'::TEXT AS message,
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TIMESTAMP, NULL::BOOLEAN, NULL::BIGINT, NULL::TIMESTAMP, NULL::TIMESTAMP;
                    RETURN;
                END IF;

                -- Check if any matching records exist
                SELECT COUNT(*) INTO record_count
                FROM asset_item_manufacturer_recommendation_maintain_schedules aimrms
                JOIN asset_items ai ON aimrms.asset_item = ai.id
                JOIN asset_maintain_schedule_parameters amsp ON aimrms.maintain_schedule_parameters = amsp.id
                WHERE (p_id IS NULL OR aimrms.id = p_id)
                AND (p_asset_item_id IS NULL OR aimrms.asset_item = p_asset_item_id)
                AND aimrms.tenant_id = _tenant_id
                AND aimrms.deleted_at IS NULL;

                IF record_count = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'No matching records found'::TEXT AS message,
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TIMESTAMP, NULL::BOOLEAN, NULL::BIGINT, NULL::TIMESTAMP, NULL::TIMESTAMP;
                    RETURN;
                END IF;

                -- Return the matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Data fetched successfully'::TEXT AS message,
                    aimrms.id,
                    aimrms.asset_item,
                    ai.serial_number::TEXT AS asset_item_serial_number,
                    aimrms.maintain_schedule_parameters,
                    amsp.name::TEXT AS maintain_schedule_parameters_name,
                    aimrms.limit_or_value::TEXT,
                    aimrms.operator::TEXT,
                    aimrms.reading_parameters::TEXT,
                    aimrms.deleted_at,
                    aimrms.isactive,
                    aimrms.tenant_id,
                    aimrms.created_at,
                    aimrms.updated_at
                FROM asset_item_manufacturer_recommendation_maintain_schedules aimrms
                JOIN asset_items ai ON aimrms.asset_item = ai.id
                JOIN asset_maintain_schedule_parameters amsp ON aimrms.maintain_schedule_parameters = amsp.id
                WHERE (p_id IS NULL OR aimrms.id = p_id)
                AND (p_asset_item_id IS NULL OR aimrms.asset_item = p_asset_item_id)
                AND aimrms.tenant_id = _tenant_id
                AND aimrms.deleted_at IS NULL;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_item_manufacturer_maintain_schedules');
    }
};
