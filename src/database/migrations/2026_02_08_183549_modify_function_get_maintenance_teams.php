<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Create get maintenance teams function with fixed table aliases
     */
    public function up(): void
    {
         DB::unprepared(
        <<<'SQL'
        DO $$
        DECLARE
            r RECORD;
        BEGIN
            -- Drop all existing versions of the function
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'get_maintenance_teams'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;
        CREATE OR REPLACE FUNCTION get_maintenance_teams(
            IN p_tenant_id BIGINT DEFAULT NULL,
            IN p_team_id BIGINT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            id BIGINT,
            team_name TEXT,
            description TEXT,
            created_at TIMESTAMP,
            updated_at TIMESTAMP,
            team_members JSONB,
            asset_groups JSONB,
            team_leader_id BIGINT,
            team_leader_name TEXT,
            team_leader_profile_image TEXT
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_team_record RECORD;
            v_members_json JSONB := '[]'::JSONB;
            v_assets_json JSONB := '[]'::JSONB;
            v_leader_id BIGINT;
            v_leader_name TEXT;
            v_leader_profile_image TEXT;
        BEGIN
            -- Validate tenant ID
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Invalid tenant ID'::TEXT, 
                    NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TIMESTAMP, NULL::TIMESTAMP,
                    NULL::JSONB, NULL::JSONB, NULL::BIGINT, NULL::TEXT, NULL::TEXT;
                RETURN;
            END IF;

            -- Validate team ID if provided
            IF p_team_id IS NOT NULL AND p_team_id <= 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Invalid team ID'::TEXT,
                    NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TIMESTAMP, NULL::TIMESTAMP,
                    NULL::JSONB, NULL::JSONB, NULL::BIGINT, NULL::TEXT, NULL::TEXT;
                RETURN;
            END IF;

            -- Loop through teams
            FOR v_team_record IN 
                SELECT mt.id, mt.team_name, mt.description, mt.created_at, mt.updated_at
                FROM maintenance_teams mt
                WHERE mt.tenant_id = p_tenant_id
                AND mt.deleted_at IS NULL
                AND mt.isactive = TRUE
                AND (p_team_id IS NULL OR mt.id = p_team_id)
                ORDER BY mt.created_at DESC
            LOOP
                -- Get team members for this team
                SELECT COALESCE(
                    jsonb_agg(
                        jsonb_build_object(
                            'id', u.id,
                            'name', u.name,
                            'email', u.email,
                            'profile_image', u.profile_image,
                            'is_team_leader', mtm.is_team_leader
                        )
                    ), '[]'::JSONB
                )
                INTO v_members_json
                FROM maintenance_team_members mtm
                INNER JOIN users u ON u.id = mtm.user_id
                WHERE mtm.team_id = v_team_record.id
                AND mtm.deleted_at IS NULL
                AND mtm.isactive = TRUE;

                -- Get team leader info
                SELECT mtm.user_id, u.name, u.profile_image
                INTO v_leader_id, v_leader_name, v_leader_profile_image
                FROM maintenance_team_members mtm
                INNER JOIN users u ON u.id = mtm.user_id
                WHERE mtm.team_id = v_team_record.id
                AND mtm.is_team_leader = TRUE
                AND mtm.deleted_at IS NULL
                AND mtm.isactive = TRUE
                LIMIT 1;

                -- Get asset groups for this team
                SELECT COALESCE(
                    jsonb_agg(
                        jsonb_build_object(
                            'id', a.id,
                            'name', a.name,
                            'asset_description', a.asset_description,
                            'asset_type', at.name
                        )
                    ), '[]'::JSONB
                )
                INTO v_assets_json
                FROM maintenance_team_related_asset_groups mtag
                INNER JOIN assets a ON a.id = mtag.asset_group_id
                INNER JOIN asset_categories ac ON ac.id = a.category
                INNER JOIN assets_types at ON at.id = ac.assets_type
                WHERE mtag.team_id = v_team_record.id
                AND mtag.deleted_at IS NULL
                AND mtag.isactive = TRUE;

                -- Return the record
                RETURN QUERY SELECT 
                    'SUCCESS'::TEXT,
                    'Maintenance teams retrieved successfully'::TEXT,
                    v_team_record.id,
                    v_team_record.team_name::TEXT,
                    v_team_record.description::TEXT,
                    v_team_record.created_at,
                    v_team_record.updated_at,
                    v_members_json,
                    v_assets_json,
                    v_leader_id,
                    v_leader_name::TEXT,
                    v_leader_profile_image::TEXT;

                -- Reset variables for next iteration
                v_members_json := '[]'::JSONB;
                v_assets_json := '[]'::JSONB;
                v_leader_id := NULL;
                v_leader_name := NULL;
                v_leader_profile_image := NULL;
            END LOOP;

        EXCEPTION
            WHEN OTHERS THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, ('Error: ' || SQLERRM)::TEXT,
                    NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TIMESTAMP, NULL::TIMESTAMP,
                    NULL::JSONB, NULL::JSONB, NULL::BIGINT, NULL::TEXT, NULL::TEXT;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_maintenance_teams(BIGINT, BIGINT)');
    }
};
