<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Create audit group add assets function
     * 
     * This function adds multiple assets to an audit group in bulk.
     * Includes validation, duplicate checking, and comprehensive activity logging for each asset.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION add_assets_to_audit_group(
            p_group_id BIGINT,
            p_asset_ids BIGINT[],
            p_tenant_id BIGINT,
            p_user_id BIGINT,
            p_user_name VARCHAR DEFAULT NULL,
            p_current_time TIMESTAMPTZ DEFAULT now()
        ) RETURNS JSONB LANGUAGE plpgsql AS $$
        DECLARE
            v_group_exists BOOLEAN;
            v_group_name VARCHAR;
            v_asset_id BIGINT;
            v_added_count INT := 0;
            v_skipped_count INT := 0;
            v_asset_exists BOOLEAN;
            v_already_assigned BOOLEAN;
            v_asset_details RECORD;
        BEGIN
            -- Verify group exists
            SELECT EXISTS(
                SELECT 1 FROM audit_groups 
                WHERE id = p_group_id 
                    AND tenant_id = p_tenant_id 
                    AND deleted_at IS NULL
            ), name
            INTO v_group_exists, v_group_name
            FROM audit_groups
            WHERE id = p_group_id 
                AND tenant_id = p_tenant_id 
                AND deleted_at IS NULL
            LIMIT 1;

            IF NOT v_group_exists THEN
                RETURN jsonb_build_object(
                    'status', 'ERROR',
                    'message', 'Audit group not found'
                );
            END IF;

            -- Process each asset
            FOREACH v_asset_id IN ARRAY p_asset_ids
            LOOP
                -- Check if asset exists
                SELECT EXISTS(
                    SELECT 1 FROM asset_items 
                    WHERE id = v_asset_id 
                        AND tenant_id = p_tenant_id 
                        AND deleted_at IS NULL
                ) INTO v_asset_exists;

                IF NOT v_asset_exists THEN
                    v_skipped_count := v_skipped_count + 1;
                    CONTINUE;
                END IF;

                -- Check if already assigned to this group
                SELECT EXISTS(
                    SELECT 1 FROM audit_groups_releated_assets
                    WHERE audit_group_id = p_group_id
                        AND asset_id = v_asset_id
                        AND tenant_id = p_tenant_id
                        AND deleted_at IS NULL
                ) INTO v_already_assigned;

                IF v_already_assigned THEN
                    v_skipped_count := v_skipped_count + 1;
                    CONTINUE;
                END IF;

                -- Add asset to group
                INSERT INTO audit_groups_releated_assets (
                    audit_group_id,
                    asset_id,
                    tenant_id,
                    isactive,
                    created_at,
                    updated_at
                ) VALUES (
                    p_group_id,
                    v_asset_id,
                    p_tenant_id,
                    TRUE,
                    p_current_time,
                    p_current_time
                );

                v_added_count := v_added_count + 1;

                -- Get asset details for logging
                SELECT ai.asset_tag, a.name as asset_name
                INTO v_asset_details
                FROM asset_items ai
                INNER JOIN assets a ON a.id = ai.asset_id
                WHERE ai.id = v_asset_id;

                -- Log activity
                BEGIN
                    PERFORM log_activity(
                        'audit_group.asset_added',
                        'Asset "' || COALESCE(v_asset_details.asset_tag, 'N/A') || '" added to audit group "' || v_group_name || '"',
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
            END LOOP;

            RETURN jsonb_build_object(
                'status', 'SUCCESS',
                'message', v_added_count || ' asset(s) added successfully',
                'added_count', v_added_count,
                'skipped_count', v_skipped_count,
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
        DB::unprepared('DROP FUNCTION IF EXISTS add_assets_to_audit_group');
    }
};
