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
        CREATE OR REPLACE FUNCTION update_asset_sub_categories_details(
            p_asset_sub_categories_id BIGINT,
            p_categories_name VARCHAR(255),
            p_categories_description TEXT,
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
            rows_updated INT;          -- Variable to capture affected rows
            existing_count INT;        -- Variable to count existing category names
        BEGIN
            -- Check if the category name already exists in another row
            SELECT COUNT(*) INTO existing_count
            FROM asset_sub_categories
            WHERE name = p_categories_name
            AND tenant_id = p_tenant_id
            AND id != p_asset_sub_categories_id -- Ensure it's not the same row
            AND deleted_at IS NULL;
        
            IF existing_count > 0 THEN
                -- Return failure message if category name already exists
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT AS status, 
                    'Sub Category name already exists'::TEXT AS message;
                RETURN;
            END IF;
        
            -- Update the asset_sub_categories table
            UPDATE asset_sub_categories
            SET 
                name = p_categories_name,
                description = p_categories_description,
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
                    'SUCCESS'::TEXT AS status, 
                    'Update Asset Sub Categories Details Successfully'::TEXT AS message;
            ELSE
                -- Return failure message if no rows were updated
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT AS status, 
                    'No rows updated. Asset Sub Category not found.'::TEXT AS message;
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
        DB::unprepared('DROP FUNCTION IF EXISTS update_asset_sub_categories_details');
    }
};
