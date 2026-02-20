<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Create audit group delete function
     * 
     * This function performs a soft delete of an audit group and cascades
     * to remove all associated asset relations. Includes comprehensive activity logging.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION delete_audit_group(
            p_group_id BIGINT,
            p_tenant_id BIGINT,
            p_user_id BIGINT,
            p_user_name VARCHAR DEFAULT NULL,
            p_current_time TIMESTAMPTZ DEFAULT now()
        ) RETURNS JSONB LANGUAGE plpgsql AS $$
        DECLARE
            v_group_exists BOOLEAN;
            v_group_name VARCHAR;
            v_asset_count INT;
        BEGIN
            -- Verify group exists and get details
            SELECT 
                EXISTS(SELECT 1 FROM audit_groups WHERE id = p_group_id AND tenant_id = p_tenant_id AND deleted_at IS NULL),
                name
            INTO v_group_exists, v_group_name
            FROM audit_groups
            WHERE id = p_group_id AND tenant_id = p_tenant_id AND deleted_at IS NULL
            LIMIT 1;

            IF NOT v_group_exists THEN
                RETURN jsonb_build_object(
                    'status', 'ERROR',
                    'message', 'Audit group not found'
                );
            END IF;

            -- Get asset count
            SELECT COUNT(*)
            INTO v_asset_count
            FROM audit_groups_releated_assets
            WHERE audit_group_id = p_group_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

            -- Soft delete all associated assets first
            UPDATE audit_groups_releated_assets
            SET 
                deleted_at = p_current_time,
                isactive = FALSE,
                updated_at = p_current_time
            WHERE audit_group_id = p_group_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

            -- Soft delete the group
            UPDATE audit_groups
            SET 
                deleted_at = p_current_time,
                isactive = FALSE,
                updated_at = p_current_time
            WHERE id = p_group_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

            -- Log activity
            BEGIN
                PERFORM log_activity(
                    'audit_group.deleted',
                    'Audit group "' || v_group_name || '" deleted (contained ' || v_asset_count || ' assets)',
                    'audit_groups',
                    p_group_id,
                    'user',
                    p_user_id,
                    jsonb_build_object(
                        'group_id', p_group_id,
                        'group_name', v_group_name,
                        'asset_count', v_asset_count,
                        'user_name', p_user_name,
                        'action_time', p_current_time
                    ),
                    p_tenant_id
                );
            EXCEPTION WHEN OTHERS THEN
                RAISE NOTICE 'Log activity failed: %', SQLERRM;
            END;

            RETURN jsonb_build_object(
                'status', 'SUCCESS',
                'message', 'Audit group deleted successfully',
                'group_id', p_group_id,
                'group_name', v_group_name,
                'assets_removed', v_asset_count
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
        DB::unprepared('DROP FUNCTION IF EXISTS delete_audit_group');
    }
};
