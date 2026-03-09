<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION delete_zombie_report(
            p_id        BIGINT,
            p_user_id   BIGINT,
            p_tenant_id BIGINT
        )
        RETURNS INT
        LANGUAGE plpgsql AS $$
        DECLARE
            v_row zombie_assets_reports%ROWTYPE;
        BEGIN
            SELECT * INTO v_row
            FROM zombie_assets_reports
            WHERE id = p_id AND tenant_id = p_tenant_id AND deleted_at IS NULL;

            IF NOT FOUND THEN
                RAISE EXCEPTION 'NOT_FOUND: Report not found.';
            END IF;

            IF v_row.resolution_status::TEXT <> 'reported' OR v_row.reported_by <> p_user_id THEN
                RAISE EXCEPTION 'FORBIDDEN: Only the original reporter can edit/delete a report in "reported" status.';
            END IF;

            UPDATE zombie_assets_reports
            SET deleted_at = NOW(), updated_at = NOW()
            WHERE id = p_id AND tenant_id = p_tenant_id;

            -- Activity log
            PERFORM log_activity(
                'zombie_assets_reports',
                'deleted',
                'App\Models\ZombieAssetsReport',
                p_id,
                'App\Models\User',
                p_user_id,
                NULL::JSONB,
                p_tenant_id
            );

            RETURN 1;
        END;
        $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS delete_zombie_report(BIGINT, BIGINT, BIGINT);');
    }
};
