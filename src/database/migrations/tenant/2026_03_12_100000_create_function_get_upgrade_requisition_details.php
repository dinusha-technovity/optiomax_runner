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

            -- Drop all versions of the function if exists
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_upgrade_requisition_details'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION get_upgrade_requisition_details(
                _asset_requisition_id   BIGINT,
                _tenant_id              BIGINT
            )
            RETURNS JSON
            LANGUAGE plpgsql
            AS $fn$
            DECLARE
                v_data JSON := NULL;
            BEGIN

                -- ─── Validate inputs ───
                IF _asset_requisition_id IS NULL OR _asset_requisition_id <= 0 THEN
                    RETURN json_build_object(
                        'status', 'FAILURE',
                        'message', 'Invalid asset requisition ID',
                        'data', NULL::JSON,
                        'success', FALSE
                    );
                END IF;

                IF _tenant_id IS NULL OR _tenant_id <= 0 THEN
                    RETURN json_build_object(
                        'status', 'FAILURE',
                        'message', 'Invalid tenant ID',
                        'data', NULL::JSON,
                        'success', FALSE
                    );
                END IF;

                -- ─── Fetch upgrade requisition details ───
                SELECT row_to_json(t) INTO v_data
                FROM (
                    SELECT
                        uar.id,
                        ar.id AS asset_requisition_id,
                        ar.requisition_id,
                        ar.requisition_date,
                        uar.expected_date,
                        uar.status,
                        uar.upgrade_description,
                        uar.justification,
                        uar.other_reason,
                        uar.error_logs_performance_doc,
                        uar.screenshots,
                        uar.other_docs,
                        uar.work_order_id,
                        uar.is_recommend_for_transition,
                        uar.created_at,

                        -- Asset details
                        json_build_object(
                            'id', ai.id,
                            'asset_name', a.name,
                            'model_number', ai.model_number,
                            'serial_number', ai.serial_number,
                            'thumbnail_image', ai.thumbnail_image,
                            'qr_code', ai.qr_code,
                            'item_value', ai.item_value,
                            'asset_id', ai.asset_id,
                            'asset_tag', ai.asset_tag
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
                                  AND arrp.tenant_id = _tenant_id
                            ),
                            '[]'::JSON
                        ) AS reasons,

                        -- Outcomes / Specifications (from pivot)
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
                                  AND arop.tenant_id = _tenant_id
                            ),
                            '[]'::JSON
                        ) AS specifications,

                        -- Action details (latest action for REJECTED / ON_HOLD / REPLACE_SUGGESTED)
                        CASE
                            WHEN uar.status IN ('REJECTED', 'ON_HOLD', 'REPLACE_SUGGESTED') THEN
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
                    WHERE uar.asset_requisition_id = _asset_requisition_id
                      AND uar.tenant_id = _tenant_id
                      AND uar.deleted_at IS NULL
                    LIMIT 1
                ) t;

                -- ─── Check if data was found ───
                IF v_data IS NULL THEN
                    RETURN json_build_object(
                        'status', 'FAILURE',
                        'message', 'Upgrade requisition not found',
                        'data', NULL::JSON,
                        'success', FALSE
                    );
                END IF;

                -- ─── Return result ───
                RETURN json_build_object(
                    'status', 'SUCCESS',
                    'message', 'Upgrade requisition fetched successfully',
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
        DB::unprepared('DROP FUNCTION IF EXISTS get_upgrade_requisition_details(BIGINT, BIGINT);');
    }
};
