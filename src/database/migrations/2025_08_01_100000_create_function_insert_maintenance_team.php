<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Create upsert maintenance team functions (insert + update)
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
        -- Create the insert function (for new teams)
        CREATE OR REPLACE FUNCTION upsert_maintenance_team(
            IN p_team_name TEXT,
            IN p_description TEXT,
            IN p_team_members JSONB,
            IN p_team_leader_id BIGINT,
            IN p_asset_groups JSONB,
            IN p_tenant_id BIGINT
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            team_id BIGINT
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_team_id BIGINT;
            v_member JSONB;
            v_asset_group JSONB;
            v_member_id BIGINT;
            v_asset_group_id BIGINT;
            v_is_leader BOOLEAN;
        BEGIN
            -- Validations
            IF p_team_name IS NULL OR LENGTH(TRIM(p_team_name)) = 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Team name is required'::TEXT, NULL::BIGINT;
                RETURN;
            END IF;

            IF p_description IS NULL OR LENGTH(TRIM(p_description)) = 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Description is required'::TEXT, NULL::BIGINT;
                RETURN;
            END IF;

            IF p_team_leader_id IS NULL OR p_team_leader_id <= 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Team leader is required'::TEXT, NULL::BIGINT;
                RETURN;
            END IF;

            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Invalid tenant ID'::TEXT, NULL::BIGINT;
                RETURN;
            END IF;

            IF p_team_members IS NULL OR jsonb_array_length(p_team_members) = 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'At least one team member is required'::TEXT, NULL::BIGINT;
                RETURN;
            END IF;

            IF p_asset_groups IS NULL OR jsonb_array_length(p_asset_groups) = 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'At least one asset group is required'::TEXT, NULL::BIGINT;
                RETURN;
            END IF;

            IF EXISTS (
                SELECT 1 FROM maintenance_teams 
                WHERE team_name = p_team_name 
                AND tenant_id = p_tenant_id 
                AND deleted_at IS NULL
            ) THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Team name already exists'::TEXT, NULL::BIGINT;
                RETURN;
            END IF;

            -- Insert team
            INSERT INTO maintenance_teams (
                team_name, description, tenant_id, created_at, updated_at
            ) VALUES (
                p_team_name, p_description, p_tenant_id, NOW(), NOW()
            )
            RETURNING id INTO v_team_id;

            -- Team members
            FOR v_member IN SELECT * FROM jsonb_array_elements(p_team_members)
            LOOP
                v_member_id := (v_member->>'id')::BIGINT;
                v_is_leader := (v_member_id = p_team_leader_id);

                INSERT INTO maintenance_team_members (
                    user_id, is_team_leader, team_id, tenant_id, created_at, updated_at
                ) VALUES (
                    v_member_id, v_is_leader, v_team_id, p_tenant_id, NOW(), NOW()
                );
            END LOOP;

            -- Asset groups
            FOR v_asset_group IN SELECT * FROM jsonb_array_elements(p_asset_groups)
            LOOP
                v_asset_group_id := (v_asset_group->>'id')::BIGINT;

                INSERT INTO maintenance_team_related_asset_groups (
                    asset_group_id, team_id, tenant_id, created_at, updated_at
                ) VALUES (
                    v_asset_group_id, v_team_id, p_tenant_id, NOW(), NOW()
                );
            END LOOP;

            RETURN QUERY SELECT 'SUCCESS'::TEXT, 'Maintenance team created successfully'::TEXT, v_team_id;

        EXCEPTION WHEN OTHERS THEN
            RETURN QUERY SELECT 'FAILURE'::TEXT, ('Error: ' || SQLERRM)::TEXT, NULL::BIGINT;
        END;
        $$;


        -- Create the update function (for existing teams)
        CREATE OR REPLACE FUNCTION upsert_maintenance_team(
            IN p_team_id BIGINT,
            IN p_team_name TEXT,
            IN p_description TEXT,
            IN p_team_members JSONB,
            IN p_team_leader_id BIGINT,
            IN p_asset_groups JSONB,
            IN p_tenant_id BIGINT
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            team_id BIGINT
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_member JSONB;
            v_asset_group JSONB;
            v_member_id BIGINT;
            v_asset_group_id BIGINT;
            v_is_leader BOOLEAN;
        BEGIN
            -- Validations
            IF p_team_id IS NULL OR p_team_id <= 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Invalid team ID'::TEXT, NULL::BIGINT;
                RETURN;
            END IF;

            IF p_team_name IS NULL OR LENGTH(TRIM(p_team_name)) = 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Team name is required'::TEXT, NULL::BIGINT;
                RETURN;
            END IF;

            IF p_description IS NULL OR LENGTH(TRIM(p_description)) = 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Description is required'::TEXT, NULL::BIGINT;
                RETURN;
            END IF;

            IF p_team_leader_id IS NULL OR p_team_leader_id <= 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Team leader is required'::TEXT, NULL::BIGINT;
                RETURN;
            END IF;

            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Invalid tenant ID'::TEXT, NULL::BIGINT;
                RETURN;
            END IF;

            IF p_team_members IS NULL OR jsonb_array_length(p_team_members) = 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'At least one team member is required'::TEXT, NULL::BIGINT;
                RETURN;
            END IF;

            IF p_asset_groups IS NULL OR jsonb_array_length(p_asset_groups) = 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'At least one asset group is required'::TEXT, NULL::BIGINT;
                RETURN;
            END IF;

            IF NOT EXISTS (
                SELECT 1 FROM maintenance_teams 
                WHERE id = p_team_id AND tenant_id = p_tenant_id AND deleted_at IS NULL
            ) THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Team not found or access denied'::TEXT, NULL::BIGINT;
                RETURN;
            END IF;

            IF EXISTS (
                SELECT 1 FROM maintenance_teams 
                WHERE team_name = p_team_name AND tenant_id = p_tenant_id 
                AND id != p_team_id AND deleted_at IS NULL
            ) THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Team name already exists'::TEXT, NULL::BIGINT;
                RETURN;
            END IF;

            -- Update main record
            UPDATE maintenance_teams 
            SET 
                team_name = p_team_name,
                description = p_description,
                updated_at = NOW()
            WHERE id = p_team_id AND tenant_id = p_tenant_id;

            -- Soft delete existing members and asset groups
            UPDATE maintenance_team_members 
            SET deleted_at = NOW(), isactive = FALSE, updated_at = NOW()
            WHERE maintenance_team_members.team_id = p_team_id AND maintenance_team_members.deleted_at IS NULL;

            UPDATE maintenance_team_related_asset_groups 
            SET deleted_at = NOW(), isactive = FALSE, updated_at = NOW()
            WHERE maintenance_team_related_asset_groups.team_id = p_team_id AND maintenance_team_related_asset_groups.deleted_at IS NULL;

            -- Insert updated team members
            FOR v_member IN SELECT * FROM jsonb_array_elements(p_team_members)
            LOOP
                v_member_id := (v_member->>'id')::BIGINT;
                v_is_leader := (v_member_id = p_team_leader_id);

                INSERT INTO maintenance_team_members (
                    user_id, is_team_leader, team_id, tenant_id, created_at, updated_at
                ) VALUES (
                    v_member_id, v_is_leader, p_team_id, p_tenant_id, NOW(), NOW()
                );
            END LOOP;

            -- Insert updated asset groups
            FOR v_asset_group IN SELECT * FROM jsonb_array_elements(p_asset_groups)
            LOOP
                v_asset_group_id := (v_asset_group->>'id')::BIGINT;

                INSERT INTO maintenance_team_related_asset_groups (
                    asset_group_id, team_id, tenant_id, created_at, updated_at
                ) VALUES (
                    v_asset_group_id, p_team_id, p_tenant_id, NOW(), NOW()
                );
            END LOOP;

            RETURN QUERY SELECT 'SUCCESS'::TEXT, 'Maintenance team updated successfully'::TEXT, p_team_id;

        EXCEPTION WHEN OTHERS THEN
            RETURN QUERY SELECT 'FAILURE'::TEXT, ('Error: ' || SQLERRM)::TEXT, NULL::BIGINT;
        END;
        $$;
        SQL);
    }

    /**
     * Drop both overloaded versions of upsert_maintenance_team
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS upsert_maintenance_team(TEXT, TEXT, JSONB, BIGINT, JSONB, BIGINT)');
        DB::unprepared('DROP FUNCTION IF EXISTS upsert_maintenance_team(BIGINT, TEXT, TEXT, JSONB, BIGINT, JSONB, BIGINT)');
    }
};
