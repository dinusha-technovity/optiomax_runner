<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration 
{
    /**
     * Run the migrations.
     */
    public function up(): void
    { 
        // DB::unprepared( 
        //     'CREATE OR REPLACE PROCEDURE store_procedure_delete_asset(
        //         IN p_asset_id bigint,
        //         IN p_current_time TIMESTAMP WITH TIME ZONE
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     BEGIN
        //         UPDATE assets
        //         SET 
        //             deleted_at = p_current_time
        //         WHERE id = p_asset_id;
        //     END; 
        //     $$;
        // '); 

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION delete_asset(
                p_asset_id BIGINT,
                p_tenant_id BIGINT,
                p_current_time TIMESTAMP WITH TIME ZONE
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                deleted_data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                rows_updated INT;          -- Tracks the number of affected rows
                asset_item_count INT;      -- Tracks references in sub-categories
                deleted_data JSONB;        -- Holds the data of the deleted asset
            BEGIN
                -- Check if the asset is referenced in the asset_items table
                SELECT COUNT(*) INTO asset_item_count
                FROM asset_items
                WHERE asset_id = p_asset_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

                -- If references exist, return failure message
                IF asset_item_count > 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'You cannot delete this Asset, as it is associated with active Asset Items'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                    RETURN;
                END IF;

                -- Fetch the asset data before deletion
                SELECT to_jsonb(assets) INTO deleted_data
                FROM assets
                WHERE id = p_asset_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

                -- Check if the asset exists
                IF deleted_data IS NULL THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No rows found. Asset not found or already deleted.'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                    RETURN;
                END IF;

                -- Mark the asset as deleted
                UPDATE assets
                SET deleted_at = p_current_time
                WHERE id = p_asset_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

                -- Capture the number of rows updated
                GET DIAGNOSTICS rows_updated = ROW_COUNT;

                -- Return appropriate message based on the update result
                IF rows_updated > 0 THEN
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status, 
                        'Asset deleted successfully'::TEXT AS message,
                        deleted_data;
                ELSE
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No rows updated. Asset not found or already deleted.'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
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
        DB::unprepared('DROP FUNCTION IF EXISTS delete_asset');
    }
};