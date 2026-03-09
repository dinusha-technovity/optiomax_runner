<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION update_zombie_report(
            p_id                         BIGINT,
            p_tenant_id                  BIGINT,
            p_user_id                    BIGINT,
            p_reporter_type_id           BIGINT  DEFAULT NULL,
            p_asset_description          TEXT    DEFAULT NULL,
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
            p_requires_action            BOOLEAN DEFAULT NULL,
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

            UPDATE zombie_assets_reports SET
                reporter_type_id          = COALESCE(p_reporter_type_id,         reporter_type_id),
                asset_description         = COALESCE(p_asset_description,        asset_description),
                estimated_category_id     = COALESCE(p_estimated_category_id,    estimated_category_id),
                serial_number             = COALESCE(p_serial_number,            serial_number),
                model_number              = COALESCE(p_model_number,             model_number),
                brand                     = COALESCE(p_brand,                    brand),
                found_location_latitude   = COALESCE(p_found_location_latitude,  found_location_latitude),
                found_location_longitude  = COALESCE(p_found_location_longitude, found_location_longitude),
                location_description      = COALESCE(p_location_description,     location_description),
                area_zone                 = COALESCE(p_area_zone,                area_zone),
                estimated_condition_id    = COALESCE(p_estimated_condition_id,   estimated_condition_id),
                estimated_value_range_id  = COALESCE(p_estimated_value_range_id, estimated_value_range_id),
                photos                    = COALESCE(p_photos,                   photos),
                auditor_notes             = COALESCE(p_auditor_notes,            auditor_notes),
                requires_action           = COALESCE(p_requires_action,          requires_action),
                action_due_date           = COALESCE(p_action_due_date,          action_due_date),
                updated_at                = NOW()
            WHERE id = p_id AND tenant_id = p_tenant_id;

            -- Activity log
            PERFORM log_activity(
                'zombie_assets_reports',
                'updated',
                'App\Models\ZombieAssetsReport',
                p_id,
                'App\Models\User',
                p_user_id,
                jsonb_build_object(
                    'estimated_condition_id',   p_estimated_condition_id,
                    'estimated_value_range_id', p_estimated_value_range_id,
                    'estimated_category_id',    p_estimated_category_id
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
        DB::unprepared('DROP FUNCTION IF EXISTS update_zombie_report(BIGINT, BIGINT, BIGINT, BIGINT, TEXT, BIGINT, TEXT, TEXT, TEXT, TEXT, TEXT, TEXT, TEXT, BIGINT, BIGINT, JSONB, TEXT, BOOLEAN, DATE);');
    }
};
