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
            CREATE OR REPLACE FUNCTION get_asset_critically_based_maintain_schedules(
                _tenant_id BIGINT,
                p_asset_id BIGINT DEFAULT NULL,
                p_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                asset BIGINT,
                asset_name TEXT,
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

                IF p_asset_id IS NOT NULL AND p_asset_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid Asset ID provided'::TEXT AS message,
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TIMESTAMP, NULL::BOOLEAN, NULL::BIGINT, NULL::TIMESTAMP, NULL::TIMESTAMP;
                    RETURN;
                END IF;
        
                -- Check if any matching records exist
                SELECT COUNT(*) INTO record_count
                FROM asset_critically_based_maintain_schedules acbms
                JOIN assets a ON acbms.asset = a.id
                WHERE (p_id IS NULL OR acbms.id = p_id)
                AND acbms.tenant_id = _tenant_id
                AND acbms.deleted_at IS NULL;
        
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
                    acbms.id,
                    acbms.asset,
                    a.name::TEXT AS asset_name,
                    acbms.assessment_description,
                    acbms.schedule::TEXT,
                    acbms.expected_results::TEXT,
                    acbms.comments::TEXT,
                    acbms.deleted_at,
                    acbms.isactive,
                    acbms.tenant_id,
                    acbms.created_at,
                    acbms.updated_at
                FROM asset_critically_based_maintain_schedules acbms
                JOIN assets a ON acbms.asset = a.id
                WHERE (p_id IS NULL OR acbms.id = p_id)
                AND (p_asset_id IS NULL OR acbms.asset = p_asset_id)
                AND acbms.tenant_id = _tenant_id
                AND acbms.deleted_at IS NULL;
            END;
            $$;
        SQL);    
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_critically_based_maintain_schedules');
    }
};