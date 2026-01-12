<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Function to calculate asset audit scores based on the scoring framework
     * Weights: Physical 30%, System 30%, Maintenance 20%, Risk 20%
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
                WHERE proname = 'calculate_and_store_audit_scores'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION calculate_and_store_audit_scores(
            IN p_session_id BIGINT,
            IN p_asset_item_id BIGINT,
            IN p_tenant_id BIGINT,
            IN p_current_time TIMESTAMPTZ,
            IN p_causer_id BIGINT DEFAULT NULL,
            IN p_causer_name TEXT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            final_score DECIMAL(5,2),
            physical_score DECIMAL(5,2),
            system_score DECIMAL(5,2),
            compliance_score DECIMAL(5,2),
            risk_score DECIMAL(5,2),
            condition_status TEXT,
            recommendation TEXT
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_physical_avg DECIMAL(5,2) := 0;
            v_system_avg DECIMAL(5,2) := 0;
            v_compliance_avg DECIMAL(5,2) := 0;
            v_risk_avg DECIMAL(5,2) := 0;
            v_physical_weighted DECIMAL(5,2) := 0;
            v_system_weighted DECIMAL(5,2) := 0;
            v_compliance_weighted DECIMAL(5,2) := 0;
            v_risk_weighted DECIMAL(5,2) := 0;
            v_final_score DECIMAL(5,2) := 0;
            v_condition_status TEXT;
            v_recommendation TEXT;
            v_score_id BIGINT;
            v_variable_type_physical BIGINT;
            v_variable_type_system BIGINT;
            v_variable_type_compliance BIGINT;
            v_variable_type_risk BIGINT;
            v_count INT;
            new_record JSONB;
        BEGIN
            -- Validate inputs
            IF p_session_id IS NULL OR p_session_id = 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 
                    'Session ID is required'::TEXT,
                    NULL::DECIMAL(5,2), NULL::DECIMAL(5,2), NULL::DECIMAL(5,2), 
                    NULL::DECIMAL(5,2), NULL::DECIMAL(5,2), NULL::TEXT, NULL::TEXT;
                RETURN;
            END IF;

            IF p_tenant_id IS NULL OR p_tenant_id = 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 
                    'Tenant ID is required'::TEXT,
                    NULL::DECIMAL(5,2), NULL::DECIMAL(5,2), NULL::DECIMAL(5,2), 
                    NULL::DECIMAL(5,2), NULL::DECIMAL(5,2), NULL::TEXT, NULL::TEXT;
                RETURN;
            END IF;

            -- Verify session exists
            IF NOT EXISTS (
                SELECT 1 FROM asset_items_audit_sessions 
                WHERE id = p_session_id 
                AND tenant_id = p_tenant_id 
                AND deleted_at IS NULL
            ) THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 
                    'Audit session not found'::TEXT,
                    NULL::DECIMAL(5,2), NULL::DECIMAL(5,2), NULL::DECIMAL(5,2), 
                    NULL::DECIMAL(5,2), NULL::DECIMAL(5,2), NULL::TEXT, NULL::TEXT;
                RETURN;
            END IF;

            -- Get variable type IDs
            SELECT id INTO v_variable_type_physical 
            FROM asset_audit_variable_type 
            WHERE name = 'Physical Condition' AND deleted_at IS NULL LIMIT 1;

            SELECT id INTO v_variable_type_system 
            FROM asset_audit_variable_type 
            WHERE name = 'System / Operational Condition' AND deleted_at IS NULL LIMIT 1;

            SELECT id INTO v_variable_type_compliance 
            FROM asset_audit_variable_type 
            WHERE name = 'Compliance & Usage' AND deleted_at IS NULL LIMIT 1;

            SELECT id INTO v_variable_type_risk 
            FROM asset_audit_variable_type 
            WHERE name = 'Risk & Replacement Need' AND deleted_at IS NULL LIMIT 1;

            -- Calculate Physical Condition Score (30% weight)
            SELECT 
                AVG(CAST(aiar.score AS DECIMAL(5,2)))
            INTO v_physical_avg
            FROM asset_items_audited_record aiar
            INNER JOIN asset_audit_variable aav ON aav.id = aiar.asset_audit_variable_id
            WHERE aiar.asset_items_audit_sessions_id = p_session_id
                AND aiar.tenant_id = p_tenant_id
                AND aiar.deleted_at IS NULL
                AND aav.asset_audit_variable_type_id = v_variable_type_physical
                AND aav.deleted_at IS NULL;

            v_physical_avg := COALESCE(v_physical_avg, 0);
            v_physical_weighted := v_physical_avg * 20; -- Convert 1-5 scale to 0-100 (multiply by 20)

            -- Calculate System/Operational Score (30% weight)
            SELECT 
                AVG(CAST(aiar.score AS DECIMAL(5,2)))
            INTO v_system_avg
            FROM asset_items_audited_record aiar
            INNER JOIN asset_audit_variable aav ON aav.id = aiar.asset_audit_variable_id
            WHERE aiar.asset_items_audit_sessions_id = p_session_id
                AND aiar.tenant_id = p_tenant_id
                AND aiar.deleted_at IS NULL
                AND aav.asset_audit_variable_type_id = v_variable_type_system
                AND aav.deleted_at IS NULL;

            v_system_avg := COALESCE(v_system_avg, 0);
            v_system_weighted := v_system_avg * 20;

            -- Calculate Compliance & Usage Score (20% weight)
            SELECT 
                AVG(CAST(aiar.score AS DECIMAL(5,2)))
            INTO v_compliance_avg
            FROM asset_items_audited_record aiar
            INNER JOIN asset_audit_variable aav ON aav.id = aiar.asset_audit_variable_id
            WHERE aiar.asset_items_audit_sessions_id = p_session_id
                AND aiar.tenant_id = p_tenant_id
                AND aiar.deleted_at IS NULL
                AND aav.asset_audit_variable_type_id = v_variable_type_compliance
                AND aav.deleted_at IS NULL;

            v_compliance_avg := COALESCE(v_compliance_avg, 0);
            v_compliance_weighted := v_compliance_avg * 20;

            -- Calculate Risk & Replacement Need Score (20% weight)
            SELECT 
                AVG(CAST(aiar.score AS DECIMAL(5,2)))
            INTO v_risk_avg
            FROM asset_items_audited_record aiar
            INNER JOIN asset_audit_variable aav ON aav.id = aiar.asset_audit_variable_id
            WHERE aiar.asset_items_audit_sessions_id = p_session_id
                AND aiar.tenant_id = p_tenant_id
                AND aiar.deleted_at IS NULL
                AND aav.asset_audit_variable_type_id = v_variable_type_risk
                AND aav.deleted_at IS NULL;

            v_risk_avg := COALESCE(v_risk_avg, 0);
            v_risk_weighted := v_risk_avg * 20;

            -- Calculate Final Weighted Score
            -- Formula: (Physical × 0.30) + (System × 0.30) + (Compliance × 0.20) + (Risk × 0.20)
            v_final_score := 
                (v_physical_weighted * 0.30) + 
                (v_system_weighted * 0.30) + 
                (v_compliance_weighted * 0.20) + 
                (v_risk_weighted * 0.20);

            -- Determine Condition Status based on final score
            CASE 
                WHEN v_final_score >= 90 THEN 
                    v_condition_status := 'Excellent';
                    v_recommendation := 'No action required. Continue routine monitoring.';
                WHEN v_final_score >= 70 THEN 
                    v_condition_status := 'Good';
                    v_recommendation := 'Maintain routine maintenance schedule.';
                WHEN v_final_score >= 50 THEN 
                    v_condition_status := 'Fair';
                    v_recommendation := 'Monitor closely and plan for preventive maintenance.';
                WHEN v_final_score >= 30 THEN 
                    v_condition_status := 'Poor';
                    v_recommendation := 'Repair or upgrade required. Schedule maintenance immediately.';
                ELSE 
                    v_condition_status := 'Critical';
                    v_recommendation := 'URGENT: Immediate replacement or major repair required.';
            END CASE;

            -- Check if score record already exists for this session
            SELECT id INTO v_score_id
            FROM asset_items_audit_score
            WHERE asset_items_audit_sessions_id = p_session_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

            IF v_score_id IS NULL THEN
                -- Insert new score record
                INSERT INTO asset_items_audit_score (
                    asset_item_id,
                    asset_items_audit_sessions_id,
                    final_score,
                    physical_condition_score,
                    system_or_operational_condition_score,
                    compliance_and_usage_score,
                    risk_and_replacement_need_score,
                    tenant_id,
                    isactive,
                    created_at,
                    updated_at
                )
                VALUES (
                    p_asset_item_id,
                    p_session_id,
                    v_final_score,
                    v_physical_weighted,
                    v_system_weighted,
                    v_compliance_weighted,
                    v_risk_weighted,
                    p_tenant_id,
                    true,
                    p_current_time,
                    p_current_time
                )
                RETURNING id INTO v_score_id;
            ELSE
                -- Update existing score record
                UPDATE asset_items_audit_score
                SET
                    final_score = v_final_score,
                    physical_condition_score = v_physical_weighted,
                    system_or_operational_condition_score = v_system_weighted,
                    compliance_and_usage_score = v_compliance_weighted,
                    risk_and_replacement_need_score = v_risk_weighted,
                    updated_at = p_current_time
                WHERE id = v_score_id;
            END IF;

            -- Get the score record for logging
            SELECT to_jsonb(s) INTO new_record
            FROM asset_items_audit_score s
            WHERE id = v_score_id;

            -- Log the activity
            BEGIN
                PERFORM log_activity(
                    'calculate_audit_scores',
                    format('Scores calculated for session %s: Final Score %.2f (%s)', 
                           p_session_id, v_final_score, v_condition_status),
                    'asset_items_audit_score',
                    v_score_id,
                    'user',
                    p_causer_id,
                    new_record,
                    p_tenant_id
                );
            EXCEPTION WHEN OTHERS THEN
                -- Continue even if logging fails
            END;

            RETURN QUERY SELECT 
                'SUCCESS'::TEXT,
                format('Scores calculated successfully. Status: %s', v_condition_status)::TEXT,
                v_final_score,
                v_physical_weighted,
                v_system_weighted,
                v_compliance_weighted,
                v_risk_weighted,
                v_condition_status,
                v_recommendation;
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
                WHERE proname = 'calculate_and_store_audit_scores'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;
        SQL);
    }
};
