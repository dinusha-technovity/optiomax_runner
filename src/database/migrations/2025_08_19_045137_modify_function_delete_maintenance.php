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
        DROP FUNCTION IF EXISTS delete_maintenance_team(BIGINT, BIGINT, BIGINT);
        DROP FUNCTION IF EXISTS delete_maintenance_team(BIGINT, BIGINT);

        CREATE OR REPLACE FUNCTION delete_maintenance_team(
            IN p_team_id BIGINT,
            IN p_tenant_id BIGINT,
            IN p_action_by BIGINT
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT
        )
        LANGUAGE plpgsql
        AS $$
        BEGIN
            -- Validate required fields
            IF p_team_id IS NULL OR p_team_id <= 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Invalid team ID'::TEXT;
                RETURN;
            END IF;

            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Invalid tenant ID'::TEXT;
                RETURN;
            END IF;

            -- Check if team exists and belongs to tenant
            IF NOT EXISTS (
                SELECT 1 FROM maintenance_teams 
                WHERE id = p_team_id 
                AND tenant_id = p_tenant_id 
                AND deleted_at IS NULL
            ) THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Team not found or access denied'::TEXT;
                RETURN;
            END IF;

            -- Soft delete team members
            UPDATE maintenance_team_members 
            SET 
                deleted_at = NOW(),
                isactive = FALSE,
                updated_at = NOW()
            WHERE team_id = p_team_id AND deleted_at IS NULL;

            -- Soft delete asset group assignments
            UPDATE maintenance_team_related_asset_groups 
            SET 
                deleted_at = NOW(),
                isactive = FALSE,
                updated_at = NOW()
            WHERE team_id = p_team_id AND deleted_at IS NULL;

            -- Soft delete main team record
            UPDATE maintenance_teams 
            SET 
                deleted_at = NOW(),
                isactive = FALSE,
                updated_at = NOW()
            WHERE id = p_team_id AND tenant_id = p_tenant_id;

            RETURN QUERY SELECT 'SUCCESS'::TEXT, 'Maintenance team deleted successfully'::TEXT;

        EXCEPTION
            WHEN OTHERS THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, ('Error: ' || SQLERRM)::TEXT;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS delete_maintenance_team(BIGINT, BIGINT)');
    }
};
