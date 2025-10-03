<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION delete_item(
                p_item_id BIGINT,
                p_tenant_id BIGINT,
                p_current_time TIMESTAMP WITH TIME ZONE,
                p_user_id BIGINT,
                p_user_name VARCHAR
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                rows_updated INT;         -- Tracks number of affected rows in items table
                supplier_ref_count INT;   -- Tracks references in suppliers_for_item table
                v_deleted_data JSONB;
                v_log_success BOOLEAN;

            BEGIN
                -- Check if the item is referenced in active suppliers_for_item records
                SELECT COUNT(*) INTO supplier_ref_count
                FROM suppliers_for_item
                WHERE master_item_id = p_item_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

                -- Soft delete the referenced suppliers_for_item records first
                IF supplier_ref_count > 0 THEN
                    UPDATE suppliers_for_item
                    SET deleted_at = p_current_time
                    WHERE master_item_id = p_item_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;
                END IF;

                -- get old data and store it in my deleted dara variabe

                SELECT to_jsonb(items) INTO v_deleted_data
                FROM items
                WHERE id = p_item_id;

                -- Mark the item as deleted
                UPDATE items
                SET deleted_at = p_current_time
                WHERE id = p_item_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

                -- now loggin

                -- Log the activity (with error handling)###################
                    BEGIN
                        PERFORM log_activity(
                            'item.' || 'soft delete item',
                            'Item ' || ' item deleted' || ' by ' || p_user_name,
                            'item',
                            p_item_id,
                            'user',
                            p_user_id,
                            v_deleted_data,
                            p_tenant_id
                        );
                        v_log_success := TRUE;
                    EXCEPTION WHEN OTHERS THEN
                        v_log_success := FALSE;
                    END;
                    -- end loggin #############################################

                -- Capture the number of rows updated in items table
                GET DIAGNOSTICS rows_updated = ROW_COUNT;

                -- Return appropriate message based on the update result
                IF rows_updated > 0 THEN
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status, 
                        'Item and associated supplier records deleted successfully'::TEXT AS message;
                ELSE
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No rows updated. Item not found or already deleted.'::TEXT AS message;
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
        DB::unprepared("DROP FUNCTION IF EXISTS delete_item");

    }
};
