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
                    EXECUTE format('DROP ROUTINE %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION calculate_supplier_rating(
                p_supplier_id BIGINT,
                p_event_type TEXT,
                p_event_payload JSONB,
                p_tenant_id BIGINT
            )
            RETURNS VOID
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
                v_asset_count INT := 0;

                v_event_score_diff NUMERIC := 0;
                v_event_impact_percent NUMERIC := 0;
            BEGIN
                -- Current asset count
                SELECT asset_count INTO v_asset_count
                FROM supplier_asset_counters
                WHERE supplier_id = p_supplier_id AND tenant_id = p_tenant_id;
                
                v_asset_count := COALESCE(v_asset_count, 0);

                -- Fetch existing scores to preserve them if not recalculated
                SELECT 
                    asset_score,
                    quality_score,
                    response_score,
                    fulfillment_score,
                    cost_score,
                    final_score
                INTO 
                    v_asset_score,
                    v_quality_score,
                    v_response_score,
                    v_fulfillment_score,
                    v_cost_score,
                    v_prev_score
                FROM supplier_rating_summary
                WHERE supplier_id = p_supplier_id AND tenant_id = p_tenant_id;

                v_asset_score       := COALESCE(v_asset_score, 0);
                v_quality_score     := COALESCE(v_quality_score, 0);
                v_response_score    := COALESCE(v_response_score, 0);
                v_fulfillment_score := COALESCE(v_fulfillment_score, 0);
                v_cost_score        := COALESCE(v_cost_score, 0);
                v_prev_score        := COALESCE(v_prev_score, 0);

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
                    AND gs.tenant_id = p_tenant_id;

                     -- If this supplier just took the lead, update global stats

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
                        (COUNT(*) FILTER (WHERE pari.updated_at::DATE <= pa.closing_date)::NUMERIC
                        / NULLIF(COUNT(*),0)) * 100
                    INTO v_response_score
                    FROM procurement_attempt_request_items pari
                    JOIN procurements p ON p.id = pari.procurement_id
                    JOIN procurements_quotation_request_attempts pa ON pa.id = pari.attempted_id
                    WHERE pari.supplier_id = p_supplier_id
                    AND pari.is_receive_quotation = TRUE
                    AND p.tenant_id = p_tenant_id;

                    -- Fulfillment score
                    SELECT
                        LEAST(
                            100,
                            (SUM(available_quantity)::NUMERIC / NULLIF(SUM(requested_quantity),0)) * 100
                        )
                    INTO v_fulfillment_score
                    FROM procurement_attempt_request_items pari
                    JOIN procurements p ON p.id = pari.procurement_id
                    WHERE pari.supplier_id = p_supplier_id
                    AND pari.can_full_fill_requested_quantity = TRUE
                    AND pari.is_receive_quotation = TRUE
                    AND p.tenant_id = p_tenant_id;

                    -- Cost score
                    SELECT
                        GREATEST(
                            0,
                            100 - ABS(
                                (SUM(with_tax_price_per_item * available_quantity) - SUM(expected_budget_per_item * requested_quantity))::NUMERIC
                                / NULLIF(SUM(expected_budget_per_item * requested_quantity),0)
                            ) * 100
                        )
                    INTO v_cost_score
                    FROM procurement_attempt_request_items pari
                    JOIN procurements p ON p.id = pari.procurement_id
                    JOIN procurements_quotation_request_attempts pa ON pa.id = pari.attempted_id
                    WHERE pari.supplier_id = p_supplier_id;
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
                
                -- Calculate Event Impact
                v_event_score_diff := v_final_score - v_prev_score;
                
                IF v_prev_score > 0 THEN
                    v_event_impact_percent := (v_event_score_diff / v_prev_score) * 100;
                ELSIF v_final_score > 0 THEN
                     v_event_impact_percent := 100;
                ELSE
                     v_event_impact_percent := 0;
                END IF;

                /* ===========================
                UPSERT SUMMARY
                ============================*/
                UPDATE supplier_rating_summary
                SET
                    asset_score = v_asset_score,
                    quality_score = v_quality_score,
                    response_score = v_response_score,
                    fulfillment_score = v_fulfillment_score,
                    cost_score = v_cost_score,
                    final_score = v_final_score,
                    star_rating = calc_star_rating(v_final_score),
                    last_calculated_at = NOW()
                WHERE supplier_id = p_supplier_id AND tenant_id = p_tenant_id;

                IF NOT FOUND THEN
                    INSERT INTO supplier_rating_summary (
                        supplier_id,
                        asset_score,
                        quality_score,
                        response_score,
                        fulfillment_score,
                        cost_score,
                        final_score,
                        star_rating,
                        last_calculated_at,
                        tenant_id
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
                        NOW(),
                        p_tenant_id
                    );
                END IF;

                /* ===========================
                EVENT AUDIT
                ============================*/
                INSERT INTO supplier_rating_events (
                    supplier_id,
                    event_type,
                    previous_score,
                    new_score,
                    created_at,
                    tenant_id,
                    event_score,
                    impact_percentage
                )
                VALUES (
                    p_supplier_id,
                    p_event_type,
                    v_prev_score,
                    v_final_score,
                    NOW(),
                    p_tenant_id,
                    v_event_score_diff,
                    v_event_impact_percent
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
                    EXECUTE format('DROP ROUTINE %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
        SQL);
    }
};
