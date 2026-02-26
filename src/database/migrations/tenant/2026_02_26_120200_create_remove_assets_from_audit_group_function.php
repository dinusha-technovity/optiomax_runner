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
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION remove_assets_from_audit_group(
                p_audit_group_id BIGINT,
                p_asset_ids BIGINT[],
                p_tenant_id BIGINT,
                p_user_id BIGINT,
                p_user_name TEXT,
                p_current_time TIMESTAMP WITH TIME ZONE
            )
            RETURNS JSONB
            LANGUAGE plpgsql
            AS \$\$
            DECLARE
                asset_id_item BIGINT;
                removed_list JSONB := '[]'::JSONB;
                not_found_list JSONB := '[]'::JSONB;
                count_removed INT := 0;
                count_not_found INT := 0;
                audit_group_exists BOOLEAN;
                assignment_exists BOOLEAN;
                asset_info JSONB;
                rows_deleted INT;
                audit_group_name TEXT;
                activity_log_id BIGINT;
                result_json JSONB;
            BEGIN
                -- Verify audit group exists and get its name
                SELECT name INTO audit_group_name
                FROM audit_groups 
                WHERE id = p_audit_group_id 
                AND tenant_id = p_tenant_id 
                AND deleted_at IS NULL;
                
                IF audit_group_name IS NULL THEN
                    RETURN jsonb_build_object(
                        'status', 'FAILURE',
                        'message', 'Audit group not found',
                        'removed_count', 0,
                        'not_found_count', 0,
                        'removed_assets', '[]'::JSONB,
                        'not_found_assets', '[]'::JSONB
                    );
                END IF;
                
                -- Process each asset
                FOREACH asset_id_item IN ARRAY p_asset_ids
                LOOP
                    -- Get asset info before removal
                    SELECT jsonb_build_object(
                        'asset_id', ai.id,
                        'asset_tag', ai.asset_tag,
                        'model_number', ai.model_number,
                        'serial_number', ai.serial_number
                    ) INTO asset_info
                    FROM asset_items ai
                    WHERE ai.id = asset_id_item
                    AND ai.tenant_id = p_tenant_id;
                    
                    IF asset_info IS NULL THEN
                        -- Asset not found
                        SELECT jsonb_build_object(
                            'asset_id', asset_id_item,
                            'reason', 'Asset not found'
                        ) INTO asset_info;
                        
                        not_found_list := not_found_list || asset_info;
                        count_not_found := count_not_found + 1;
                        CONTINUE;
                    END IF;
                    
                    -- Check if assignment exists
                    SELECT EXISTS(
                        SELECT 1 FROM audit_groups_releated_assets
                        WHERE audit_group_id = p_audit_group_id
                        AND asset_id = asset_id_item
                        AND tenant_id = p_tenant_id
                        AND deleted_at IS NULL
                    ) INTO assignment_exists;
                    
                    IF NOT assignment_exists THEN
                        -- Assignment doesn't exist
                        asset_info := asset_info || jsonb_build_object('reason', 'Not assigned to this group');
                        not_found_list := not_found_list || asset_info;
                        count_not_found := count_not_found + 1;
                        CONTINUE;
                    END IF;
                    
                    -- Soft delete the assignment
                    UPDATE audit_groups_releated_assets
                    SET deleted_at = p_current_time,
                        isactive = FALSE,
                        updated_at = p_current_time
                    WHERE audit_group_id = p_audit_group_id
                    AND asset_id = asset_id_item
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;
                    
                    GET DIAGNOSTICS rows_deleted = ROW_COUNT;
                    
                    IF rows_deleted > 0 THEN
                        asset_info := asset_info || jsonb_build_object('removed_at', p_current_time);
                        removed_list := removed_list || asset_info;
                        count_removed := count_removed + 1;
                    ELSE
                        asset_info := asset_info || jsonb_build_object('reason', 'Failed to remove');
                        not_found_list := not_found_list || asset_info;
                        count_not_found := count_not_found + 1;
                    END IF;
                END LOOP;
                
                -- Create activity log entry if assets were removed
                IF count_removed > 0 THEN
                    INSERT INTO activity_log (
                        log_name,
                        description,
                        subject_type,
                        subject_id,
                        causer_type,
                        causer_id,
                        properties,
                        created_at,
                        updated_at
                    ) VALUES (
                        'audit_group',
                        format('Removed %s asset(s) from audit group "%s"', count_removed, audit_group_name),
                        'App\\Models\\AuditGroup',
                        p_audit_group_id,
                        'App\\Models\\User',
                        p_user_id,
                        jsonb_build_object(
                            'action', 'remove_assets',
                            'audit_group_id', p_audit_group_id,
                            'audit_group_name', audit_group_name,
                            'removed_assets', removed_list,
                            'user_name', p_user_name,
                            'tenant_id', p_tenant_id
                        ),
                        p_current_time,
                        p_current_time
                    ) RETURNING id INTO activity_log_id;
                END IF;
                
                -- Build and return result
                result_json := jsonb_build_object(
                    'status', 'SUCCESS',
                    'message', format('Removed %s asset(s), %s not found or not assigned', 
                                     count_removed, count_not_found),
                    'removed_count', count_removed,
                    'not_found_count', count_not_found,
                    'removed_assets', removed_list,
                    'not_found_assets', not_found_list,
                    'activity_log_id', activity_log_id
                );
                
                RETURN result_json;
            END;
            \$\$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(<<<SQL
            DROP FUNCTION IF EXISTS remove_assets_from_audit_group(
                BIGINT,
                BIGINT[],
                BIGINT,
                BIGINT,
                TEXT,
                TIMESTAMP WITH TIME ZONE
            );
        SQL);
    }
};
