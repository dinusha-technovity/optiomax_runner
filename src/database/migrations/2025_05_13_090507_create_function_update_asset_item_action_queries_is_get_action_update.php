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
            CREATE OR REPLACE FUNCTION update_asset_item_action_queries_get_action(
                p_action_queries_id BIGINT,
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
                rows_updated INT;
                v_existing_data JSONB;
                v_log_success BOOLEAN;
            BEGIN
                -- Snapshot the existing row before update
                SELECT to_jsonb(asset_item_action_queries) INTO v_existing_data
                FROM asset_item_action_queries
                WHERE id = p_action_queries_id;

                -- Attempt to mark the item as 'get action'
                UPDATE asset_item_action_queries
                SET 
                    is_get_action = TRUE,
                    updated_at = p_current_time
                WHERE id = p_action_queries_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

                GET DIAGNOSTICS rows_updated = ROW_COUNT;

                -- Log the action, gracefully handle failure
                BEGIN
                    PERFORM log_activity(
                        'asset_item_action_queries.get_action',
                        format('User %s triggered get_action on asset_item_action_queries', p_user_name),
                        'asset_item_action_queries',
                        p_action_queries_id,
                        'user',
                        p_user_id,
                        v_existing_data,
                        p_tenant_id
                    );
                    v_log_success := TRUE;
                EXCEPTION WHEN OTHERS THEN
                    v_log_success := FALSE;
                END;

                -- Respond
                IF rows_updated > 0 THEN
                    RETURN QUERY SELECT 
                        'SUCCESS', 
                        'asset item action queries get action successfully';
                ELSE
                    RETURN QUERY SELECT 
                        'FAILURE', 
                        'No rows updated. Item not found or already deleted.';
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
        DB::unprepared('DROP FUNCTION IF EXISTS update_asset_item_action_queries_get_action');
    }
};
