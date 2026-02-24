<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
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
                    WHERE proname = 'get_replace_requisition_recommend_data'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

        CREATE OR REPLACE FUNCTION get_replace_requisition_recommend_data(
            IN _tenant_id     BIGINT,
            IN _asset_requisition_id   BIGINT
        )
        RETURNS JSON
        LANGUAGE plpgsql
        AS $fn$
        DECLARE
            v_result JSON;
        BEGIN

            SELECT json_build_object(
                'id', rrd.id,
                'tenant_id', rrd.tenant_id,
                'asset_requisition_id', rrd.asset_requisition_id,
                'decision_id', rrd.decision_id,
                'asset_id', rrd.asset_id,
                'recommendation_reason', rrd.recommendation_reason,
                'priority_id', rrd.priority_id,
                'priority_name', p.name,
                'estimated_cost', rrd.estimated_cost,
                'suppliers', rrd.suppliers,
                'specifications', rrd.specifications,
                'is_disposal_recommended', rrd.is_disposal_recommended,
                'disposal_recommendation_id', rrd.disposal_recommendation_id,
                'disposal_recommendation_name', dr.name,
                'mode_of_acquisition_id', rrd.mode_of_acquisition_id,
                'mode_of_acquisition_name', moa.name,
                'recommended_by', rrd.recommended_by,
                'recommended_by_name', u.user_name,
                'created_at', rrd.created_at,
                'asset_name', a.name,
                'asset_code', ai.asset_tag,
                'asset_category', ac.name,
                'asset_sub_category', asc_t.name,
                'requisition_number', ar.requisition_id,
                'requisition_status', ar.requisition_status
            )
            INTO v_result
            FROM replace_requisition_recommend_data rrd
            LEFT JOIN asset_requisition_priority_types p ON p.id = rrd.priority_id
            LEFT JOIN disposal_recommendations dr ON dr.id = rrd.disposal_recommendation_id
            LEFT JOIN asset_requisition_availability_types moa ON moa.id = rrd.mode_of_acquisition_id
            LEFT JOIN users u ON u.id = rrd.recommended_by
            LEFT JOIN asset_items ai ON ai.id = rrd.asset_id
            LEFT JOIN assets a ON a.id = ai.asset_id
            LEFT JOIN asset_categories ac ON ac.id = a.category
            LEFT JOIN asset_sub_categories asc_t ON asc_t.id = a.sub_category
            LEFT JOIN asset_requisitions ar ON ar.id = rrd.asset_requisition_id
            WHERE rrd.asset_requisition_id = _asset_requisition_id
              AND rrd.tenant_id = _tenant_id
              AND rrd.is_active = TRUE
              AND rrd.deleted_at IS NULL
            ORDER BY rrd.id DESC
            LIMIT 1;

            IF v_result IS NULL THEN
                v_result := '{}';
            END IF;

            RETURN v_result;

        END;
        $fn$;
        SQL);
    }

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
                    WHERE proname = 'get_replace_requisition_recommend_data'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
        SQL);
    }
};
