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
            CREATE OR REPLACE FUNCTION get_asset_item_critically_based_maintain_schedules(
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
                assessment_description TEXT,
                schedule TEXT,
                expected_results TEXT,
                comments TEXT,
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
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TIMESTAMP, NULL::BOOLEAN, NULL::BIGINT, NULL::TIMESTAMP, NULL::TIMESTAMP;
                    RETURN;
                END IF;
        
                -- Validate ID (optional)
                IF p_id IS NOT NULL AND p_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid ID provided'::TEXT AS message,
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TIMESTAMP, NULL::BOOLEAN, NULL::BIGINT, NULL::TIMESTAMP, NULL::TIMESTAMP;
                    RETURN;
                END IF;

                IF p_asset_item_id IS NOT NULL AND p_asset_item_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid Asset ID provided'::TEXT AS message,
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TIMESTAMP, NULL::BOOLEAN, NULL::BIGINT, NULL::TIMESTAMP, NULL::TIMESTAMP;
                    RETURN;
                END IF;
        
                -- Check if any matching records exist
                SELECT COUNT(*) INTO record_count
                FROM asset_item_critically_based_maintain_schedules aicbms
                JOIN asset_items ai ON aicbms.asset_item = ai.id
                WHERE (p_id IS NULL OR aicbms.id = p_id)
                AND (p_asset_item_id IS NULL OR aicbms.asset_item = p_asset_item_id)
                AND aicbms.tenant_id = _tenant_id
                AND aicbms.deleted_at IS NULL;
        
                IF record_count = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'No matching records found'::TEXT AS message,
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TIMESTAMP, NULL::BOOLEAN, NULL::BIGINT, NULL::TIMESTAMP, NULL::TIMESTAMP;
                    RETURN;
                END IF;

                -- Return the matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Data fetched successfully'::TEXT AS message,
                    aicbms.id,
                    aicbms.asset_item,
                    ai.serial_number::TEXT AS asset_item_serial_number,
                    aicbms.assessment_description,
                    aicbms.schedule::TEXT,
                    aicbms.expected_results::TEXT,
                    aicbms.comments::TEXT,
                    aicbms.deleted_at,
                    aicbms.isactive,
                    aicbms.tenant_id,
                    aicbms.created_at,
                    aicbms.updated_at
                FROM asset_item_critically_based_maintain_schedules aicbms
                JOIN asset_items ai ON aicbms.asset_item = ai.id
                WHERE (p_id IS NULL OR aicbms.id = p_id)
                AND (p_asset_item_id IS NULL OR aicbms.asset_item = p_asset_item_id)
                AND aicbms.tenant_id = _tenant_id
                AND aicbms.deleted_at IS NULL;
            END;
            $$;
        SQL);  
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_item_critically_based_maintain_schedules');
    }
};