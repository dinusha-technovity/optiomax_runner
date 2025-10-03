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
        //     'CREATE OR REPLACE PROCEDURE store_procedure_delete_asset_item(
        //         IN p_asset_item_id bigint,
        //         IN p_current_time TIMESTAMP WITH TIME ZONE
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     BEGIN
        //         UPDATE asset_items
        //         SET 
        //             deleted_at = p_current_time
        //         WHERE id = p_asset_item_id;
        //     END; 
        //     $$;
        // ');
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION delete_asset_item(
                p_asset_item_id BIGINT,
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
                rows_updated INT;           -- Tracks the number of affected rows
                deleted_data JSONB;         -- Holds the data of the deleted asset item
            BEGIN
                -- Fetch the asset item data before deletion
                SELECT to_jsonb(asset_items) INTO deleted_data
                FROM asset_items
                WHERE id = p_asset_item_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

                -- Check if the asset item exists
                IF deleted_data IS NULL THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No rows found. Asset Item not found or already deleted.'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                    RETURN;
                END IF;

                -- Mark the asset item as deleted
                UPDATE asset_items
                SET deleted_at = p_current_time
                WHERE id = p_asset_item_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

                -- Capture the number of rows updated
                GET DIAGNOSTICS rows_updated = ROW_COUNT;

                -- Return appropriate message based on the update result
                IF rows_updated > 0 THEN
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status, 
                        'Asset Item deleted successfully'::TEXT AS message,
                        deleted_data;
                ELSE
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No rows updated. Asset Item not found or already deleted.'::TEXT AS message,
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
        DB::unprepared('DROP FUNCTION IF EXISTS delete_asset_item');
    }
};
