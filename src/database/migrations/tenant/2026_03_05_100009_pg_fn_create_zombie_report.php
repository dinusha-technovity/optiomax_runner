<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION create_zombie_report(
            p_tenant_id                  BIGINT,
            p_reported_by                BIGINT,
            p_reporter_type_id           BIGINT,
            p_asset_description          TEXT,
            p_estimated_category_id      BIGINT  DEFAULT NULL,
            p_serial_number              TEXT    DEFAULT NULL,
            p_model_number               TEXT    DEFAULT NULL,
            p_brand                      TEXT    DEFAULT NULL,
            p_found_location_latitude    TEXT    DEFAULT NULL,
            p_found_location_longitude   TEXT    DEFAULT NULL,
            p_location_description       TEXT    DEFAULT NULL,
            p_area_zone                  TEXT    DEFAULT NULL,
            p_estimated_condition_id     BIGINT  DEFAULT NULL,
            p_estimated_value_range_id   BIGINT  DEFAULT NULL,
            p_photos                     JSONB   DEFAULT NULL,
            p_auditor_notes              TEXT    DEFAULT NULL,
            p_requires_action            BOOLEAN DEFAULT TRUE,
            p_action_due_date            DATE    DEFAULT NULL
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
        DECLARE
            v_new_id BIGINT;
        BEGIN
            INSERT INTO zombie_assets_reports (
                tenant_id, reported_by, reporter_type_id, asset_description,
                estimated_category_id, serial_number, model_number, brand,
                found_location_latitude, found_location_longitude, location_description,
                area_zone, estimated_condition_id, estimated_value_range_id,
                photos, auditor_notes, requires_action, action_due_date,
                resolution_status, isactive, created_at, updated_at
            ) VALUES (
                p_tenant_id, p_reported_by, p_reporter_type_id, p_asset_description,
                p_estimated_category_id, p_serial_number, p_model_number, p_brand,
                p_found_location_latitude, p_found_location_longitude, p_location_description,
                p_area_zone, p_estimated_condition_id, p_estimated_value_range_id,
                p_photos, p_auditor_notes, p_requires_action, p_action_due_date,
                'reported', TRUE, NOW(), NOW()
            )
            RETURNING zombie_assets_reports.id INTO v_new_id;

            -- Activity log
            PERFORM log_activity(
                'zombie_assets_reports',
                'created',
                'App\Models\ZombieAssetsReport',
                v_new_id,
                'App\Models\User',
                p_reported_by,
                jsonb_build_object(
                    'asset_description',        p_asset_description,
                    'reporter_type_id',         p_reporter_type_id,
                    'estimated_category_id',    p_estimated_category_id,
                    'estimated_condition_id',   p_estimated_condition_id,
                    'estimated_value_range_id', p_estimated_value_range_id
                ),
                p_tenant_id
            );

            RETURN QUERY SELECT * FROM get_zombie_report_by_id(v_new_id, p_tenant_id);
        END;
        $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS create_zombie_report(BIGINT, BIGINT, BIGINT, TEXT, BIGINT, TEXT, TEXT, TEXT, TEXT, TEXT, TEXT, TEXT, BIGINT, BIGINT, JSONB, TEXT, BOOLEAN, DATE);');
    }
};
