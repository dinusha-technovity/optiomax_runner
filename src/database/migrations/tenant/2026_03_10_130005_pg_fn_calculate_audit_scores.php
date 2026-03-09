<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * PG function: calculate_and_store_audit_scores
     *
     * Aggregates scores from asset_items_audited_record by variable type category,
     * applies weighted formula (Physical 30% + System 30% + Compliance 20% + Risk 20%),
     * then upserts the result into asset_items_audit_score.
     *
     * Score normalisation: raw score 1-5  →  (score / 5.0) * 100  →  0-100 scale
     * Grade thresholds : A>=90, B>=80, C>=70, D>=60, F<60
     * Passing threshold: >= 60
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION calculate_and_store_audit_scores(
            p_asset_item_audit_session_id BIGINT,
            p_tenant_id                   BIGINT
        )
        RETURNS TABLE (
            status                TEXT,
            message               TEXT,
            final_score           DECIMAL(5,2),
            physical_score        DECIMAL(5,2),
            system_score          DECIMAL(5,2),
            compliance_score      DECIMAL(5,2),
            risk_score            DECIMAL(5,2),
            grade                 TEXT,
            is_passing            BOOLEAN,
            total_variables_scored INT,
            completion_percentage DECIMAL(5,2)
        )
        LANGUAGE plpgsql AS $$
        DECLARE
            v_physical_avg   DECIMAL(5,2);
            v_system_avg     DECIMAL(5,2);
            v_compliance_avg DECIMAL(5,2);
            v_risk_avg       DECIMAL(5,2);
            v_final_score    DECIMAL(5,2) := 0;
            v_grade          TEXT;
            v_is_passing     BOOLEAN;
            v_total_scored   INT          := 0;
            v_total_possible INT          := 0;
            v_completion_pct DECIMAL(5,2) := 0;
            v_weighted_sum   DECIMAL(5,2) := 0;
            v_weight_sum     DECIMAL(5,2) := 0;
            v_previous_score DECIMAL(5,2);
            v_score_id       BIGINT;
            v_asset_item_id  BIGINT;
        BEGIN
            -- Validate that the audit session record exists
            IF NOT EXISTS (
                SELECT 1 FROM asset_items_audit_sessions
                WHERE id         = p_asset_item_audit_session_id
                  AND tenant_id  = p_tenant_id
                  AND deleted_at IS NULL
            ) THEN
                RETURN QUERY SELECT
                    'FAILURE'::TEXT, 'Audit session record not found'::TEXT,
                    NULL::DECIMAL(5,2), NULL::DECIMAL(5,2), NULL::DECIMAL(5,2),
                    NULL::DECIMAL(5,2), NULL::DECIMAL(5,2), NULL::TEXT, NULL::BOOLEAN,
                    0::INT, 0::DECIMAL(5,2);
                RETURN;
            END IF;

            SELECT asset_item_id
            INTO   v_asset_item_id
            FROM   asset_items_audit_sessions
            WHERE  id = p_asset_item_audit_session_id;

            -- Count scored records
            SELECT COUNT(*)
            INTO   v_total_scored
            FROM   asset_items_audited_record
            WHERE  asset_item_audit_session_id = p_asset_item_audit_session_id
              AND  deleted_at IS NULL;

            -- Count total possible active variables for this tenant
            SELECT COUNT(*)
            INTO   v_total_possible
            FROM   asset_audit_variable
            WHERE  (tenant_id = p_tenant_id OR tenant_id IS NULL)
              AND  deleted_at IS NULL
              AND  is_active   = TRUE;

            IF v_total_possible > 0 THEN
                v_completion_pct := ROUND((v_total_scored::DECIMAL / v_total_possible) * 100, 2);
            ELSE
                v_completion_pct := CASE WHEN v_total_scored > 0 THEN 100.00 ELSE 0.00 END;
            END IF;

            IF v_total_scored = 0 THEN
                RETURN QUERY SELECT
                    'SUCCESS'::TEXT, 'No scores submitted yet'::TEXT,
                    0.00::DECIMAL(5,2), NULL::DECIMAL(5,2), NULL::DECIMAL(5,2),
                    NULL::DECIMAL(5,2), NULL::DECIMAL(5,2), 'F'::TEXT, FALSE::BOOLEAN,
                    0::INT, 0.00::DECIMAL(5,2);
                RETURN;
            END IF;

            -- Per-category averages; variable type matched by keyword in the type name.
            -- score 1-5 is normalised to 0-100 using: (score / 5.0) * 100
            SELECT
                AVG(CASE WHEN LOWER(avt.name) LIKE '%physical%'
                              THEN (r.score::DECIMAL / 5.0) * 100 END),
                AVG(CASE WHEN LOWER(avt.name) LIKE '%system%'
                           OR LOWER(avt.name) LIKE '%functional%'
                              THEN (r.score::DECIMAL / 5.0) * 100 END),
                AVG(CASE WHEN LOWER(avt.name) LIKE '%compli%'
                              THEN (r.score::DECIMAL / 5.0) * 100 END),
                AVG(CASE WHEN LOWER(avt.name) LIKE '%risk%'
                              THEN (r.score::DECIMAL / 5.0) * 100 END)
            INTO v_physical_avg, v_system_avg, v_compliance_avg, v_risk_avg
            FROM asset_items_audited_record     r
            JOIN asset_audit_variable           av  ON av.id  = r.asset_audit_variable_id
            JOIN asset_audit_variable_type      avt ON avt.id = av.asset_audit_variable_type_id
            WHERE r.asset_item_audit_session_id = p_asset_item_audit_session_id
              AND r.deleted_at IS NULL;

            -- Build weighted sum; re-normalise if not all categories are present
            IF v_physical_avg IS NOT NULL THEN
                v_weighted_sum := v_weighted_sum + (v_physical_avg * 0.30);
                v_weight_sum   := v_weight_sum   + 0.30;
            END IF;
            IF v_system_avg IS NOT NULL THEN
                v_weighted_sum := v_weighted_sum + (v_system_avg * 0.30);
                v_weight_sum   := v_weight_sum   + 0.30;
            END IF;
            IF v_compliance_avg IS NOT NULL THEN
                v_weighted_sum := v_weighted_sum + (v_compliance_avg * 0.20);
                v_weight_sum   := v_weight_sum   + 0.20;
            END IF;
            IF v_risk_avg IS NOT NULL THEN
                v_weighted_sum := v_weighted_sum + (v_risk_avg * 0.20);
                v_weight_sum   := v_weight_sum   + 0.20;
            END IF;

            -- If no category keyword matched, fall back to a simple overall average
            IF v_weight_sum = 0 THEN
                SELECT ROUND(AVG((r.score::DECIMAL / 5.0) * 100), 2)
                INTO   v_final_score
                FROM   asset_items_audited_record r
                WHERE  r.asset_item_audit_session_id = p_asset_item_audit_session_id
                  AND  r.deleted_at IS NULL;
                v_final_score := COALESCE(v_final_score, 0);
            ELSE
                -- Divide by v_weight_sum to re-scale when some categories are absent
                v_final_score := ROUND(v_weighted_sum / v_weight_sum, 2);
            END IF;

            -- Grade
            v_grade := CASE
                WHEN v_final_score >= 90 THEN 'A'
                WHEN v_final_score >= 80 THEN 'B'
                WHEN v_final_score >= 70 THEN 'C'
                WHEN v_final_score >= 60 THEN 'D'
                ELSE 'F'
            END;

            v_is_passing := v_final_score >= 60;

            -- Keep the previous score for change tracking
            SELECT s.final_score
            INTO   v_previous_score
            FROM   asset_items_audit_score s
            WHERE  s.asset_item_audit_session_id = p_asset_item_audit_session_id
              AND  s.deleted_at IS NULL
            LIMIT  1;

            -- Upsert the aggregate score record
            SELECT id INTO v_score_id
            FROM   asset_items_audit_score
            WHERE  asset_item_audit_session_id = p_asset_item_audit_session_id
              AND  deleted_at IS NULL
            LIMIT  1;

            IF v_score_id IS NULL THEN
                INSERT INTO asset_items_audit_score (
                    asset_item_audit_session_id,
                    asset_item_id,
                    physical_score, system_score, compliance_score, risk_score,
                    final_score, grade,
                    total_variables_scored, total_possible_variables, completion_percentage,
                    previous_score, score_change,
                    is_passing, passing_threshold,
                    calculated_at,
                    tenant_id, isactive, created_at, updated_at
                ) VALUES (
                    p_asset_item_audit_session_id,
                    v_asset_item_id,
                    v_physical_avg, v_system_avg, v_compliance_avg, v_risk_avg,
                    v_final_score, v_grade,
                    v_total_scored, v_total_possible, v_completion_pct,
                    NULL, NULL,
                    v_is_passing, 60.00,
                    NOW(),
                    p_tenant_id, TRUE, NOW(), NOW()
                );
            ELSE
                UPDATE asset_items_audit_score
                SET
                    physical_score           = v_physical_avg,
                    system_score             = v_system_avg,
                    compliance_score         = v_compliance_avg,
                    risk_score               = v_risk_avg,
                    final_score              = v_final_score,
                    grade                    = v_grade,
                    total_variables_scored   = v_total_scored,
                    total_possible_variables = v_total_possible,
                    completion_percentage    = v_completion_pct,
                    previous_score           = v_previous_score,
                    score_change             = v_final_score - COALESCE(v_previous_score, v_final_score),
                    is_passing               = v_is_passing,
                    calculated_at            = NOW(),
                    updated_at               = NOW()
                WHERE id = v_score_id;
            END IF;

            RETURN QUERY SELECT
                'SUCCESS'::TEXT,
                'Scores calculated successfully'::TEXT,
                v_final_score,
                v_physical_avg,
                v_system_avg,
                v_compliance_avg,
                v_risk_avg,
                v_grade,
                v_is_passing,
                v_total_scored,
                v_completion_pct;
        END;
        $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS calculate_and_store_audit_scores(BIGINT, BIGINT)');
    }
};
