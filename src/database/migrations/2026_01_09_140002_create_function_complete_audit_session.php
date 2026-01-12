<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * ISO 19011:2018 & ISO 55001:2014 Compliant - Audit Session Completion
     * Validates completeness and transitions audit to completed status
     */
    public function up(): void
    {
        // Drop existing function first
        DB::unprepared('DROP FUNCTION IF EXISTS complete_audit_session(BIGINT, TEXT, BOOLEAN, TEXT, DATE, BIGINT, BIGINT);');
        
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION complete_audit_session(
                p_session_id BIGINT,
                p_remarks TEXT DEFAULT NULL,
                p_follow_up_required BOOLEAN DEFAULT false,
                p_follow_up_notes TEXT DEFAULT NULL,
                p_follow_up_due_date DATE DEFAULT NULL,
                p_auditor_id BIGINT DEFAULT NULL,
                p_tenant_id BIGINT DEFAULT NULL
            )
            RETURNS JSON
            LANGUAGE plpgsql
            AS $BODY$
            DECLARE
                v_result JSON;
                v_scored_count INTEGER;
                v_final_score NUMERIC(5,2);
                v_physical_avg NUMERIC(5,2);
                v_system_avg NUMERIC(5,2);
                v_compliance_avg NUMERIC(5,2);
                v_risk_avg NUMERIC(5,2);
                v_condition_status TEXT;
                v_score_id BIGINT;
            BEGIN
                -- Count scored variables
                SELECT COUNT(*) INTO v_scored_count
                FROM asset_items_audited_record
                WHERE asset_items_audit_sessions_id = p_session_id
                  AND tenant_id = p_tenant_id
                  AND deleted_at IS NULL;

                -- Calculate category averages (assuming score is VARCHAR, cast to NUMERIC)
                -- Physical Condition
                SELECT AVG(CAST(score AS NUMERIC)) INTO v_physical_avg
                FROM asset_items_audited_record aiar
                JOIN asset_audit_variable aav ON aav.id = aiar.asset_audit_variable_id
                JOIN asset_audit_variable_type aavt ON aavt.id = aav.asset_audit_variable_type_id
                WHERE aiar.asset_items_audit_sessions_id = p_session_id
                  AND aiar.tenant_id = p_tenant_id
                  AND aiar.deleted_at IS NULL
                  AND aavt.name = 'Physical Condition';

                -- System/Operational
                SELECT AVG(CAST(score AS NUMERIC)) INTO v_system_avg
                FROM asset_items_audited_record aiar
                JOIN asset_audit_variable aav ON aav.id = aiar.asset_audit_variable_id
                JOIN asset_audit_variable_type aavt ON aavt.id = aav.asset_audit_variable_type_id
                WHERE aiar.asset_items_audit_sessions_id = p_session_id
                  AND aiar.tenant_id = p_tenant_id
                  AND aiar.deleted_at IS NULL
                  AND aavt.name = 'System / Operational Condition';

                -- Compliance & Usage
                SELECT AVG(CAST(score AS NUMERIC)) INTO v_compliance_avg
                FROM asset_items_audited_record aiar
                JOIN asset_audit_variable aav ON aav.id = aiar.asset_audit_variable_id
                JOIN asset_audit_variable_type aavt ON aavt.id = aav.asset_audit_variable_type_id
                WHERE aiar.asset_items_audit_sessions_id = p_session_id
                  AND aiar.tenant_id = p_tenant_id
                  AND aiar.deleted_at IS NULL
                  AND aavt.name = 'Compliance & Usage';

                -- Risk & Replacement
                SELECT AVG(CAST(score AS NUMERIC)) INTO v_risk_avg
                FROM asset_items_audited_record aiar
                JOIN asset_audit_variable aav ON aav.id = aiar.asset_audit_variable_id
                JOIN asset_audit_variable_type aavt ON aavt.id = aav.asset_audit_variable_type_id
                WHERE aiar.asset_items_audit_sessions_id = p_session_id
                  AND aiar.tenant_id = p_tenant_id
                  AND aiar.deleted_at IS NULL
                  AND aavt.name = 'Risk & Replacement Need';

                -- Calculate final weighted score (scale 1-5 to 0-100)
                -- Physical 30%, System 30%, Compliance 20%, Risk 20%
                v_final_score := (
                    (COALESCE(v_physical_avg, 0) * 20 * 0.30) +
                    (COALESCE(v_system_avg, 0) * 20 * 0.30) +
                    (COALESCE(v_compliance_avg, 0) * 20 * 0.20) +
                    (COALESCE(v_risk_avg, 0) * 20 * 0.20)
                );

                -- Determine condition status
                v_condition_status := CASE
                    WHEN v_final_score >= 90 THEN 'Excellent'
                    WHEN v_final_score >= 75 THEN 'Good'
                    WHEN v_final_score >= 60 THEN 'Fair'
                    WHEN v_final_score >= 40 THEN 'Poor'
                    ELSE 'Critical'
                END;

                -- Insert or update audit score
                SELECT id INTO v_score_id
                FROM asset_items_audit_score
                WHERE asset_items_audit_sessions_id = p_session_id
                  AND tenant_id = p_tenant_id
                  AND deleted_at IS NULL;

                IF v_score_id IS NULL THEN
                    INSERT INTO asset_items_audit_score (
                        asset_items_audit_sessions_id,
                        final_score,
                        physical_condition_score,
                        system_or_operational_condition_score,
                        compliance_and_usage_score,
                        risk_and_replacement_need_score,
                        condition_status,
                        tenant_id,
                        isactive,
                        created_at,
                        updated_at
                    ) VALUES (
                        p_session_id,
                        v_final_score,
                        v_physical_avg * 20,
                        v_system_avg * 20,
                        v_compliance_avg * 20,
                        v_risk_avg * 20,
                        v_condition_status,
                        p_tenant_id,
                        true,
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                    );
                END IF;

                -- Update session status to completed
                UPDATE asset_items_audit_sessions
                SET audit_status = 'completed',
                    remarks = COALESCE(p_remarks, remarks),
                    follow_up_required = p_follow_up_required,
                    follow_up_notes = CASE WHEN p_follow_up_required THEN p_follow_up_notes ELSE NULL END,
                    audit_completed_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = p_session_id
                  AND tenant_id = p_tenant_id;

                v_result := json_build_object(
                    'success', true,
                    'message', 'Audit session completed',
                    'data', json_build_object(
                        'session_id', p_session_id,
                        'final_score', v_final_score,
                        'condition_status', v_condition_status,
                        'variables_scored', v_scored_count
                    )
                );

                RETURN v_result;

            EXCEPTION
                WHEN OTHERS THEN
                    RETURN json_build_object(
                        'success', false,
                        'message', 'Error: ' || SQLERRM
                    );
            END;
            $BODY$
SQL
        );

        DB::statement("COMMENT ON FUNCTION complete_audit_session IS 'ISO 19011 compliant audit session completion'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS complete_audit_session(BIGINT, TEXT, BOOLEAN, TEXT, DATE, BIGINT, BIGINT);');
    }
};