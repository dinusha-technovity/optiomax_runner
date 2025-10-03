<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION update_asset_sub_categories_reading_parameters(
                p_asset_sub_categories_id BIGINT,
                p_reading_parameters JSONB,
                p_tenant_id BIGINT,
                p_current_time TIMESTAMP WITH TIME ZONE
            )
            RETURNS TABLE (
                status TEXT, 
                message TEXT
            )
            LANGUAGE plpgsql 
            AS $$
            DECLARE
                rows_updated INT; -- Variable to capture affected rows
            BEGIN
                -- Update the asset_sub_categories table
                UPDATE asset_sub_categories
                SET 
                    reading_parameters = p_reading_parameters,
                    updated_at = p_current_time
                WHERE id = p_asset_sub_categories_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;
        
                -- Capture the number of rows updated
                GET DIAGNOSTICS rows_updated = ROW_COUNT;
        
                -- Check if the update was successful
                IF rows_updated > 0 THEN
                    -- Return success message
                    RETURN QUERY SELECT 
                        'SUCCESS' AS status, 
                        'Update Asset Sub Categories Reading Parameters Successfully' AS message;
                ELSE
                    -- Return failure message if no rows were updated
                    RETURN QUERY SELECT 
                        'FAILURE' AS status, 
                        'No rows updated. Asset Sub Category not found.' AS message;
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
        DB::unprepared('DROP FUNCTION IF EXISTS update_asset_sub_categories_reading_parameters');
    }
};
