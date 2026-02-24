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
                    WHERE proname = 'get_user_asset_requisitions'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

        CREATE OR REPLACE FUNCTION get_user_asset_requisitions(
            _tenant_id           BIGINT,
            _user_id             BIGINT,
            _requisition_type    VARCHAR,       -- 'new', 'upgrade', 'replace'
            _status              VARCHAR DEFAULT NULL,
            _page_no             INT     DEFAULT 1,
            _page_size           INT     DEFAULT 10,
            _search              TEXT    DEFAULT NULL,
            _sort_by             TEXT    DEFAULT 'newest'
        )
        RETURNS JSON
        LANGUAGE plpgsql
        AS $fn$
        DECLARE
            v_total_records INT;
            v_total_pages   INT;
            v_offset        INT;
            v_data          JSON := '[]'::JSON;
            v_order_clause  TEXT := 'ORDER BY ar.id DESC';
            v_status_filter TEXT := '';
            v_req_type      TEXT;
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

            IF _user_id IS NULL OR _user_id <= 0 THEN
                RETURN json_build_object(
                    'status', 'FAILURE',
                    'message', 'Invalid user ID',
                    'data', '[]'::JSON,
                    'meta', json_build_object('total_records', 0, 'total_pages', 0, 'current_page', _page_no, 'page_size', _page_size),
                    'success', FALSE
                );
            END IF;

            v_req_type := LOWER(TRIM(COALESCE(_requisition_type, '')));

            IF v_req_type NOT IN ('new', 'upgrade', 'replace') THEN
                RETURN json_build_object(
                    'status', 'FAILURE',
                    'message', 'Invalid requisition type. Must be new, upgrade, or replace.',
                    'data', '[]'::JSON,
                    'meta', json_build_object('total_records', 0, 'total_pages', 0, 'current_page', _page_no, 'page_size', _page_size),
                    'success', FALSE
                );
            END IF;

            -- ─── Sanitize page params ───
            _page_no   := GREATEST(COALESCE(_page_no, 1), 1);
            _page_size := GREATEST(COALESCE(_page_size, 10), 1);

            -- ─── Sorting logic ───
            IF v_req_type = 'upgrade' THEN
                CASE LOWER(TRIM(COALESCE(_sort_by, 'newest')))
                    WHEN 'newest'  THEN v_order_clause := 'ORDER BY uar.id DESC';
                    WHEN 'oldest'  THEN v_order_clause := 'ORDER BY uar.id ASC';
                    WHEN 'az'      THEN v_order_clause := 'ORDER BY a.name ASC NULLS LAST';
                    WHEN 'za'      THEN v_order_clause := 'ORDER BY a.name DESC NULLS LAST';
                    ELSE v_order_clause := 'ORDER BY uar.id DESC';
                END CASE;
            ELSIF v_req_type = 'replace' THEN
                CASE LOWER(TRIM(COALESCE(_sort_by, 'newest')))
                    WHEN 'newest'  THEN v_order_clause := 'ORDER BY rar.id DESC';
                    WHEN 'oldest'  THEN v_order_clause := 'ORDER BY rar.id ASC';
                    WHEN 'az'      THEN v_order_clause := 'ORDER BY a.name ASC NULLS LAST';
                    WHEN 'za'      THEN v_order_clause := 'ORDER BY a.name DESC NULLS LAST';
                    ELSE v_order_clause := 'ORDER BY rar.id DESC';
                END CASE;
            ELSE
                CASE LOWER(TRIM(COALESCE(_sort_by, 'newest')))
                    WHEN 'newest'  THEN v_order_clause := 'ORDER BY ar.id DESC';
                    WHEN 'oldest'  THEN v_order_clause := 'ORDER BY ar.id ASC';
                    ELSE v_order_clause := 'ORDER BY ar.id DESC';
                END CASE;
            END IF;

            -- ─── Status filter ───
            IF _status IS NOT NULL AND TRIM(_status) <> '' THEN
                IF v_req_type = 'upgrade' THEN
                    v_status_filter := format(' AND uar.status = %L', UPPER(TRIM(_status)));
                ELSIF v_req_type = 'replace' THEN
                    v_status_filter := format(' AND rar.status = %L', UPPER(TRIM(_status)));
                ELSE
                    v_status_filter := format(' AND ar.requisition_status = %L', UPPER(TRIM(_status)));
                END IF;
            END IF;

            -- ══════════════════════════════════════════════════════════════════
            -- UPGRADE REQUISITIONS
            -- ══════════════════════════════════════════════════════════════════
            IF v_req_type = 'upgrade' THEN

                -- Count
                EXECUTE format($SQL$
                    SELECT COUNT(*)
                    FROM upgrade_asset_requisitions uar
                    LEFT JOIN asset_requisitions ar
                        ON ar.id = uar.asset_requisition_id
                    LEFT JOIN asset_items ai
                        ON ai.id = uar.asset_id AND ai.isactive = TRUE
                    LEFT JOIN assets a
                        ON a.id = ai.asset_id
                    WHERE uar.tenant_id = %L
                      AND uar.created_by = %L
                      AND uar.deleted_at IS NULL
                      %s
                      AND (
                          %L IS NULL
                          OR ar.requisition_id ILIKE '%%' || %L || '%%'
                          OR a.name ILIKE '%%' || %L || '%%'
                          OR ai.serial_number ILIKE '%%' || %L || '%%'
                          OR ai.model_number ILIKE '%%' || %L || '%%'
                          OR uar.upgrade_description ILIKE '%%' || %L || '%%'
                      )
                $SQL$,
                    _tenant_id,
                    _user_id,
                    v_status_filter,
                    _search, _search, _search, _search, _search, _search
                ) INTO v_total_records;

                IF v_total_records = 0 THEN
                    RETURN json_build_object(
                        'status', 'SUCCESS',
                        'message', 'No upgrade requisitions found',
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

                v_total_pages := CEIL(v_total_records::DECIMAL / _page_size);
                v_offset      := (_page_no - 1) * _page_size;

                -- Fetch data
                EXECUTE format($SQL$
                    SELECT COALESCE(json_agg(row_to_json(t)), '[]'::JSON)
                    FROM (
                        SELECT
                            uar.id,
                            ar.requisition_id,
                            ar.id AS asset_requisition_id,
                            ar.requisition_date,
                            uar.status,
                            uar.upgrade_description,
                            uar.other_reason,
                            uar.justification,
                            uar.expected_date,
                            uar.error_logs_performance_doc,
                            uar.screenshots,
                            uar.other_docs,
                            uar.work_order_id,
                            uar.is_recommend_for_transition,
                            uar.created_at,

                            -- Decision ID
                            ard.id AS decision_id,

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

                            -- Created by
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
                                    WHERE arrp.asset_requisition_id = uar.asset_requisition_id
                                      AND arrp.asset_requisition_type_id = 2
                                      AND arrp.asset_requisition_data_id = uar.id
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
                                    WHERE arop.asset_requisition_id = uar.asset_requisition_id
                                      AND arop.asset_requisition_type_id = 2
                                      AND arop.asset_requisition_data_id = uar.id
                                      AND arop.tenant_id = %L
                                ),
                                '[]'::JSON
                            ) AS outcomes,

                            -- Action details (for REJECTED, ON_HOLD, REPLACE_SUGGESTED, SENT_TO_WORK_ORDER)
                            CASE
                                WHEN uar.status IN ('REJECTED', 'ON_HOLD', 'REPLACE_SUGGESTED', 'SENT_TO_WORK_ORDER') THEN
                                    COALESCE(
                                        (
                                            SELECT json_build_object(
                                                'id', act.id,
                                                'action_type', act.action_type,
                                                'reason', act.reason,
                                                'additional_note', act.additional_note,
                                                'action_by', json_build_object(
                                                    'id', au.id,
                                                    'user_name', au.user_name,
                                                    'name', au.name,
                                                    'profile_image', au.profile_image
                                                ),
                                                'created_at', act.created_at
                                            )
                                            FROM asset_requisition_actions act
                                            LEFT JOIN users au ON au.id = act.action_by
                                            WHERE act.asset_requisition_id = uar.asset_requisition_id
                                              AND act.is_active = TRUE
                                            ORDER BY act.id DESC
                                            LIMIT 1
                                        ),
                                        NULL
                                    )
                                ELSE NULL
                            END AS action_details

                        FROM upgrade_asset_requisitions uar
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
                        LEFT JOIN asset_requisition_decision ard
                            ON ard.asset_requisition_data_id = uar.id
                            AND ard.deleted_at IS NULL
                        WHERE uar.tenant_id = %L
                          AND uar.created_by = %L
                          AND uar.deleted_at IS NULL
                          %s
                          AND (
                              %L IS NULL
                              OR ar.requisition_id ILIKE '%%' || %L || '%%'
                              OR a.name ILIKE '%%' || %L || '%%'
                              OR ai.serial_number ILIKE '%%' || %L || '%%'
                              OR ai.model_number ILIKE '%%' || %L || '%%'
                              OR uar.upgrade_description ILIKE '%%' || %L || '%%'
                          )
                        %s
                        LIMIT %s OFFSET %s
                    ) t
                $SQL$,
                    _tenant_id,
                    _tenant_id,
                    _tenant_id,
                    _user_id,
                    v_status_filter,
                    _search, _search, _search, _search, _search, _search,
                    v_order_clause,
                    _page_size, v_offset
                ) INTO v_data;

            -- ══════════════════════════════════════════════════════════════════
            -- REPLACE REQUISITIONS
            -- ══════════════════════════════════════════════════════════════════
            ELSIF v_req_type = 'replace' THEN

                -- Count
                EXECUTE format($SQL$
                    SELECT COUNT(*)
                    FROM replace_asset_requisitions rar
                    LEFT JOIN asset_requisitions ar
                        ON ar.id = rar.asset_requisition_id
                    LEFT JOIN asset_items ai
                        ON ai.id = rar.asset_id AND ai.isactive = TRUE
                    LEFT JOIN assets a
                        ON a.id = ai.asset_id
                    WHERE rar.tenant_id = %L
                      AND rar.created_by = %L
                      AND rar.deleted_at IS NULL
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
                    _user_id,
                    v_status_filter,
                    _search, _search, _search, _search, _search, _search
                ) INTO v_total_records;

                IF v_total_records = 0 THEN
                    RETURN json_build_object(
                        'status', 'SUCCESS',
                        'message', 'No replacement requisitions found',
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

                v_total_pages := CEIL(v_total_records::DECIMAL / _page_size);
                v_offset      := (_page_no - 1) * _page_size;

                -- Fetch data
                EXECUTE format($SQL$
                    SELECT COALESCE(json_agg(row_to_json(t)), '[]'::JSON)
                    FROM (
                        SELECT
                            rar.id,
                            ar.requisition_id,
                            ar.id AS asset_requisition_id,
                            ar.requisition_date,
                            rar.replace_requisition_number,
                            rar.status,
                            rar.replacement_description,
                            rar.other_reason,
                            rar.justification,
                            rar.expected_date,
                            rar.mode_of_acquisition,
                            rar.expected_condition,
                            rar.error_logs_performance_doc,
                            rar.screenshots,
                            rar.other_docs,
                            rar.work_order_id,
                            rar.is_came_from_upgrade_req,
                            rar.upgrade_action_id,
                            rar.is_disposal_recommended,
                            rar.disposal_recommended_type,
                            rar.created_at,

                            -- Decision ID
                            ard.id AS decision_id,

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

                            -- Created by
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
                                    WHERE arrp.asset_requisition_id = rar.asset_requisition_id
                                      AND arrp.asset_requisition_type_id = 3
                                      AND arrp.asset_requisition_data_id = rar.id
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
                                    WHERE arop.asset_requisition_id = rar.asset_requisition_id
                                      AND arop.asset_requisition_type_id = 3
                                      AND arop.asset_requisition_data_id = rar.id
                                      AND arop.tenant_id = %L
                                ),
                                '[]'::JSON
                            ) AS outcomes,

                            -- Suppliers (from pivot)
                            COALESCE(
                                (
                                    SELECT json_agg(json_build_object(
                                        'id', s.id,
                                        'supplier_name', s.supplier_name,
                                        'email', su.email,
                                        'contact', su.contact_no
                                    ))
                                    FROM asset_requisition_supplier_pivot arsp
                                    INNER JOIN suppliers s
                                        ON s.id = arsp.supplier_id AND s.isactive = TRUE
                                    LEFT JOIN users su
                                        ON su.id = s.user_id
                                    WHERE arsp.asset_requisition_type_id = 3
                                      AND arsp.asset_requisition_data_id = rar.id
                                      AND arsp.tenant_id = %L
                                      AND arsp.is_active = TRUE
                                      AND arsp.deleted_at IS NULL
                                ),
                                '[]'::JSON
                            ) AS suppliers,

                            -- Action details (for REJECTED, ON_HOLD, REJECTED_BY_ML)
                            CASE
                                WHEN rar.status IN ('REJECTED', 'ON_HOLD', 'REJECTED_BY_ML') THEN
                                    COALESCE(
                                        (
                                            SELECT json_build_object(
                                                'id', act.id,
                                                'action_type', act.action_type,
                                                'reason', act.reason,
                                                'additional_note', act.additional_note,
                                                'action_by', json_build_object(
                                                    'id', au.id,
                                                    'user_name', au.user_name,
                                                    'name', au.name,
                                                    'profile_image', au.profile_image
                                                ),
                                                'created_at', act.created_at
                                            )
                                            FROM asset_requisition_actions act
                                            LEFT JOIN users au ON au.id = act.action_by
                                            WHERE act.asset_requisition_id = rar.asset_requisition_id
                                              AND act.is_active = TRUE
                                            ORDER BY act.id DESC
                                            LIMIT 1
                                        ),
                                        NULL
                                    )
                                ELSE NULL
                            END AS action_details

                        FROM replace_asset_requisitions rar
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
                        LEFT JOIN asset_requisition_decision ard
                            ON ard.asset_requisition_data_id = rar.id
                            AND ard.deleted_at IS NULL
                        WHERE rar.tenant_id = %L
                          AND rar.created_by = %L
                          AND rar.deleted_at IS NULL
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
                    _user_id,
                    v_status_filter,
                    _search, _search, _search, _search, _search, _search,
                    v_order_clause,
                    _page_size, v_offset
                ) INTO v_data;

            -- ══════════════════════════════════════════════════════════════════
            -- NEW ASSET REQUISITIONS
            -- ══════════════════════════════════════════════════════════════════
            ELSIF v_req_type = 'new' THEN

                -- Count
                EXECUTE format($SQL$
                    SELECT COUNT(*)
                    FROM asset_requisitions ar
                    WHERE ar.tenant_id = %L
                      AND ar.requisition_by = %L
                      AND ar.deleted_at IS NULL
                      AND ar.isactive = TRUE
                      %s
                      AND (
                          %L IS NULL
                          OR ar.requisition_id ILIKE '%%' || %L || '%%'
                      )
                $SQL$,
                    _tenant_id,
                    _user_id,
                    v_status_filter,
                    _search, _search
                ) INTO v_total_records;

                IF v_total_records = 0 THEN
                    RETURN json_build_object(
                        'status', 'SUCCESS',
                        'message', 'No new asset requisitions found',
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

                v_total_pages := CEIL(v_total_records::DECIMAL / _page_size);
                v_offset      := (_page_no - 1) * _page_size;

                -- Fetch data
                EXECUTE format($SQL$
                    SELECT COALESCE(json_agg(row_to_json(t)), '[]'::JSON)
                    FROM (
                        SELECT
                            ar.id AS asset_requisition_id,
                            ar.requisition_id,
                            ar.requisition_date,
                            ar.requisition_status,
                            ar.requisition_by,
                            ar.created_at,

                            -- Created by
                            json_build_object(
                                'id', u.id,
                                'user_name', u.user_name,
                                'email', u.email,
                                'name', u.name,
                                'profile_image', u.profile_image
                            ) AS created_by,

                            -- Items
                            COALESCE(
                                (
                                    SELECT json_agg(json_build_object(
                                        'id', ari.id,
                                        'item_name', ari.item_name,
                                        'asset_type', ari.asset_type,
                                        'quantity', ari.quantity,
                                        'budget', ari.budget,
                                        'budget_currency', ari.budget_currency,
                                        'business_purpose', ari.business_purpose,
                                        'priority', ari.priority,
                                        'priority_name', ari.priority_name,
                                        'required_date', ari.required_date,
                                        'reason', ari.reason,
                                        'asset_acquisition_type', ari.asset_acquisition_type,
                                        'availability_type_name', ari.availability_type_name,
                                        'period_status_name', ari.period_status_name,
                                        'expected_conditions', ari.expected_conditions,
                                        'description', ari.description,
                                        'files', ari.files
                                    ))
                                    FROM asset_requisitions_items ari
                                    WHERE ari.asset_requisition_id = ar.id
                                      AND ari.tenant_id = %L
                                ),
                                '[]'::JSON
                            ) AS items

                        FROM asset_requisitions ar
                        LEFT JOIN users u
                            ON u.id = ar.requisition_by
                        WHERE ar.tenant_id = %L
                          AND ar.requisition_by = %L
                          AND ar.deleted_at IS NULL
                          AND ar.isactive = TRUE
                          %s
                          AND (
                              %L IS NULL
                              OR ar.requisition_id ILIKE '%%' || %L || '%%'
                          )
                        %s
                        LIMIT %s OFFSET %s
                    ) t
                $SQL$,
                    _tenant_id,
                    _tenant_id,
                    _user_id,
                    v_status_filter,
                    _search, _search,
                    v_order_clause,
                    _page_size, v_offset
                ) INTO v_data;

            END IF;

            -- ─── Return result ───
            RETURN json_build_object(
                'status', 'SUCCESS',
                'message', format('%s requisitions fetched successfully', INITCAP(v_req_type)),
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
                    WHERE proname = 'get_user_asset_requisitions'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
        SQL);
    }
};
