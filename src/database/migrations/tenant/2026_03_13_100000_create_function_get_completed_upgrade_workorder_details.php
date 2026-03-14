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
                    WHERE proname = 'get_completed_upgrade_workorder_details'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION get_completed_upgrade_workorder_details(
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

                -- ─── Fetch work order details for completed upgrade requisition ───
                SELECT row_to_json(t) INTO v_data
                FROM (
                    SELECT
                        wo.id AS work_order_id,
                        wo.work_order_number,
                        wo.title,
                        wo.scope_of_work,
                        wo.actual_work_order_start,
                        wo.actual_work_order_end,
                        wo.status AS work_order_status,
                        wo.completion_note,
                        wo.technician_comment,
                        wo.created_at AS work_order_created_at,
                        wo.updated_at AS work_order_updated_at,
                        
                        -- Technician details
                        CASE
                            WHEN wo.technician_id IS NOT NULL THEN
                                json_build_object(
                                    'id', tech.id,
                                    'user_name', tech.user_name,
                                    'name', tech.name,
                                    'email', tech.email,
                                    'profile_image', tech.profile_image
                                )
                            ELSE NULL
                        END AS technician,
                        
                        -- Asset Requisition details
                        ar.id AS asset_requisition_id,
                        ar.requisition_id,
                        ar.requisition_date,
                        
                        -- Upgrade Requisition status
                        uar.status AS upgrade_requisition_status,
                        uar.expected_date,
                        
                        -- Asset details
                        json_build_object(
                            'id', ai.id,
                            'asset_name', a.name,
                            'model_number', ai.model_number,
                            'serial_number', ai.serial_number,
                            'asset_tag', ai.asset_tag
                        ) AS asset

                    FROM upgrade_asset_requisitions uar
                    INNER JOIN asset_requisitions ar
                        ON ar.id = uar.asset_requisition_id
                    INNER JOIN work_orders wo
                        ON wo.asset_requisition_id = ar.id
                    LEFT JOIN users tech
                        ON tech.id = wo.technician_id
                    LEFT JOIN asset_items ai
                        ON ai.id = uar.asset_id AND ai.isactive = TRUE
                    LEFT JOIN assets a
                        ON a.id = ai.asset_id
                        
                    WHERE uar.asset_requisition_id = _asset_requisition_id
                      AND uar.tenant_id = _tenant_id
                      AND uar.deleted_at IS NULL
                      AND wo.tenant_id = _tenant_id
                      AND wo.deleted_at IS NULL
                      AND wo.isactive = TRUE
                    ORDER BY wo.id DESC
                    LIMIT 1
                ) t;

                -- ─── Check if data was found ───
                IF v_data IS NULL THEN
                    RETURN json_build_object(
                        'status', 'FAILURE',
                        'message', 'No work order found for this upgrade requisition',
                        'data', NULL::JSON,
                        'success', FALSE
                    );
                END IF;

                -- ─── Return success response ───
                RETURN json_build_object(
                    'status', 'SUCCESS',
                    'message', 'Work order details retrieved successfully',
                    'data', v_data,
                    'success', TRUE
                );

            EXCEPTION
                WHEN OTHERS THEN
                    RETURN json_build_object(
                        'status', 'ERROR',
                        'message', SQLERRM,
                        'data', NULL::JSON,
                        'success', FALSE
                    );
            END;
            $fn$;

            -- ─── Grant necessary permissions ───
            -- ALTER FUNCTION get_completed_upgrade_workorder_details(BIGINT, BIGINT) OWNER TO postgres;
            -- GRANT EXECUTE ON FUNCTION get_completed_upgrade_workorder_details(BIGINT, BIGINT) TO postgres;

        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP FUNCTION IF EXISTS get_completed_upgrade_workorder_details(BIGINT, BIGINT) CASCADE;
        SQL);
    }
};
