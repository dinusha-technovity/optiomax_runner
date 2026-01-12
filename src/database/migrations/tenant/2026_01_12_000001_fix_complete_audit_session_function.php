<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Fix complete_audit_session function - removes condition_status column
     * and fixes ON CONFLICT error by using explicit check
     */
    public function up(): void
    {
        // Drop existing function with all possible signatures
        DB::unprepared('DROP FUNCTION IF EXISTS complete_audit_session(BIGINT, TEXT, BOOLEAN, TEXT, DATE, BIGINT, BIGINT) CASCADE;');
        
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION complete_audit_session(
                IN p_session_id BIGINT,
                IN p_remarks TEXT,
                IN p_follow_up_required BOOLEAN,
                IN p_follow_up_notes TEXT,
                IN p_follow_up_due_date DATE,
                IN p_auditor_id BIGINT,
                IN p_tenant_id BIGINT
            )
            RETURNS JSON
            LANGUAGE plpgsql
            AS $BODY$
            DECLARE
                v_asset_item_id BIGINT;
                v_variables_scored INT;
                v_physical_avg DECIMAL(5,2);
                v_system_avg DECIMAL(5,2);
                v_compliance_avg DECIMAL(5,2);
                v_risk_avg DECIMAL(5,2);
                v_final_score DECIMAL(5,2);
                v_existing_score_id BIGINT;
            BEGIN
                -- Get asset_item_id from session
                SELECT asset_item_id INTO v_asset_item_id
                FROM asset_items_audit_sessions
                WHERE id = p_session_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

                IF v_asset_item_id IS NULL THEN
                    RETURN json_build_object(
                        'success', false,
                        'message', 'Session not found or does not belong to tenant'
                    );
                END IF;

                -- Count scored variables
                SELECT COUNT(*) INTO v_variables_scored
                FROM asset_items_audited_record
                WHERE asset_items_audit_sessions_id = p_session_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

                IF v_variables_scored = 0 THEN
                    RETURN json_build_object(
                        'success', false,
                        'message', 'No audit scores recorded for this session'
                    );
                END IF;

                -- Calculate category averages (score is VARCHAR, cast to DECIMAL)
                -- Match exact names from asset_audit_variable_type table:
                -- 'Physical Condition', 'System / Operational Condition', 'Compliance & Usage', 'Risk & Replacement Need'
                SELECT 
                    AVG(CASE WHEN vt.name = 'Physical Condition' THEN CAST(r.score AS DECIMAL) ELSE NULL END),
                    AVG(CASE WHEN vt.name = 'System / Operational Condition' THEN CAST(r.score AS DECIMAL) ELSE NULL END),
                    AVG(CASE WHEN vt.name = 'Compliance & Usage' THEN CAST(r.score AS DECIMAL) ELSE NULL END),
                    AVG(CASE WHEN vt.name = 'Risk & Replacement Need' THEN CAST(r.score AS DECIMAL) ELSE NULL END)
                INTO v_physical_avg, v_system_avg, v_compliance_avg, v_risk_avg
                FROM asset_items_audited_record r
                JOIN asset_audit_variable v ON r.asset_audit_variable_id = v.id
                JOIN asset_audit_variable_type vt ON v.asset_audit_variable_type_id = vt.id
                WHERE r.asset_items_audit_sessions_id = p_session_id
                AND r.tenant_id = p_tenant_id
                AND r.deleted_at IS NULL;

                -- Calculate final weighted score (scale 1-5 to 0-100)
                -- Physical 30%, System 30%, Compliance 20%, Risk 20%
                v_final_score := COALESCE(v_physical_avg * 20 * 0.30, 0) + 
                                 COALESCE(v_system_avg * 20 * 0.30, 0) + 
                                 COALESCE(v_compliance_avg * 20 * 0.20, 0) + 
                                 COALESCE(v_risk_avg * 20 * 0.20, 0);

                -- Check if score record already exists
                SELECT id INTO v_existing_score_id
                FROM asset_items_audit_score
                WHERE asset_items_audit_sessions_id = p_session_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL
                LIMIT 1;

                IF v_existing_score_id IS NOT NULL THEN
                    -- Update existing score
                    UPDATE asset_items_audit_score
                    SET
                        final_score = v_final_score,
                        physical_condition_score = COALESCE(v_physical_avg * 20, 0),
                        system_or_operational_condition_score = COALESCE(v_system_avg * 20, 0),
                        compliance_and_usage_score = COALESCE(v_compliance_avg * 20, 0),
                        risk_and_replacement_need_score = COALESCE(v_risk_avg * 20, 0),
                        updated_at = NOW()
                    WHERE id = v_existing_score_id;
                ELSE
                    -- Insert new score (without condition_status column)
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
                        v_asset_item_id,
                        p_session_id,
                        v_final_score,
                        COALESCE(v_physical_avg * 20, 0),
                        COALESCE(v_system_avg * 20, 0),
                        COALESCE(v_compliance_avg * 20, 0),
                        COALESCE(v_risk_avg * 20, 0),
                        p_tenant_id,
                        true,
                        NOW(),
                        NOW()
                    );
                END IF;

                -- Update session status to completed
                UPDATE asset_items_audit_sessions
                SET
                    audit_status = 'completed',
                    remarks = p_remarks,
                    follow_up_required = p_follow_up_required,
                    follow_up_notes = p_follow_up_notes,
                    follow_up_due_date = p_follow_up_due_date,
                    audit_completed_at = NOW(),
                    updated_at = NOW()
                WHERE id = p_session_id;

                -- Return success response
                RETURN json_build_object(
                    'success', true,
                    'message', 'Audit session completed successfully',
                    'session_id', p_session_id,
                    'final_score', v_final_score,
                    'variables_scored', v_variables_scored
                );
            END;
            $BODY$
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS complete_audit_session(BIGINT, TEXT, BOOLEAN, TEXT, DATE, BIGINT, BIGINT) CASCADE;');
    }
};