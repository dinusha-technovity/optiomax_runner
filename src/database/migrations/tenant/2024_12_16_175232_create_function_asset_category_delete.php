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
        // DB::unprepared(<<<SQL
        //     CREATE OR REPLACE FUNCTION delete_asset_category(
        //         p_asset_categories_id BIGINT,
        //         p_current_time TIMESTAMP WITH TIME ZONE
        //     )
        //     RETURNS TABLE (
        //         status TEXT,
        //         message TEXT
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     BEGIN
        //         -- Update the asset_categories table
        //         UPDATE asset_categories
        //         SET 
        //             deleted_at = p_current_time
        //         WHERE id = p_asset_categories_id;

        //         -- Check if the update was successful
        //         IF FOUND THEN
        //             -- Return success message
        //             RETURN QUERY SELECT 
        //                 'SUCCESS' AS status, 
        //                 'Asset Categories Deleted Successfully' AS message;
        //         ELSE
        //             -- Return failure message if no rows were updated
        //             RETURN QUERY SELECT 
        //                 'FAILURE' AS status, 
        //                 'No rows updated. Asset category not found.' AS message;
        //         END IF;
        //     END;
        //     $$;
        // SQL);
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION delete_asset_category(
                p_asset_categories_id BIGINT,
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
                sub_category_count INT;   -- Tracks references in sub-categories
                asset_count INT;          -- Tracks references in assets
            BEGIN
                -- Check if the category is referenced in the asset_sub_categories table
                SELECT COUNT(*) INTO sub_category_count
                FROM asset_sub_categories
                WHERE asset_category_id = p_asset_categories_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

                -- If references exist, return failure message
                IF sub_category_count > 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'You cannot delete this Category, as it is associated with active Sub Categories'::TEXT AS message;
                    RETURN;
                END IF;

                -- Check if the category is referenced in the assets table (directly or via sub-categories)
                SELECT COUNT(*) INTO asset_count
                FROM assets
                WHERE category = p_asset_categories_id -- Assuming this is the correct reference
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

                -- If references exist, return failure message
                IF asset_count > 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'You cannot delete this Category, as it is associated with active assets'::TEXT AS message;
                    RETURN;
                END IF;

                -- Mark the category as deleted
                UPDATE asset_categories
                SET deleted_at = p_current_time
                WHERE id = p_asset_categories_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

                -- Capture the number of rows updated
                GET DIAGNOSTICS rows_updated = ROW_COUNT;

                -- Return appropriate message based on the update result
                IF rows_updated > 0 THEN
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status, 
                        'Asset Category deleted successfully'::TEXT AS message;
                ELSE
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No rows updated. Asset Category not found or already deleted.'::TEXT AS message;
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
        DB::unprepared('DROP FUNCTION IF EXISTS delete_asset_category');
    }
};