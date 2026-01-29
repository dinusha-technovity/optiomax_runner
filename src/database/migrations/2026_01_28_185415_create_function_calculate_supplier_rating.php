<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
                    WHERE proname = 'calculate_supplier_rating'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE PROCEDURE calculate_supplier_rating(
                p_supplier_id UUID,
                p_event_type TEXT,
                p_event_payload JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_asset_score NUMERIC := 0;
                v_quality_score NUMERIC := 0;
                v_response_score NUMERIC := 0;
                v_fulfillment_score NUMERIC := 0;
                v_cost_score NUMERIC := 0;
                v_final_score NUMERIC := 0;

                v_prev_score NUMERIC := 0;
            BEGIN
                -- Previous score (if exists)
                SELECT final_score
                INTO v_prev_score
                FROM supplier_rating_summary
                WHERE supplier_id = p_supplier_id;

                /* ===========================
                ASSET SCORE
                ============================*/
                IF p_event_type IN ('ASSET_REGISTERED', 'FULL_RECALC') THEN

                    SELECT
                        CASE
                            WHEN gs.highest_asset_count = 0 THEN 0
                            ELSE (sc.asset_count::NUMERIC / gs.highest_asset_count) * 100
                        END
                    INTO v_asset_score
                    FROM supplier_asset_counters sc
                    CROSS JOIN supplier_asset_global_stats gs
                    WHERE sc.supplier_id = p_supplier_id
                    AND gs.id = 1;

                END IF;


                /* ===========================
                QUALITY SCORE
                ============================*/
                IF p_event_type IN ('INCIDENT_REPORTED', 'ASSET_REGISTERED', 'FULL_RECALC') THEN
                SELECT
                    GREATEST(
                        0,
                        100 -
                        (
                            (
                            COUNT(*) FILTER (WHERE i.priority_level = 1) * 4 +
                            COUNT(*) FILTER (WHERE i.priority_level = 2) * 3 +
                            COUNT(*) FILTER (WHERE i.priority_level = 3) * 2 +
                            COUNT(*) FILTER (WHERE i.priority_level = 4) * 1 

                            )::NUMERIC
                            /
                            NULLIF(v_asset_count, 0)
                        ) * 100
                    )
                INTO v_quality_score
                FROM asset_items a
                LEFT JOIN asset_maintenance_incident_reports i
                    ON i.asset = a.id
                WHERE a.supplier = p_supplier_id;
                END IF;

                /* ===========================
                QUOTATION / RESPONSE / COST
                ============================*/
                IF p_event_type IN ('QUOTATION_RESPONDED', 'RFQ_EXPIRED', 'FULL_RECALC') THEN
                    -- Response score
                    SELECT
                        (COUNT(*) FILTER (WHERE responded_at <= sla_deadline)::NUMERIC
                        / NULLIF(COUNT(*),0)) * 100
                    INTO v_response_score
                    FROM quotations
                    WHERE supplier_id = p_supplier_id;

                    -- Fulfillment score
                    SELECT
                        LEAST(
                            100,
                            (SUM(available_qty)::NUMERIC / NULLIF(SUM(requested_qty),0)) * 100
                        )
                    INTO v_fulfillment_score
                    FROM quotation_items qi
                    JOIN quotations q ON q.id = qi.quotation_id
                    WHERE q.supplier_id = p_supplier_id;

                    -- Cost score
                    SELECT
                        GREATEST(
                            0,
                            100 - ABS(
                                (SUM(withtax_price) - SUM(expected_price))
                                / NULLIF(SUM(expected_price),0)
                            ) * 100
                        )
                    INTO v_cost_score
                    FROM quotation_items qi
                    JOIN quotations q ON q.id = qi.quotation_id
                    WHERE q.supplier_id = p_supplier_id;
                END IF;

                /* ===========================
                FINAL SCORE
                ============================*/
                v_final_score :=
                    (COALESCE(v_asset_score,0) * 0.10) +
                    (COALESCE(v_quality_score,0) * 0.30) +
                    (COALESCE(v_response_score,0) * 0.20) +
                    (COALESCE(v_fulfillment_score,0) * 0.20) +
                    (COALESCE(v_cost_score,0) * 0.20);

                /* ===========================
                UPSERT SUMMARY
                ============================*/
                INSERT INTO supplier_rating_summary (
                    supplier_id,
                    asset_score,
                    quality_score,
                    response_score,
                    fulfillment_score,
                    cost_score,
                    final_score,
                    star_rating,
                    last_calculated_at
                )
                VALUES (
                    p_supplier_id,
                    v_asset_score,
                    v_quality_score,
                    v_response_score,
                    v_fulfillment_score,
                    v_cost_score,
                    v_final_score,
                    calc_star_rating(v_final_score),
                    NOW()
                )
                ON CONFLICT (supplier_id)
                DO UPDATE SET
                    asset_score = EXCLUDED.asset_score,
                    quality_score = EXCLUDED.quality_score,
                    response_score = EXCLUDED.response_score,
                    fulfillment_score = EXCLUDED.fulfillment_score,
                    cost_score = EXCLUDED.cost_score,
                    final_score = EXCLUDED.final_score,
                    star_rating = EXCLUDED.star_rating,
                    last_calculated_at = NOW();

                /* ===========================
                EVENT AUDIT
                ============================*/
                INSERT INTO supplier_rating_events (
                    id,
                    supplier_id,
                    event_type,
                    previous_score,
                    new_score,
                    created_at
                )
                VALUES (
                    gen_random_uuid(),
                    p_supplier_id,
                    p_event_type,
                    v_prev_score,
                    v_final_score,
                    NOW()
                );

            END;
            $$;
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
                    WHERE proname = 'calculate_supplier_rating'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
        SQL);
    }
};
