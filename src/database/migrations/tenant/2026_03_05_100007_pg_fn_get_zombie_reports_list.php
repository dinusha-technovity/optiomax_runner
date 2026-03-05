<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION get_zombie_reports_list(
            p_tenant_id          BIGINT,
            p_user_id            BIGINT  DEFAULT NULL,
            p_page               INT     DEFAULT 1,
            p_per_page           INT     DEFAULT 10,
            p_search             TEXT    DEFAULT NULL,
            p_resolution_status  TEXT    DEFAULT NULL,
            p_reporter_type_id   BIGINT  DEFAULT NULL,
            p_category_id        BIGINT  DEFAULT NULL,
            p_sort_by            TEXT    DEFAULT 'created_at',
            p_sort_order         TEXT    DEFAULT 'desc'
        )
        RETURNS TABLE (
            total_count                  BIGINT,
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
        LANGUAGE plpgsql STABLE AS $$
        DECLARE
            v_sort_col  TEXT;
            v_sort_dir  TEXT;
            v_offset    INT;
            v_sql       TEXT;
        BEGIN
            -- Whitelist sort column to prevent injection
            v_sort_col := CASE p_sort_by
                WHEN 'asset_description' THEN 'zar.asset_description'
                WHEN 'resolution_status' THEN 'zar.resolution_status'
                WHEN 'action_due_date'   THEN 'zar.action_due_date'
                WHEN 'area_zone'         THEN 'zar.area_zone'
                ELSE 'zar.created_at'
            END;

            v_sort_dir := CASE WHEN lower(p_sort_order) = 'asc' THEN 'ASC' ELSE 'DESC' END;
            v_offset   := (GREATEST(p_page, 1) - 1) * GREATEST(p_per_page, 1);

            v_sql := '
                SELECT
                    COUNT(*) OVER()::BIGINT                          AS total_count,
                    zar.id::BIGINT,
                    zar.reported_by::BIGINT,
                    zar.reporter_type_id::BIGINT,
                    zart.name::TEXT                                  AS reporter_type_name,
                    zart.label::TEXT                                 AS reporter_type_label,
                    zar.asset_description::TEXT,
                    zar.estimated_category_id::BIGINT,
                    ac.name::TEXT                                    AS estimated_category_name,
                    zar.serial_number::TEXT,
                    zar.model_number::TEXT,
                    zar.brand::TEXT,
                    zar.area_zone::TEXT,
                    zar.estimated_condition_id::BIGINT,
                    zac.name::TEXT                                   AS estimated_condition_name,
                    zac.label::TEXT                                  AS estimated_condition_label,
                    zar.estimated_value_range_id::BIGINT,
                    zavr.name::TEXT                                  AS estimated_value_range_name,
                    zavr.label::TEXT                                 AS estimated_value_range_label,
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
                    u.name::TEXT                                     AS reporter_name,
                    u.email::TEXT                                    AS reporter_email,
                    u.profile_image::TEXT                            AS reporter_profile_image,
                    u.contact_no::TEXT                               AS reporter_contact_no,
                    u.contact_no_code::TEXT                          AS reporter_contact_no_code,
                    u.designation_id::BIGINT                         AS reporter_designation_id,
                    d.designation::TEXT                              AS reporter_designation_name
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
                WHERE zar.tenant_id  = $1
                  AND zar.deleted_at IS NULL
                  AND ($2 IS NULL OR zar.reported_by            = $2)
                  AND ($3 IS NULL OR (
                         zar.asset_description ILIKE ''%'' || $3 || ''%''
                      OR COALESCE(zar.serial_number,  '''')::TEXT ILIKE ''%'' || $3 || ''%''
                      OR COALESCE(zar.model_number,   '''')::TEXT ILIKE ''%'' || $3 || ''%''
                      OR COALESCE(zar.brand,          '''')::TEXT ILIKE ''%'' || $3 || ''%''
                      OR COALESCE(zar.area_zone,      '''')::TEXT ILIKE ''%'' || $3 || ''%''
                  ))
                  AND ($4 IS NULL OR zar.resolution_status::TEXT = $4)
                  AND ($5 IS NULL OR zar.reporter_type_id        = $5)
                  AND ($6 IS NULL OR zar.estimated_category_id   = $6)
                ORDER BY ' || v_sort_col || ' ' || v_sort_dir || '
                OFFSET $7
                LIMIT  $8
            ';

            RETURN QUERY EXECUTE v_sql
            USING
                p_tenant_id,
                p_user_id,
                p_search,
                p_resolution_status,
                p_reporter_type_id,
                p_category_id,
                v_offset,
                p_per_page;
        END;
        $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_zombie_reports_list(BIGINT, BIGINT, INT, INT, TEXT, TEXT, BIGINT, BIGINT, TEXT, TEXT);');
    }
};
