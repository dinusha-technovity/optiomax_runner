<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION update_zombie_report_resolution(
            p_id                    BIGINT,
            p_tenant_id             BIGINT,
            p_resolver_id           BIGINT,
            p_resolution_status     TEXT,
            p_resolution_notes      TEXT    DEFAULT NULL,
            p_matched_asset_item_id BIGINT  DEFAULT NULL
        )
        RETURNS TABLE (
            id                           BIGINT,
            reported_by                  BIGINT,
            reporter_type_id             BIGINT,
            reporter_type_name           TEXT,
            reporter_type_label          TEXT,
            asset_description            TEXT,
            estimated_category_id        BIGINT,
            estimated_category_name      TEXT,
            serial_number                TEXT,
            model_number                 TEXT,
            brand                        TEXT,
            area_zone                    TEXT,
            estimated_condition_id       BIGINT,
            estimated_condition_name     TEXT,
            estimated_condition_label    TEXT,
            estimated_value_range_id     BIGINT,
            estimated_value_range_name   TEXT,
            estimated_value_range_label  TEXT,
            resolution_status            TEXT,
            requires_action              BOOLEAN,
            action_due_date              DATE,
            photos                       JSONB,
            found_location_latitude      TEXT,
            found_location_longitude     TEXT,
            location_description         TEXT,
            auditor_notes                TEXT,
            resolution_notes             TEXT,
            resolved_at                  TIMESTAMP,
            resolved_by                  BIGINT,
            matched_asset_item_id        BIGINT,
            created_at                   TIMESTAMP,
            updated_at                   TIMESTAMP,
            reporter_name                TEXT,
            reporter_email               TEXT,
            reporter_profile_image       TEXT,
            reporter_contact_no          TEXT,
            reporter_contact_no_code     TEXT,
            reporter_designation_id      BIGINT,
            reporter_designation_name    TEXT
        )
        LANGUAGE plpgsql AS $$
        BEGIN
            IF NOT EXISTS (
                SELECT 1 FROM zombie_assets_reports
                WHERE id = p_id AND tenant_id = p_tenant_id AND deleted_at IS NULL
            ) THEN
                RAISE EXCEPTION 'NOT_FOUND: Report not found.';
            END IF;

            UPDATE zombie_assets_reports SET
                resolution_status     = p_resolution_status,
                resolution_notes      = COALESCE(p_resolution_notes,      resolution_notes),
                matched_asset_item_id = COALESCE(p_matched_asset_item_id, matched_asset_item_id),
                resolved_by           = p_resolver_id,
                resolved_at           = NOW(),
                updated_at            = NOW()
            WHERE id = p_id AND tenant_id = p_tenant_id;

            -- Activity log
            PERFORM log_activity(
                'zombie_assets_reports',
                'resolution_updated',
                'App\Models\ZombieAssetsReport',
                p_id,
                'App\Models\User',
                p_resolver_id,
                jsonb_build_object(
                    'resolution_status',     p_resolution_status,
                    'resolution_notes',      p_resolution_notes,
                    'matched_asset_item_id', p_matched_asset_item_id
                ),
                p_tenant_id
            );

            RETURN QUERY SELECT * FROM get_zombie_report_by_id(p_id, p_tenant_id);
        END;
        $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS update_zombie_report_resolution(BIGINT, BIGINT, BIGINT, TEXT, TEXT, BIGINT);');
    }
};
