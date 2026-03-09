<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Create get_auditor_assigned_assets PostgreSQL function.
     *
     * Returns all asset items assigned to a given auditor through their audit sessions,
     * with full asset, audit session, audit period, and audit item session details.
     *
     * Includes:
     *   - responsible person profile (name, email, image, designation, contact)
     *   - period leader profile
     *   - audit_by user profile (name, email, image, designation, contact)
     *   - assigned groups and other auditors as JSONB
     *   - stable ORDER BY tiebreaker (audit_session_id ASC, asset_item_id ASC)
     *   - optional filters: asset_item_id, audit_session_id, search, session_status,
     *     audit_period_id
     *   - pagination with total_count
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        DO $$
        DECLARE
            r RECORD;
        BEGIN
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'get_auditor_assigned_assets'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_auditor_assigned_assets(
            p_user_id                BIGINT,
            p_tenant_id              BIGINT,
            p_page                   INT     DEFAULT 1,
            p_per_page               INT     DEFAULT 10,
            p_search                 TEXT    DEFAULT NULL,
            p_session_status         TEXT    DEFAULT NULL,
            p_audit_period_id        BIGINT  DEFAULT NULL,
            p_sort_by                TEXT    DEFAULT 'created_at',
            p_sort_order             TEXT    DEFAULT 'desc',
            p_filter_asset_item_id   BIGINT  DEFAULT NULL,
            p_filter_audit_session_id BIGINT DEFAULT NULL
        )
        RETURNS TABLE(
            asset_item_id                           BIGINT,
            asset_id                                BIGINT,
            asset_name                              TEXT,
            model_number                            TEXT,
            serial_number                           TEXT,
            asset_tag                               TEXT,
            qr_code                                 TEXT,
            thumbnail_image                         JSONB,
            item_value                              NUMERIC,
            assets_type_id                          BIGINT,
            assets_type_name                        TEXT,
            category_id                             BIGINT,
            category_name                           TEXT,
            sub_category_id                         BIGINT,
            sub_category_name                       TEXT,
            responsible_person_id                   BIGINT,
            responsible_person_name                 TEXT,
            responsible_person_email                TEXT,
            responsible_person_profile_image        TEXT,
            responsible_person_designation_id       BIGINT,
            responsible_person_designation_name     TEXT,
            responsible_person_contact_no           TEXT,
            responsible_person_contact_no_code      TEXT,
            department_id                           BIGINT,
            department_name                         TEXT,
            asset_location_latitude                 TEXT,
            asset_location_longitude                TEXT,
            asset_isactive                          BOOLEAN,
            asset_item_created_at                   TIMESTAMP,
            audit_session_id                        BIGINT,
            session_code                            TEXT,
            session_name                            TEXT,
            session_description                     TEXT,
            scheduled_date                          DATE,
            actual_start_date                       DATE,
            actual_end_date                         DATE,
            session_status                          TEXT,
            lead_auditor_id                         BIGINT,
            lead_auditor_name                       TEXT,
            session_created_at                      TIMESTAMP,
            audit_period_id                         BIGINT,
            period_name                             TEXT,
            period_description                      TEXT,
            period_start_date                       DATE,
            period_end_date                         DATE,
            period_status                           TEXT,
            period_leader_id                        BIGINT,
            period_leader_name                      TEXT,
            period_leader_email                     TEXT,
            period_leader_profile_image             TEXT,
            period_leader_designation_id            BIGINT,
            period_leader_designation_name          TEXT,
            period_leader_contact_no                TEXT,
            period_leader_contact_no_code           TEXT,
            assigned_groups                         JSONB,
            other_auditors                          JSONB,
            my_role                                 TEXT,
            my_assignment_date                      TIMESTAMP,
            audit_item_session_id                   BIGINT,
            audit_item_status                       TEXT,
            audit_item_audit_period_id              BIGINT,
            audit_item_asset_available              BOOLEAN,
            audit_item_availability_notes           TEXT,
            audit_item_availability_checked_at      TIMESTAMP,
            audit_item_document                     TEXT,
            audit_item_audit_by                     BIGINT,
            audit_item_audit_by_name                TEXT,
            audit_item_audit_by_email               TEXT,
            audit_item_audit_by_profile_image       TEXT,
            audit_item_audit_by_designation_id      BIGINT,
            audit_item_audit_by_designation_name    TEXT,
            audit_item_audit_by_contact_no          TEXT,
            audit_item_audit_by_contact_no_code     TEXT,
            audit_item_auditing_latitude            TEXT,
            audit_item_auditing_longitude           TEXT,
            audit_item_location_description         TEXT,
            audit_item_remarks                      TEXT,
            audit_item_follow_up_required           BOOLEAN,
            audit_item_follow_up_notes              TEXT,
            audit_item_follow_up_due_date           DATE,
            audit_item_audit_started_at             TIMESTAMP,
            audit_item_audit_completed_at           TIMESTAMP,
            audit_item_audit_duration_minutes       INT,
            audit_item_approved_by                  BIGINT,
            audit_item_approved_at                  TIMESTAMP,
            audit_item_approval_notes               TEXT,
            audit_item_isactive                     BOOLEAN,
            audit_item_session_created_at           TIMESTAMP,
            audit_item_session_updated_at           TIMESTAMP,
            total_count                             BIGINT
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_offset      INT;
            v_total_count BIGINT;
        BEGIN
            v_offset := (p_page - 1) * p_per_page;

            SELECT COUNT(DISTINCT (asess.id, ai.id))
            INTO v_total_count
            FROM audit_sessions_auditors asa
            INNER JOIN audit_sessions asess ON asa.audit_session_id = asess.id
            INNER JOIN audit_periods   ap   ON asess.audit_period_id = ap.id
            INNER JOIN audit_sessions_groups asg ON asess.id = asg.audit_session_id
            INNER JOIN audit_groups    ag   ON asg.audit_group_id = ag.id
            INNER JOIN audit_groups_releated_assets agra ON ag.id = agra.audit_group_id
            INNER JOIN asset_items     ai   ON agra.asset_id = ai.id
            INNER JOIN assets          a    ON ai.asset_id   = a.id
            WHERE asa.user_id      = p_user_id
              AND asa.tenant_id    = p_tenant_id
              AND asa.deleted_at   IS NULL
              AND asess.deleted_at IS NULL
              AND asess.tenant_id  = p_tenant_id
              AND asg.deleted_at   IS NULL
              AND agra.deleted_at  IS NULL
              AND ai.deleted_at    IS NULL
              AND ai.tenant_id     = p_tenant_id
              AND a.deleted_at     IS NULL
              AND (p_search IS NULL OR
                   COALESCE(a.name,            '') ILIKE '%' || p_search || '%' OR
                   COALESCE(ai.model_number,   '') ILIKE '%' || p_search || '%' OR
                   COALESCE(ai.serial_number,  '') ILIKE '%' || p_search || '%' OR
                   COALESCE(ai.asset_tag,      '') ILIKE '%' || p_search || '%' OR
                   COALESCE(asess.session_name,'') ILIKE '%' || p_search || '%' OR
                   COALESCE(asess.session_code,'') ILIKE '%' || p_search || '%')
              AND (p_session_status   IS NULL OR asess.status          = p_session_status)
              AND (p_audit_period_id  IS NULL OR asess.audit_period_id = p_audit_period_id)
              AND (p_filter_asset_item_id    IS NULL OR ai.id    = p_filter_asset_item_id)
              AND (p_filter_audit_session_id IS NULL OR asess.id = p_filter_audit_session_id);

            RETURN QUERY
            WITH distinct_assets AS (
                SELECT DISTINCT ON (asess.id, ai.id)
                    ai.id                             AS asset_item_id,
                    ai.asset_id,
                    ai.model_number,
                    ai.serial_number,
                    ai.asset_tag,
                    ai.qr_code::TEXT                  AS qr_code,
                    ai.thumbnail_image,
                    ai.item_value,
                    ai.responsible_person             AS responsible_person_id,
                    ai.department                     AS department_id,
                    ai.asset_location_latitude,
                    ai.asset_location_longitude,
                    ai.isactive                       AS asset_isactive,
                    ai.created_at                     AS asset_item_created_at,
                    asess.id                          AS audit_session_id,
                    asess.session_code,
                    asess.session_name,
                    asess.description                 AS session_description,
                    asess.scheduled_date,
                    asess.actual_start_date,
                    asess.actual_end_date,
                    asess.status                      AS session_status,
                    asess.lead_auditor_id,
                    asess.created_at                  AS session_created_at,
                    ap.id                             AS audit_period_id,
                    ap.period_name,
                    ap.description                    AS period_description,
                    ap.start_date                     AS period_start_date,
                    ap.end_date                       AS period_end_date,
                    ap.status                         AS period_status,
                    ap.period_leader_id,
                    asa.role                          AS my_role,
                    asa.assigned_at                   AS my_assignment_date
                FROM audit_sessions_auditors asa
                INNER JOIN audit_sessions asess ON asa.audit_session_id = asess.id
                INNER JOIN audit_periods   ap   ON asess.audit_period_id = ap.id
                INNER JOIN audit_sessions_groups asg ON asess.id = asg.audit_session_id
                INNER JOIN audit_groups    ag   ON asg.audit_group_id = ag.id
                INNER JOIN audit_groups_releated_assets agra ON ag.id = agra.audit_group_id
                INNER JOIN asset_items     ai   ON agra.asset_id = ai.id
                INNER JOIN assets          a    ON ai.asset_id   = a.id
                WHERE asa.user_id      = p_user_id
                  AND asa.tenant_id    = p_tenant_id
                  AND asa.deleted_at   IS NULL
                  AND asess.deleted_at IS NULL
                  AND asess.tenant_id  = p_tenant_id
                  AND asg.deleted_at   IS NULL
                  AND agra.deleted_at  IS NULL
                  AND ai.deleted_at    IS NULL
                  AND ai.tenant_id     = p_tenant_id
                  AND a.deleted_at     IS NULL
                  AND (p_search IS NULL OR
                       COALESCE(a.name,            '') ILIKE '%' || p_search || '%' OR
                       COALESCE(ai.model_number,   '') ILIKE '%' || p_search || '%' OR
                       COALESCE(ai.serial_number,  '') ILIKE '%' || p_search || '%' OR
                       COALESCE(ai.asset_tag,      '') ILIKE '%' || p_search || '%' OR
                       COALESCE(asess.session_name,'') ILIKE '%' || p_search || '%' OR
                       COALESCE(asess.session_code,'') ILIKE '%' || p_search || '%')
                  AND (p_session_status   IS NULL OR asess.status          = p_session_status)
                  AND (p_audit_period_id  IS NULL OR asess.audit_period_id = p_audit_period_id)
                  AND (p_filter_asset_item_id    IS NULL OR ai.id    = p_filter_asset_item_id)
                  AND (p_filter_audit_session_id IS NULL OR asess.id = p_filter_audit_session_id)
            )
            SELECT
                da.asset_item_id,
                da.asset_id,
                COALESCE(a.name,           '')::TEXT  AS asset_name,
                COALESCE(da.model_number,  '')::TEXT  AS model_number,
                COALESCE(da.serial_number, '')::TEXT  AS serial_number,
                COALESCE(da.asset_tag,     '')::TEXT  AS asset_tag,
                COALESCE(da.qr_code,       '')::TEXT  AS qr_code,
                da.thumbnail_image,
                da.item_value,
                ac.assets_type                         AS assets_type_id,
                COALESCE(ast.name,   '')::TEXT         AS assets_type_name,
                a.category                             AS category_id,
                COALESCE(ac.name,    '')::TEXT         AS category_name,
                a.sub_category                         AS sub_category_id,
                COALESCE(assc.name,  '')::TEXT         AS sub_category_name,
                da.responsible_person_id,
                COALESCE(u_resp.name,          '')::TEXT  AS responsible_person_name,
                COALESCE(u_resp.email,         '')::TEXT  AS responsible_person_email,
                COALESCE(u_resp.profile_image, '')::TEXT  AS responsible_person_profile_image,
                u_resp.designation_id                      AS responsible_person_designation_id,
                COALESCE(d_resp.designation,   '')::TEXT  AS responsible_person_designation_name,
                COALESCE(u_resp.contact_no,    '')::TEXT  AS responsible_person_contact_no,
                COALESCE(u_resp.contact_no_code::TEXT, '') AS responsible_person_contact_no_code,
                da.department_id,
                COALESCE(org.data->>'organizationName', '')::TEXT AS department_name,
                COALESCE(da.asset_location_latitude,  '')::TEXT  AS asset_location_latitude,
                COALESCE(da.asset_location_longitude, '')::TEXT  AS asset_location_longitude,
                da.asset_isactive,
                da.asset_item_created_at,
                da.audit_session_id,
                da.session_code::TEXT,
                da.session_name::TEXT,
                COALESCE(da.session_description, '')::TEXT AS session_description,
                da.scheduled_date,
                da.actual_start_date,
                da.actual_end_date,
                da.session_status::TEXT,
                da.lead_auditor_id,
                COALESCE(u_lead.name, '')::TEXT        AS lead_auditor_name,
                da.session_created_at,
                da.audit_period_id,
                da.period_name::TEXT,
                COALESCE(da.period_description, '')::TEXT AS period_description,
                da.period_start_date,
                da.period_end_date,
                da.period_status::TEXT,
                da.period_leader_id,
                COALESCE(u_pled.name,          '')::TEXT  AS period_leader_name,
                COALESCE(u_pled.email,         '')::TEXT  AS period_leader_email,
                COALESCE(u_pled.profile_image, '')::TEXT  AS period_leader_profile_image,
                u_pled.designation_id                      AS period_leader_designation_id,
                COALESCE(d_pled.designation,   '')::TEXT  AS period_leader_designation_name,
                COALESCE(u_pled.contact_no,    '')::TEXT  AS period_leader_contact_no,
                COALESCE(u_pled.contact_no_code::TEXT, '') AS period_leader_contact_no_code,
                (
                    SELECT JSONB_AGG(
                        JSONB_BUILD_OBJECT(
                            'group_id',          grp.id,
                            'group_name',        grp.name,
                            'group_description', grp.description,
                            'assigned_at',       grp_asg.assigned_at,
                            'audit_status',      grp_asg.audit_status
                        )
                        ORDER BY grp_asg.id
                    )
                    FROM audit_sessions_groups grp_asg
                    INNER JOIN audit_groups grp ON grp_asg.audit_group_id = grp.id
                    WHERE grp_asg.audit_session_id = da.audit_session_id
                      AND grp_asg.deleted_at       IS NULL
                      AND grp.deleted_at           IS NULL
                ) AS assigned_groups,
                (
                    SELECT JSONB_AGG(
                        JSONB_BUILD_OBJECT(
                            'user_id',      osa.user_id,
                            'user_name',    ou.name,
                            'email',        ou.email,
                            'role',         osa.role,
                            'assigned_at',  osa.assigned_at,
                            'competencies', osa.competencies
                        )
                        ORDER BY osa.id
                    )
                    FROM audit_sessions_auditors osa
                    INNER JOIN users ou ON osa.user_id = ou.id
                    WHERE osa.audit_session_id = da.audit_session_id
                      AND osa.deleted_at       IS NULL
                      AND osa.user_id         != p_user_id
                ) AS other_auditors,
                da.my_role::TEXT,
                da.my_assignment_date,
                aias.id                                       AS audit_item_session_id,
                COALESCE(aias.audit_status, 'pending')::TEXT  AS audit_item_status,
                aias.audit_period_id                          AS audit_item_audit_period_id,
                aias.asset_available                          AS audit_item_asset_available,
                aias.availability_notes::TEXT                 AS audit_item_availability_notes,
                aias.availability_checked_at                  AS audit_item_availability_checked_at,
                aias.document::TEXT                           AS audit_item_document,
                aias.audit_by                                 AS audit_item_audit_by,
                COALESCE(u_audit_by.name,           '')::TEXT  AS audit_item_audit_by_name,
                COALESCE(u_audit_by.email,          '')::TEXT  AS audit_item_audit_by_email,
                COALESCE(u_audit_by.profile_image,  '')::TEXT  AS audit_item_audit_by_profile_image,
                u_audit_by.designation_id                      AS audit_item_audit_by_designation_id,
                COALESCE(d_audit_by.designation,    '')::TEXT  AS audit_item_audit_by_designation_name,
                COALESCE(u_audit_by.contact_no,     '')::TEXT  AS audit_item_audit_by_contact_no,
                COALESCE(u_audit_by.contact_no_code::TEXT, '') AS audit_item_audit_by_contact_no_code,
                aias.auditing_location_latitude::TEXT         AS audit_item_auditing_latitude,
                aias.auditing_location_longitude::TEXT        AS audit_item_auditing_longitude,
                aias.location_description::TEXT               AS audit_item_location_description,
                aias.remarks::TEXT                            AS audit_item_remarks,
                aias.follow_up_required                       AS audit_item_follow_up_required,
                aias.follow_up_notes::TEXT                    AS audit_item_follow_up_notes,
                aias.follow_up_due_date                       AS audit_item_follow_up_due_date,
                aias.audit_started_at                         AS audit_item_audit_started_at,
                aias.audit_completed_at                       AS audit_item_audit_completed_at,
                aias.audit_duration_minutes                   AS audit_item_audit_duration_minutes,
                aias.approved_by                              AS audit_item_approved_by,
                aias.approved_at                              AS audit_item_approved_at,
                aias.approval_notes::TEXT                     AS audit_item_approval_notes,
                aias.isactive                                 AS audit_item_isactive,
                aias.created_at                               AS audit_item_session_created_at,
                aias.updated_at                               AS audit_item_session_updated_at,
                v_total_count
            FROM distinct_assets da
            INNER JOIN assets               a     ON da.asset_id              = a.id
            INNER JOIN asset_categories     ac    ON a.category               = ac.id
            INNER JOIN assets_types         ast   ON ac.assets_type           = ast.id
            INNER JOIN asset_sub_categories assc  ON a.sub_category           = assc.id
            LEFT  JOIN users                u_resp ON da.responsible_person_id = u_resp.id
            LEFT  JOIN designations         d_resp ON u_resp.designation_id    = d_resp.id
                                                  AND d_resp.deleted_at        IS NULL
            LEFT  JOIN organization         org    ON da.department_id         = org.id
                                                  AND org.deleted_at           IS NULL
            LEFT  JOIN users                u_lead ON da.lead_auditor_id       = u_lead.id
            LEFT  JOIN users                u_pled ON da.period_leader_id      = u_pled.id
            LEFT  JOIN designations         d_pled ON u_pled.designation_id    = d_pled.id
                                                  AND d_pled.deleted_at        IS NULL
            LEFT  JOIN asset_items_audit_sessions aias
                    ON aias.asset_item_id    = da.asset_item_id
                   AND aias.audit_session_id = da.audit_session_id
                   AND aias.deleted_at       IS NULL
                   AND aias.tenant_id        = p_tenant_id
            LEFT  JOIN users                u_audit_by ON u_audit_by.id        = aias.audit_by
            LEFT  JOIN designations         d_audit_by ON u_audit_by.designation_id = d_audit_by.id
                                                      AND d_audit_by.deleted_at IS NULL
            ORDER BY
                CASE WHEN p_sort_order = 'asc' THEN
                    CASE p_sort_by
                        WHEN 'asset_item_id'   THEN da.asset_item_id::TEXT
                        WHEN 'asset_name'      THEN a.name
                        WHEN 'serial_number'   THEN da.serial_number
                        WHEN 'model_number'    THEN da.model_number
                        WHEN 'asset_tag'       THEN da.asset_tag
                        WHEN 'session_code'    THEN da.session_code
                        WHEN 'session_name'    THEN da.session_name
                        WHEN 'scheduled_date'  THEN da.scheduled_date::TEXT
                        WHEN 'session_status'  THEN da.session_status
                        WHEN 'period_name'     THEN da.period_name
                        WHEN 'audit_status'    THEN COALESCE(aias.audit_status, 'pending')
                        ELSE da.asset_item_created_at::TEXT
                    END
                END ASC NULLS LAST,
                CASE WHEN p_sort_order = 'desc' OR p_sort_order IS NULL THEN
                    CASE p_sort_by
                        WHEN 'asset_item_id'   THEN da.asset_item_id::TEXT
                        WHEN 'asset_name'      THEN a.name
                        WHEN 'serial_number'   THEN da.serial_number
                        WHEN 'model_number'    THEN da.model_number
                        WHEN 'asset_tag'       THEN da.asset_tag
                        WHEN 'session_code'    THEN da.session_code
                        WHEN 'session_name'    THEN da.session_name
                        WHEN 'scheduled_date'  THEN da.scheduled_date::TEXT
                        WHEN 'session_status'  THEN da.session_status
                        WHEN 'period_name'     THEN da.period_name
                        WHEN 'audit_status'    THEN COALESCE(aias.audit_status, 'pending')
                        ELSE da.asset_item_created_at::TEXT
                    END
                END DESC NULLS LAST,
                -- Stable tiebreaker — ensures identical rows always sort in the same
                -- order across both the paginated list and the /next /previous fetches.
                da.audit_session_id ASC,
                da.asset_item_id    ASC
            LIMIT  p_per_page
            OFFSET v_offset;

        END;
        $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_auditor_assigned_assets CASCADE;");
    }
};
