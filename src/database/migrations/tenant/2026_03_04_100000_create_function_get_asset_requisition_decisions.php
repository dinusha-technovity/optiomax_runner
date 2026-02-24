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
        DB::unprepared(<<<'SQL'
        DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_asset_requisition_decisions'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

        CREATE OR REPLACE FUNCTION get_asset_requisition_decisions(
            _tenant_id              BIGINT,
            _requisition_type_id    INT,
            _status                 VARCHAR DEFAULT NULL,
            _page_no                INT     DEFAULT 1,
            _page_size              INT     DEFAULT 10,
            _search                 TEXT    DEFAULT NULL,
            _sort_by                TEXT    DEFAULT 'newest',
            _user_id                BIGINT  DEFAULT NULL
        )
        RETURNS JSON
        LANGUAGE plpgsql
        AS $fn$
        DECLARE
            v_total_records INT;
            v_total_pages   INT;
            v_offset        INT;
            v_data          JSON := '[]'::JSON;
            v_order_clause  TEXT := 'ORDER BY ard.id DESC';
            v_status_filter TEXT := '';
            v_user_filter   TEXT := '';
        BEGIN

            -- ─── Validate inputs ───
            IF _tenant_id IS NULL OR _tenant_id <= 0 THEN
                RETURN json_build_object(
                    'status', 'FAILURE',
                    'message', 'Invalid tenant ID',
                    'data', '[]'::JSON,
                    'meta', json_build_object('total_records', 0, 'total_pages', 0, 'current_page', _page_no, 'page_size', _page_size),
                    'success', FALSE
                );
            END IF;

            IF _requisition_type_id IS NULL OR _requisition_type_id NOT IN (2, 3) THEN
                RETURN json_build_object(
                    'status', 'FAILURE',
                    'message', 'Invalid requisition type ID. Must be 2 (upgrade) or 3 (replace).',
                    'data', '[]'::JSON,
                    'meta', json_build_object('total_records', 0, 'total_pages', 0, 'current_page', _page_no, 'page_size', _page_size),
                    'success', FALSE
                );
            END IF;

            -- ─── Sorting logic ───
            CASE LOWER(TRIM(COALESCE(_sort_by, 'newest')))
                WHEN 'newest'  THEN v_order_clause := 'ORDER BY ard.id DESC';
                WHEN 'oldest'  THEN v_order_clause := 'ORDER BY ard.id ASC';
                WHEN 'az'      THEN v_order_clause := 'ORDER BY a.name ASC NULLS LAST';
                WHEN 'za'      THEN v_order_clause := 'ORDER BY a.name DESC NULLS LAST';
                ELSE v_order_clause := 'ORDER BY ard.id DESC';
            END CASE;

            -- ─── Status filter ───
            IF _status IS NOT NULL AND TRIM(_status) <> '' THEN
                v_status_filter := format(' AND ard.status = %L', UPPER(TRIM(_status)));
            END IF;

            -- ─── Maintenance team leader filter ───
            IF _user_id IS NOT NULL THEN
                v_user_filter := format(
                    ' AND EXISTS (
                        SELECT 1
                        FROM maintenance_team_related_asset_groups mtrag
                        INNER JOIN maintenance_team_members mtm
                            ON mtm.team_id = mtrag.team_id
                            AND mtm.is_team_leader = TRUE
                            AND mtm.user_id = %L
                            AND mtm.isactive = TRUE
                            AND mtm.deleted_at IS NULL
                        WHERE mtrag.asset_group_id = a.id
                          AND mtrag.tenant_id = %L
                          AND mtrag.isactive = TRUE
                          AND mtrag.deleted_at IS NULL
                    )',
                    _user_id,
                    _tenant_id
                );
            END IF;

            -- ─── Sanitize page params ───
            _page_no   := GREATEST(COALESCE(_page_no, 1), 1);
            _page_size := GREATEST(COALESCE(_page_size, 10), 1);

            -- ────────────────────────────────────────────────────────────────────
            -- UPGRADE REQUESTS  (requisition_type_id = 2)
            -- ────────────────────────────────────────────────────────────────────
            IF _requisition_type_id = 2 THEN

                -- Count
                EXECUTE format($SQL$
                    SELECT COUNT(*)
                    FROM asset_requisition_decision ard
                    LEFT JOIN upgrade_asset_requisitions uar
                        ON uar.id = ard.asset_requisition_data_id
                        AND uar.deleted_at IS NULL
                    LEFT JOIN asset_requisitions ar
                        ON ar.id = uar.asset_requisition_id
                    LEFT JOIN asset_items ai
                        ON ai.id = uar.asset_id AND ai.isactive = TRUE
                    LEFT JOIN assets a
                        ON a.id = ai.asset_id
                    WHERE ard.tenant_id = %L
                      AND ard.requisition_type_id = 2

                      AND ard.deleted_at IS NULL
                      %s
                      %s
                      AND (
                          %L IS NULL
                          OR ar.requisition_id ILIKE '%%' || %L || '%%'
                          OR a.name ILIKE '%%' || %L || '%%'
                          OR ai.serial_number ILIKE '%%' || %L || '%%'
                          OR ai.model_number ILIKE '%%' || %L || '%%'
                      )
                $SQL$,
                    _tenant_id,
                    v_status_filter,
                    v_user_filter,
                    _search, _search, _search, _search, _search
                ) INTO v_total_records;

                IF v_total_records = 0 THEN
                    RETURN json_build_object(
                        'status', 'SUCCESS',
                        'message', 'No upgrade decision requests found',
                        'data', '[]'::JSON,
                        'meta', json_build_object(
                            'total_records', 0,
                            'total_pages', 0,
                            'current_page', _page_no,
                            'page_size', _page_size
                        ),
                        'success', TRUE
                    );
                END IF;

                -- Pagination
                v_total_pages := CEIL(v_total_records::DECIMAL / _page_size);
                v_offset      := (_page_no - 1) * _page_size;

                -- Fetch data
                EXECUTE format($SQL$
                    SELECT COALESCE(json_agg(row_to_json(t)), '[]'::JSON)
                    FROM (
                        SELECT
                            ard.id AS decision_id,
                            ard.asset_requisition_id,
                            ard.requisition_type_id,
                            ard.asset_requisition_data_id,
                            ard.status AS decision_status,
                            ard.is_get_action,
                            ard.created_at AS decision_created_at,
                            ard.updated_at AS decision_updated_at,

                            -- Asset requisition base info
                            json_build_object(
                                'id', ar.id,
                                'requisition_id', ar.requisition_id,
                                'requisition_date', ar.requisition_date
                            ) AS asset_requisition,

                            -- Upgrade requisition data
                            json_build_object(
                                'id', uar.id,
                                'upgrade_description', uar.upgrade_description,
                                'other_reason', uar.other_reason,
                                'justification', uar.justification,
                                'expected_date', uar.expected_date,
                                'error_logs_performance_doc', uar.error_logs_performance_doc,
                                'screenshots', uar.screenshots,
                                'other_docs', uar.other_docs,
                                'notified_maintenance_leaders', uar.notified_maintenance_leaders,
                                'work_order_id', uar.work_order_id,
                                'status', uar.status,
                                'is_recommend_for_transition', uar.is_recommend_for_transition,
                                'is_active', uar.is_active,
                                'created_at', uar.created_at
                            ) AS upgrade_data,

                            -- Asset details
                            json_build_object(
                                'id', ai.id,
                                'asset_name', a.name,
                                'model_number', ai.model_number,
                                'serial_number', ai.serial_number,
                                'thumbnail_image', ai.thumbnail_image,
                                'qr_code', ai.qr_code,
                                'item_value', ai.item_value,
                                'asset_id', ai.asset_id
                            ) AS asset,

                            -- Priority
                            CASE
                                WHEN uar.priority IS NOT NULL THEN
                                    json_build_object('id', wpl.id, 'name', wpl.name, 'level', wpl.level)
                                ELSE NULL
                            END AS priority,

                            -- Created by (requester)
                            json_build_object(
                                'id', u.id,
                                'user_name', u.user_name,
                                'email', u.email,
                                'name', u.name,
                                'profile_image', u.profile_image
                            ) AS created_by,

                            -- Reasons (from pivot)
                            COALESCE(
                                (
                                    SELECT json_agg(json_build_object(
                                        'id', aurr.id,
                                        'code', aurr.code,
                                        'title', aurr.title,
                                        'description', aurr.description
                                    ))
                                    FROM asset_requisition_reasons_pivot arrp
                                    INNER JOIN asset_upgrade_replace_reasons aurr
                                        ON aurr.id = arrp.reason_id AND aurr.is_active = TRUE
                                    WHERE arrp.asset_requisition_id = ard.asset_requisition_id
                                      AND arrp.asset_requisition_type_id = 2
                                      AND arrp.asset_requisition_data_id = ard.asset_requisition_data_id
                                      AND arrp.tenant_id = %L
                                ),
                                '[]'::JSON
                            ) AS reasons,

                            -- Outcomes (from pivot)
                            COALESCE(
                                (
                                    SELECT json_agg(json_build_object(
                                        'id', auro.id,
                                        'outcome_text', auro.outcome_text,
                                        'description', auro.description
                                    ))
                                    FROM asset_requisition_outcomes_pivot arop
                                    INNER JOIN asset_upgrade_replace_outcomes auro
                                        ON auro.id = arop.outcome_id AND auro.is_active = TRUE
                                    WHERE arop.asset_requisition_id = ard.asset_requisition_id
                                      AND arop.asset_requisition_type_id = 2
                                      AND arop.asset_requisition_data_id = ard.asset_requisition_data_id
                                      AND arop.tenant_id = %L
                                ),
                                '[]'::JSON
                            ) AS outcomes,

                            -- Latest action
                            COALESCE(
                                (
                                    SELECT json_build_object(
                                        'id', act.id,
                                        'action_type', act.action_type,
                                        'reason', act.reason,
                                        'additional_note', act.additional_note,
                                        'action_by_name', au.user_name,
                                        'created_at', act.created_at
                                    )
                                    FROM asset_requisition_actions act
                                    LEFT JOIN users au ON au.id = act.action_by
                                    WHERE act.decision_id = ard.id
                                      AND act.is_active = TRUE
                                    ORDER BY act.id DESC
                                    LIMIT 1
                                ),
                                NULL
                            ) AS latest_action,

                            -- Action details (for REJECTED and ON_HOLD statuses)
                            CASE
                                WHEN ard.status IN ('REJECTED', 'ON_HOLD') THEN
                                    COALESCE(
                                        (
                                            SELECT json_build_object(
                                                'reason', act2.reason,
                                                'additional_note', act2.additional_note,
                                                'action_by_name', au2.user_name,
                                                'created_at', act2.created_at
                                            )
                                            FROM asset_requisition_actions act2
                                            LEFT JOIN users au2 ON au2.id = act2.action_by
                                            WHERE act2.decision_id = ard.id
                                              AND act2.is_active = TRUE
                                            ORDER BY act2.id DESC
                                            LIMIT 1
                                        ),
                                        NULL
                                    )
                                ELSE NULL
                            END AS action_details

                        FROM asset_requisition_decision ard
                        LEFT JOIN upgrade_asset_requisitions uar
                            ON uar.id = ard.asset_requisition_data_id
                            AND uar.deleted_at IS NULL
                        LEFT JOIN asset_requisitions ar
                            ON ar.id = uar.asset_requisition_id
                        LEFT JOIN asset_items ai
                            ON ai.id = uar.asset_id AND ai.isactive = TRUE
                        LEFT JOIN assets a
                            ON a.id = ai.asset_id
                        LEFT JOIN users u
                            ON u.id = uar.created_by
                        LEFT JOIN work_order_priority_levels wpl
                            ON wpl.id = uar.priority
                        WHERE ard.tenant_id = %L
                          AND ard.requisition_type_id = 2

                          AND ard.deleted_at IS NULL
                          %s
                          %s
                          AND (
                              %L IS NULL
                              OR ar.requisition_id ILIKE '%%' || %L || '%%'
                              OR a.name ILIKE '%%' || %L || '%%'
                              OR ai.serial_number ILIKE '%%' || %L || '%%'
                              OR ai.model_number ILIKE '%%' || %L || '%%'
                          )
                        %s
                        LIMIT %s OFFSET %s
                    ) t
                $SQL$,
                    _tenant_id,
                    _tenant_id,
                    _tenant_id,
                    v_status_filter,
                    v_user_filter,
                    _search, _search, _search, _search, _search,
                    v_order_clause,
                    _page_size, v_offset
                ) INTO v_data;

            -- ────────────────────────────────────────────────────────────────────
            -- REPLACE REQUESTS  (requisition_type_id = 3)
            -- ────────────────────────────────────────────────────────────────────
            ELSIF _requisition_type_id = 3 THEN

                -- Count
                EXECUTE format($SQL$
                    SELECT COUNT(*)
                    FROM asset_requisition_decision ard
                    LEFT JOIN replace_asset_requisitions rar
                        ON rar.id = ard.asset_requisition_data_id
                        AND rar.deleted_at IS NULL
                    LEFT JOIN asset_requisitions ar
                        ON ar.id = rar.asset_requisition_id
                    LEFT JOIN asset_items ai
                        ON ai.id = rar.asset_id AND ai.isactive = TRUE
                    LEFT JOIN assets a
                        ON a.id = ai.asset_id
                    WHERE ard.tenant_id = %L
                      AND ard.requisition_type_id = 3

                      AND ard.deleted_at IS NULL
                      %s
                      %s
                      AND (
                          %L IS NULL
                          OR ar.requisition_id ILIKE '%%' || %L || '%%'
                          OR a.name ILIKE '%%' || %L || '%%'
                          OR ai.serial_number ILIKE '%%' || %L || '%%'
                          OR ai.model_number ILIKE '%%' || %L || '%%'
                          OR rar.replacement_description ILIKE '%%' || %L || '%%'
                      )
                $SQL$,
                    _tenant_id,
                    v_status_filter,
                    v_user_filter,
                    _search, _search, _search, _search, _search, _search
                ) INTO v_total_records;

                IF v_total_records = 0 THEN
                    RETURN json_build_object(
                        'status', 'SUCCESS',
                        'message', 'No replacement decision requests found',
                        'data', '[]'::JSON,
                        'meta', json_build_object(
                            'total_records', 0,
                            'total_pages', 0,
                            'current_page', _page_no,
                            'page_size', _page_size
                        ),
                        'success', TRUE
                    );
                END IF;

                -- Pagination
                v_total_pages := CEIL(v_total_records::DECIMAL / _page_size);
                v_offset      := (_page_no - 1) * _page_size;

                -- Fetch data
                EXECUTE format($SQL$
                    SELECT COALESCE(json_agg(row_to_json(t)), '[]'::JSON)
                    FROM (
                        SELECT
                            ard.id AS decision_id,
                            ard.asset_requisition_id,
                            ard.requisition_type_id,
                            ard.asset_requisition_data_id,
                            ard.status AS decision_status,
                            ard.is_get_action,
                            ard.created_at AS decision_created_at,
                            ard.updated_at AS decision_updated_at,

                            -- Asset requisition base info
                            json_build_object(
                                'id', ar.id,
                                'requisition_id', ar.requisition_id,
                                'requisition_date', ar.requisition_date
                            ) AS asset_requisition,

                            -- Replace requisition data
                            json_build_object(
                                'id', rar.id,
                                'replacement_description', rar.replacement_description,
                                'other_reason', rar.other_reason,
                                'justification', rar.justification,
                                'expected_date', rar.expected_date,
                                'mode_of_acquisition', rar.mode_of_acquisition,
                                'expected_condition', rar.expected_condition,
                                'error_logs_performance_doc', rar.error_logs_performance_doc,
                                'screenshots', rar.screenshots,
                                'other_docs', rar.other_docs,
                                'notified_maintenance_leaders', rar.notified_maintenance_leaders,
                                'work_order_id', rar.work_order_id,
                                'status', rar.status,
                                'is_came_from_upgrade_req', rar.is_came_from_upgrade_req,
                                'upgrade_action_id', rar.upgrade_action_id,
                                'is_disposal_recommended', rar.is_disposal_recommended,
                                'disposal_recommended_type', rar.disposal_recommended_type,
                                'is_active', rar.is_active,
                                'created_at', rar.created_at
                            ) AS replace_data,

                            -- Asset details
                            json_build_object(
                                'id', ai.id,
                                'asset_name', a.name,
                                'model_number', ai.model_number,
                                'serial_number', ai.serial_number,
                                'thumbnail_image', ai.thumbnail_image,
                                'qr_code', ai.qr_code,
                                'item_value', ai.item_value,
                                'asset_id', ai.asset_id
                            ) AS asset,

                            -- Priority
                            CASE
                                WHEN rar.priority IS NOT NULL THEN
                                    json_build_object('id', wpl.id, 'name', wpl.name, 'level', wpl.level)
                                ELSE NULL
                            END AS priority,

                            -- Mode of acquisition
                            CASE
                                WHEN rar.mode_of_acquisition IS NOT NULL THEN
                                    json_build_object('id', arat.id, 'name', arat.name)
                                ELSE NULL
                            END AS acquisition_mode,

                            -- Expected condition
                            CASE
                                WHEN rar.expected_condition IS NOT NULL THEN
                                    json_build_object('id', arct.id, 'name', arct.name)
                                ELSE NULL
                            END AS condition_type,

                            -- Disposal recommended type
                            CASE
                                WHEN rar.disposal_recommended_type IS NOT NULL THEN
                                    json_build_object('id', drt.id, 'code', drt.code, 'title', drt.title)
                                ELSE NULL
                            END AS disposal_type,

                            -- Created by (requester)
                            json_build_object(
                                'id', u.id,
                                'user_name', u.user_name,
                                'email', u.email,
                                'name', u.name,
                                'profile_image', u.profile_image
                            ) AS created_by,

                            -- Reasons (from pivot)
                            COALESCE(
                                (
                                    SELECT json_agg(json_build_object(
                                        'id', aurr.id,
                                        'code', aurr.code,
                                        'title', aurr.title,
                                        'description', aurr.description
                                    ))
                                    FROM asset_requisition_reasons_pivot arrp
                                    INNER JOIN asset_upgrade_replace_reasons aurr
                                        ON aurr.id = arrp.reason_id AND aurr.is_active = TRUE
                                    WHERE arrp.asset_requisition_id = ard.asset_requisition_id
                                      AND arrp.asset_requisition_type_id = 3
                                      AND arrp.asset_requisition_data_id = ard.asset_requisition_data_id
                                      AND arrp.tenant_id = %L
                                ),
                                '[]'::JSON
                            ) AS reasons,

                            -- Outcomes (from pivot)
                            COALESCE(
                                (
                                    SELECT json_agg(json_build_object(
                                        'id', auro.id,
                                        'outcome_text', auro.outcome_text,
                                        'description', auro.description
                                    ))
                                    FROM asset_requisition_outcomes_pivot arop
                                    INNER JOIN asset_upgrade_replace_outcomes auro
                                        ON auro.id = arop.outcome_id AND auro.is_active = TRUE
                                    WHERE arop.asset_requisition_id = ard.asset_requisition_id
                                      AND arop.asset_requisition_type_id = 3
                                      AND arop.asset_requisition_data_id = ard.asset_requisition_data_id
                                      AND arop.tenant_id = %L
                                ),
                                '[]'::JSON
                            ) AS outcomes,

                            -- Suppliers (from pivot)
                            COALESCE(
                                (
                                    SELECT json_agg(json_build_object(
                                        'id', s.id,
                                        'supplier_name', s.name,
                                        'email', s.email,
                                        'contact', s.contact_no
                                    ))
                                    FROM asset_requisition_supplier_pivot arsp
                                    INNER JOIN suppliers s
                                        ON s.id = arsp.supplier_id AND s.isactive = TRUE
                                    WHERE arsp.asset_requisition_type_id = 3
                                      AND arsp.asset_requisition_data_id = ard.asset_requisition_data_id
                                      AND arsp.tenant_id = %L
                                      AND arsp.is_active = TRUE
                                      AND arsp.deleted_at IS NULL
                                ),
                                '[]'::JSON
                            ) AS suppliers,

                            -- Latest action
                            COALESCE(
                                (
                                    SELECT json_build_object(
                                        'id', act.id,
                                        'action_type', act.action_type,
                                        'reason', act.reason,
                                        'additional_note', act.additional_note,
                                        'action_by_name', au.user_name,
                                        'created_at', act.created_at
                                    )
                                    FROM asset_requisition_actions act
                                    LEFT JOIN users au ON au.id = act.action_by
                                    WHERE act.decision_id = ard.id
                                      AND act.is_active = TRUE
                                    ORDER BY act.id DESC
                                    LIMIT 1
                                ),
                                NULL
                            ) AS latest_action,

                            -- Action details (for REJECTED and ON_HOLD statuses)
                            CASE
                                WHEN ard.status IN ('REJECTED', 'ON_HOLD') THEN
                                    COALESCE(
                                        (
                                            SELECT json_build_object(
                                                'reason', act2.reason,
                                                'additional_note', act2.additional_note,
                                                'action_by_name', au2.user_name,
                                                'created_at', act2.created_at
                                            )
                                            FROM asset_requisition_actions act2
                                            LEFT JOIN users au2 ON au2.id = act2.action_by
                                            WHERE act2.decision_id = ard.id
                                              AND act2.is_active = TRUE
                                            ORDER BY act2.id DESC
                                            LIMIT 1
                                        ),
                                        NULL
                                    )
                                ELSE NULL
                            END AS action_details

                        FROM asset_requisition_decision ard
                        LEFT JOIN replace_asset_requisitions rar
                            ON rar.id = ard.asset_requisition_data_id
                            AND rar.deleted_at IS NULL
                        LEFT JOIN asset_requisitions ar
                            ON ar.id = rar.asset_requisition_id
                        LEFT JOIN asset_items ai
                            ON ai.id = rar.asset_id AND ai.isactive = TRUE
                        LEFT JOIN assets a
                            ON a.id = ai.asset_id
                        LEFT JOIN users u
                            ON u.id = rar.created_by
                        LEFT JOIN work_order_priority_levels wpl
                            ON wpl.id = rar.priority
                        LEFT JOIN asset_requisition_availability_types arat
                            ON arat.id = rar.mode_of_acquisition
                        LEFT JOIN asset_received_condition_types arct
                            ON arct.id = rar.expected_condition
                        LEFT JOIN asset_upgrade_replace_reasons drt
                            ON drt.id = rar.disposal_recommended_type
                        WHERE ard.tenant_id = %L
                          AND ard.requisition_type_id = 3

                          AND ard.deleted_at IS NULL
                          %s
                          %s
                          AND (
                              %L IS NULL
                              OR ar.requisition_id ILIKE '%%' || %L || '%%'
                              OR a.name ILIKE '%%' || %L || '%%'
                              OR ai.serial_number ILIKE '%%' || %L || '%%'
                              OR ai.model_number ILIKE '%%' || %L || '%%'
                              OR rar.replacement_description ILIKE '%%' || %L || '%%'
                          )
                        %s
                        LIMIT %s OFFSET %s
                    ) t
                $SQL$,
                    _tenant_id,
                    _tenant_id,
                    _tenant_id,
                    _tenant_id,
                    v_status_filter,
                    v_user_filter,
                    _search, _search, _search, _search, _search, _search,
                    v_order_clause,
                    _page_size, v_offset
                ) INTO v_data;

            END IF;

            -- ─── Return result ───
            RETURN json_build_object(
                'status', 'SUCCESS',
                'message', CASE
                    WHEN _requisition_type_id = 2 THEN 'Upgrade decision requests fetched successfully'
                    ELSE 'Replacement decision requests fetched successfully'
                END,
                'meta', json_build_object(
                    'total_records', v_total_records,
                    'total_pages', v_total_pages,
                    'current_page', _page_no,
                    'page_size', _page_size
                ),
                'data', v_data,
                'success', TRUE
            );

        END;
        $fn$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_asset_requisition_decisions'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
        SQL);
    }
};
