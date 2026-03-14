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
                    WHERE proname = 'get_asset_requisition_status_flow'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION get_asset_requisition_status_flow(
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
                        'status',  'FAILURE',
                        'message', 'Invalid asset requisition ID',
                        'data',    NULL::JSON,
                        'success', FALSE
                    );
                END IF;

                IF _tenant_id IS NULL OR _tenant_id <= 0 THEN
                    RETURN json_build_object(
                        'status',  'FAILURE',
                        'message', 'Invalid tenant ID',
                        'data',    NULL::JSON,
                        'success', FALSE
                    );
                END IF;

                -- ─── Verify the requisition exists for this tenant ───
                IF NOT EXISTS (
                    SELECT 1
                    FROM asset_requisitions ar
                    WHERE ar.id        = _asset_requisition_id
                      AND ar.tenant_id = _tenant_id
                      AND ar.deleted_at IS NULL
                ) THEN
                    RETURN json_build_object(
                        'status',  'FAILURE',
                        'message', 'Asset requisition not found',
                        'data',    NULL::JSON,
                        'success', FALSE
                    );
                END IF;

                -- ─── Build ordered log flow ───
                SELECT json_agg(
                    json_build_object(
                        'log_id',          arl.id,
                        'log_type_id',     arl.log_type_id,
                        'log_code',        arlt.code,
                        'log_name',        arlt.name,
                        'log_description', arlt.description,
                        'action_at',       TO_CHAR(arl.action_at, 'YYYY-MM-DD"T"HH24:MI:SS"Z"'),
                        'payload',         arl.payload,
                        'action_by', CASE
                            WHEN arl.action_by IS NOT NULL THEN
                                json_build_object(
                                    'id',            u.id,
                                    'user_name',     u.user_name,
                                    'name',          u.name,
                                    'email',         u.email,
                                    'profile_image', u.profile_image
                                )
                            ELSE NULL
                        END
                    )
                    ORDER BY arl.action_at ASC, arl.id ASC
                ) INTO v_data
                FROM asset_requisition_logs arl
                INNER JOIN asset_requisition_log_types arlt
                    ON arlt.id = arl.log_type_id
                   AND arlt.is_active = TRUE
                LEFT JOIN users u
                    ON u.id = arl.action_by
                WHERE arl.asset_requisition_id = _asset_requisition_id
                  AND arl.tenant_id            = _tenant_id
                  AND arl.is_active            = TRUE
                  AND arl.deleted_at           IS NULL;

                RETURN json_build_object(
                    'status',  'SUCCESS',
                    'message', 'Status flow retrieved successfully',
                    'data',    COALESCE(v_data, '[]'::JSON),
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
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_requisition_status_flow(BIGINT, BIGINT) CASCADE;');
    }
};
