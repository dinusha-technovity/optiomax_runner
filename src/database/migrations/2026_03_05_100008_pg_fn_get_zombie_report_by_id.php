<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION get_zombie_report_by_id(
            p_id        BIGINT,
            p_tenant_id BIGINT
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
        LANGUAGE sql STABLE AS $$
            SELECT
                zar.id::BIGINT,
                zar.reported_by::BIGINT,
                zar.reporter_type_id::BIGINT,
                zart.name::TEXT,
                zart.label::TEXT,
                zar.asset_description::TEXT,
                zar.estimated_category_id::BIGINT,
                ac.name::TEXT,
                zar.serial_number::TEXT,
                zar.model_number::TEXT,
                zar.brand::TEXT,
                zar.area_zone::TEXT,
                zar.estimated_condition_id::BIGINT,
                zac.name::TEXT,
                zac.label::TEXT,
                zar.estimated_value_range_id::BIGINT,
                zavr.name::TEXT,
                zavr.label::TEXT,
                zar.resolution_status::TEXT,
                zar.requires_action::BOOLEAN,
                zar.action_due_date::DATE,
                zar.photos,
                zar.found_location_latitude::TEXT,
                zar.found_location_longitude::TEXT,
                zar.location_description::TEXT,
                zar.auditor_notes::TEXT,
                zar.resolution_notes::TEXT,
                zar.resolved_at,
                zar.resolved_by::BIGINT,
                zar.matched_asset_item_id::BIGINT,
                zar.created_at,
                zar.updated_at,
                u.name::TEXT,
                u.email::TEXT,
                u.profile_image::TEXT,
                u.contact_no::TEXT,
                u.contact_no_code::TEXT,
                u.designation_id::BIGINT,
                d.designation::TEXT
            FROM zombie_assets_reports zar
            LEFT JOIN zombie_asset_reporter_types  zart ON zart.id  = zar.reporter_type_id
            LEFT JOIN asset_categories             ac   ON ac.id    = zar.estimated_category_id
                                                       AND ac.deleted_at IS NULL
            LEFT JOIN zombie_asset_conditions      zac  ON zac.id   = zar.estimated_condition_id
            LEFT JOIN zombie_asset_value_ranges    zavr ON zavr.id  = zar.estimated_value_range_id
            LEFT JOIN users                        u    ON u.id     = zar.reported_by
                                                       AND u.deleted_at IS NULL
            LEFT JOIN designations                 d    ON d.id     = u.designation_id
                                                       AND d.deleted_at IS NULL
            WHERE zar.id        = p_id
              AND zar.tenant_id = p_tenant_id
              AND zar.deleted_at IS NULL
            LIMIT 1;
        $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_zombie_report_by_id(BIGINT, BIGINT);');
    }
};
