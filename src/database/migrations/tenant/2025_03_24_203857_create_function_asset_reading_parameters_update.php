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
            CREATE OR REPLACE FUNCTION update_asset_reading_parameters(
                p_asset_id BIGINT,
                p_reading_parameters JSONB,
                p_tenant_id BIGINT,
                p_current_time TIMESTAMP WITH TIME ZONE
            )
            RETURNS TABLE (
                status TEXT, 
                message TEXT,
                before_data JSONB,
                after_data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                rows_updated INT;       -- Variable to capture affected rows
                data_before JSONB;      -- Variable to store data before the update
                data_after JSONB;       -- Variable to store data after the update
            BEGIN
                -- Fetch data before the update
                SELECT jsonb_build_object(
                    'id', id,
                    'tenant_id', tenant_id,
                    'reading_parameters', reading_parameters,
                    'updated_at', updated_at
                ) INTO data_before
                FROM assets
                WHERE id = p_asset_id
                AND tenant_id = p_tenant_id;
        
                -- Update the assets table
                UPDATE assets
                SET 
                    reading_parameters = p_reading_parameters,
                    updated_at = p_current_time
                WHERE id = p_asset_id
                AND tenant_id = p_tenant_id;
        
                -- Capture the number of rows updated
                GET DIAGNOSTICS rows_updated = ROW_COUNT;
        
                -- Fetch data after the update if rows were updated
                IF rows_updated > 0 THEN
                    SELECT jsonb_build_object(
                        'id', id,
                        'tenant_id', tenant_id,
                        'reading_parameters', reading_parameters,
                        'updated_at', updated_at
                    ) INTO data_after
                    FROM assets
                    WHERE id = p_asset_id
                    AND tenant_id = p_tenant_id;
        
                    -- Return success with before and after data
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status, 
                        'Asset reading parameters updated successfully'::TEXT AS message,
                        data_before,
                        data_after;
                ELSE
                    -- Return failure message with before data and null after data
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No rows updated. Asset not found or tenant mismatch.'::TEXT AS message,
                        data_before,
                        NULL::JSONB AS after_data;
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
        DB::unprepared('DROP FUNCTION IF EXISTS update_asset_reading_parameters');
    }
};
