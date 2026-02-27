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
            CREATE OR REPLACE FUNCTION add_assets_to_audit_group(
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
                added_list JSONB := '[]'::JSONB;
                already_assigned_list JSONB := '[]'::JSONB;
                invalid_list JSONB := '[]'::JSONB;
                count_added INT := 0;
                count_already_assigned INT := 0;
                count_invalid INT := 0;
                audit_group_exists BOOLEAN;
                asset_exists BOOLEAN;
                already_assigned BOOLEAN;
                asset_info JSONB;
                audit_group_name TEXT;
                activity_log_id BIGINT;
                result_json JSONB;
            BEGIN
                -- Verify audit group exists and get its name
                SELECT name INTO audit_group_name
                FROM audit_groups 
                WHERE id = p_audit_group_id 
                AND tenant_id = p_tenant_id 
                AND deleted_at IS NULL
                AND isactive = TRUE;
                
                IF audit_group_name IS NULL THEN
                    RETURN jsonb_build_object(
                        'status', 'FAILURE',
                        'message', 'Audit group not found or inactive',
                        'added_count', 0,
                        'already_assigned_count', 0,
                        'invalid_assets_count', 0,
                        'added_assets', '[]'::JSONB,
                        'already_assigned_assets', '[]'::JSONB,
                        'invalid_assets', '[]'::JSONB
                    );
                END IF;
                
                -- Process each asset
                FOREACH asset_id_item IN ARRAY p_asset_ids
                LOOP
                    -- Check if asset exists and belongs to tenant
                    SELECT EXISTS(
                        SELECT 1 FROM asset_items ai
                        WHERE ai.id = asset_id_item 
                        AND ai.tenant_id = p_tenant_id
                        AND ai.deleted_at IS NULL
                    ) INTO asset_exists;
                    
                    IF NOT asset_exists THEN
                        -- Asset doesn't exist or doesn't belong to tenant
                        SELECT jsonb_build_object(
                            'asset_id', asset_id_item,
                            'reason', 'Asset not found or does not belong to tenant'
                        ) INTO asset_info;
                        
                        invalid_list := invalid_list || asset_info;
                        count_invalid := count_invalid + 1;
                        CONTINUE;
                    END IF;
                    
                    -- Check if asset is already assigned to this group
                    SELECT EXISTS(
                        SELECT 1 FROM audit_groups_releated_assets
                        WHERE audit_group_id = p_audit_group_id
                        AND asset_id = asset_id_item
                        AND tenant_id = p_tenant_id
                        AND deleted_at IS NULL
                        AND isactive = TRUE
                    ) INTO already_assigned;
                    
                    IF already_assigned THEN
                        -- Asset already assigned
                        SELECT jsonb_build_object(
                            'asset_id', ai.id,
                            'asset_tag', ai.asset_tag,
                            'model_number', ai.model_number,
                            'serial_number', ai.serial_number
                        ) INTO asset_info
                        FROM asset_items ai
                        WHERE ai.id = asset_id_item;
                        
                        already_assigned_list := already_assigned_list || asset_info;
                        count_already_assigned := count_already_assigned + 1;
                        CONTINUE;
                    END IF;
                    
                    -- Add asset to audit group
                    INSERT INTO audit_groups_releated_assets (
                        audit_group_id,
                        asset_id,
                        tenant_id,
                        isactive,
                        created_at,
                        updated_at
                    ) VALUES (
                        p_audit_group_id,
                        asset_id_item,
                        p_tenant_id,
                        TRUE,
                        p_current_time,
                        p_current_time
                    );
                    
                    -- Get asset info for response
                    SELECT jsonb_build_object(
                        'asset_id', ai.id,
                        'asset_tag', ai.asset_tag,
                        'model_number', ai.model_number,
                        'serial_number', ai.serial_number,
                        'assigned_at', p_current_time
                    ) INTO asset_info
                    FROM asset_items ai
                    WHERE ai.id = asset_id_item;
                    
                    added_list := added_list || asset_info;
                    count_added := count_added + 1;
                END LOOP;
                
                -- Create activity log entry if assets were added
                IF count_added > 0 THEN
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
                        format('Added %s asset(s) to audit group "%s"', count_added, audit_group_name),
                        'App\\Models\\AuditGroup',
                        p_audit_group_id,
                        'App\\Models\\User',
                        p_user_id,
                        jsonb_build_object(
                            'action', 'add_assets',
                            'audit_group_id', p_audit_group_id,
                            'audit_group_name', audit_group_name,
                            'added_assets', added_list,
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
                    'message', format('Added %s asset(s), %s already assigned, %s invalid', 
                                     count_added, count_already_assigned, count_invalid),
                    'added_count', count_added,
                    'already_assigned_count', count_already_assigned,
                    'invalid_assets_count', count_invalid,
                    'added_assets', added_list,
                    'already_assigned_assets', already_assigned_list,
                    'invalid_assets', invalid_list,
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
            DROP FUNCTION IF EXISTS add_assets_to_audit_group(
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
