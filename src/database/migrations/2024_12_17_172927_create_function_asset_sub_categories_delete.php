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
            CREATE OR REPLACE FUNCTION delete_asset_sub_categories(
                p_asset_sub_categories_id BIGINT,
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
                rows_updated INT;         -- Tracks number of affected rows
                existing_count INT;       -- Tracks number of existing references
            BEGIN
                -- Check if the sub-category is referenced in the assets table
                SELECT COUNT(*) INTO existing_count
                FROM assets
                WHERE sub_category = p_asset_sub_categories_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;
            
                -- If references exist, return failure message
                IF existing_count > 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'You cannot delete this Sub Category, as it is associated with assets'::TEXT AS message;
                    RETURN;
                END IF;
            
                -- Mark the sub-category as deleted
                UPDATE asset_sub_categories
                SET deleted_at = p_current_time
                WHERE id = p_asset_sub_categories_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;
            
                -- Capture the number of rows updated
                GET DIAGNOSTICS rows_updated = ROW_COUNT;
            
                -- Return appropriate message based on the update result
                IF rows_updated > 0 THEN
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status, 
                        'Asset Sub Category deleted successfully'::TEXT AS message;
                ELSE
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No rows updated. Asset Sub Category not found or already deleted.'::TEXT AS message;
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
        DB::unprepared('DROP FUNCTION IF EXISTS delete_asset_sub_categories');
    }
};