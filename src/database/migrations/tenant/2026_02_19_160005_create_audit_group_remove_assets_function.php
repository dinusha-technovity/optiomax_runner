<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Create audit group remove assets function
     * 
     * This function removes multiple assets from an audit group in bulk via soft delete.
     * Includes comprehensive activity logging for each removed asset.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION remove_assets_from_audit_group(
            p_group_id BIGINT,
            p_asset_ids BIGINT[],
            p_tenant_id BIGINT,
            p_user_id BIGINT,
            p_user_name VARCHAR DEFAULT NULL,
            p_current_time TIMESTAMPTZ DEFAULT now()
        ) RETURNS JSONB LANGUAGE plpgsql AS $$
        DECLARE
            v_group_name VARCHAR;
            v_asset_id BIGINT;
            v_removed_count INT := 0;
            v_asset_details RECORD;
        BEGIN
            -- Get group name
            SELECT name INTO v_group_name
            FROM audit_groups
            WHERE id = p_group_id 
                AND tenant_id = p_tenant_id 
                AND deleted_at IS NULL;

            IF v_group_name IS NULL THEN
                RETURN jsonb_build_object(
                    'status', 'ERROR',
                    'message', 'Audit group not found'
                );
            END IF;

            -- Process each asset
            FOREACH v_asset_id IN ARRAY p_asset_ids
            LOOP
                -- Get asset details for logging
                SELECT ai.asset_tag, a.name as asset_name
                INTO v_asset_details
                FROM asset_items ai
                INNER JOIN assets a ON a.id = ai.asset_id
                WHERE ai.id = v_asset_id;

                -- Soft delete the relation
                UPDATE audit_groups_releated_assets
                SET 
                    deleted_at = p_current_time,
                    isactive = FALSE,
                    updated_at = p_current_time
                WHERE audit_group_id = p_group_id
                    AND asset_id = v_asset_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;

                IF FOUND THEN
                    v_removed_count := v_removed_count + 1;

                    -- Log activity
                    BEGIN
                        PERFORM log_activity(
                            'audit_group.asset_removed',
                            'Asset "' || COALESCE(v_asset_details.asset_tag, 'N/A') || '" removed from audit group "' || v_group_name || '"',
                            'audit_groups_releated_assets',
                            p_group_id,
                            'user',
                            p_user_id,
                            jsonb_build_object(
                                'group_id', p_group_id,
                                'group_name', v_group_name,
                                'asset_id', v_asset_id,
                                'asset_tag', v_asset_details.asset_tag,
                                'asset_name', v_asset_details.asset_name,
                                'user_name', p_user_name,
                                'action_time', p_current_time
                            ),
                            p_tenant_id
                        );
                    EXCEPTION WHEN OTHERS THEN
                        RAISE NOTICE 'Log activity failed: %', SQLERRM;
                    END;
                END IF;
            END LOOP;

            RETURN jsonb_build_object(
                'status', 'SUCCESS',
                'message', v_removed_count || ' asset(s) removed successfully',
                'removed_count', v_removed_count,
                'group_id', p_group_id
            );

        EXCEPTION
            WHEN OTHERS THEN
                RETURN jsonb_build_object(
                    'status', 'ERROR',
                    'message', 'An error occurred: ' || SQLERRM
                );
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS remove_assets_from_audit_group');
    }
};
