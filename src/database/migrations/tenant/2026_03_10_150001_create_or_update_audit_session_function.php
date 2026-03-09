<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Function: create_or_update_audit_session - FIXED for actual table structure
     */
    public function up(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS create_or_update_audit_session CASCADE');
        
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION create_or_update_audit_session(
                p_tenant_id BIGINT,
                p_user_id BIGINT,
                p_user_name TEXT,
                p_session_id BIGINT DEFAULT NULL,
                p_session_name VARCHAR DEFAULT NULL,
                p_description TEXT DEFAULT NULL,
                p_audit_period_id BIGINT DEFAULT NULL,
                p_scheduled_date DATE DEFAULT NULL,
                p_actual_start_date DATE DEFAULT NULL,
                p_actual_end_date DATE DEFAULT NULL,
                p_lead_auditor_id BIGINT DEFAULT NULL,
                p_status VARCHAR DEFAULT 'draft',
                p_audit_objectives JSONB DEFAULT NULL,
                p_audit_scope JSONB DEFAULT NULL,
                p_audit_criteria JSONB DEFAULT NULL,
                p_audit_methodology TEXT DEFAULT NULL,
                p_risk_level VARCHAR DEFAULT NULL,
                p_risk_factors JSONB DEFAULT NULL,
                p_audit_conclusion TEXT DEFAULT NULL,
                p_recommendations JSONB DEFAULT NULL,
                p_current_time TIMESTAMPTZ DEFAULT NOW()
            )
            RETURNS JSONB
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_session_id BIGINT;
                v_session_code VARCHAR(50);
                v_is_update BOOLEAN := FALSE;
                v_old_data JSONB;
                v_new_data JSONB;
                v_action_type TEXT;
                v_log_id BIGINT;
                v_existing_count INTEGER;
                v_next_code_num INTEGER;
            BEGIN
                -- Validation: Required fields for create
                IF p_session_id IS NULL THEN
                    IF p_session_name IS NULL OR TRIM(p_session_name) = '' THEN
                        RETURN jsonb_build_object(
                            'status', 'ERROR',
                            'message', 'Session name is required for creating a new session'
                        );
                    END IF;
                    
                    IF p_audit_period_id IS NULL THEN
                        RETURN jsonb_build_object(
                            'status', 'ERROR',
                            'message', 'Audit period ID is required'
                        );
                    END IF;
                    
                    -- Validate date range if both provided
                    IF p_actual_start_date IS NOT NULL AND p_actual_end_date IS NOT NULL THEN
                        IF p_actual_end_date < p_actual_start_date THEN
                            RETURN jsonb_build_object(
                                'status', 'ERROR',
                                'message', 'Actual end date cannot be before start date'
                            );
                        END IF;
                    END IF;
                END IF;
                
                -- Check if updating existing session
                IF p_session_id IS NOT NULL THEN
                    SELECT COUNT(*) INTO v_existing_count
                    FROM audit_sessions
                    WHERE id = p_session_id
                        AND tenant_id = p_tenant_id
                        AND deleted_at IS NULL;

                    IF v_existing_count = 0 THEN
                        RETURN jsonb_build_object(
                            'status', 'ERROR',
                            'message', 'Audit session not found'
                        );
                    END IF;
                    
                    v_is_update := TRUE;
                    
                    -- Capture old data for logging
                    SELECT to_jsonb(a.*) INTO v_old_data
                    FROM audit_sessions a
                    WHERE id = p_session_id;
                END IF;

                IF v_is_update THEN
                    -- UPDATE existing session
                    UPDATE audit_sessions
                    SET
                        session_name = COALESCE(p_session_name, session_name),
                        description = COALESCE(p_description, description),
                        audit_period_id = COALESCE(p_audit_period_id, audit_period_id),
                        scheduled_date = COALESCE(p_scheduled_date, scheduled_date),
                        actual_start_date = COALESCE(p_actual_start_date, actual_start_date),
                        actual_end_date = COALESCE(p_actual_end_date, actual_end_date),
                        lead_auditor_id = COALESCE(p_lead_auditor_id, lead_auditor_id),
                        status = COALESCE(p_status, status),
                        audit_objectives = COALESCE(p_audit_objectives, audit_objectives),
                        audit_scope = COALESCE(p_audit_scope, audit_scope),
                        audit_criteria = COALESCE(p_audit_criteria, audit_criteria),
                        audit_methodology = COALESCE(p_audit_methodology, audit_methodology),
                        risk_level = COALESCE(p_risk_level, risk_level),
                        risk_factors = COALESCE(p_risk_factors, risk_factors),
                        audit_conclusion = COALESCE(p_audit_conclusion, audit_conclusion),
                        recommendations = COALESCE(p_recommendations, recommendations),
                        updated_by = p_user_id,
                        updated_at = p_current_time
                    WHERE id = p_session_id
                        AND tenant_id = p_tenant_id
                    RETURNING id, session_code INTO v_session_id, v_session_code;
                    
                    v_action_type := 'updated';
                    
                    -- Capture new data for logging
                    SELECT to_jsonb(a.*) INTO v_new_data
                    FROM audit_sessions a
                    WHERE id = v_session_id;
                    
                    -- Log activity for update
                    BEGIN
                        v_log_id := log_activity(
                            'audit_session.updated',
                            'Audit session updated: ' || v_session_code || ' by ' || p_user_name,
                            'audit_sessions',
                            v_session_id,
                            'user',
                            p_user_id,
                            jsonb_build_object(
                                'session_code', v_session_code,
                                'session_name', p_session_name,
                                'action', 'update',
                                'old_data', v_old_data,
                                'new_data', v_new_data,
                                'updated_by', p_user_name,
                                'updated_at', p_current_time
                            ),
                            p_tenant_id
                        );
                    EXCEPTION WHEN OTHERS THEN
                        RAISE NOTICE 'Log activity failed: %', SQLERRM;
                    END;
                    
                ELSE
                    -- Generate session code: AS-000001, AS-000002, etc.
                    SELECT COALESCE(MAX(CAST(SUBSTRING(session_code FROM 4) AS INTEGER)), 0) + 1 
                    INTO v_next_code_num
                    FROM audit_sessions
                    WHERE tenant_id = p_tenant_id;
                    
                    v_session_code := 'AS-' || LPAD(v_next_code_num::TEXT, 6, '0');
                    
                    -- INSERT new session
                    INSERT INTO audit_sessions (
                        tenant_id,
                        session_code,
                        session_name,
                        description,
                        audit_period_id,
                        scheduled_date,
                        actual_start_date,
                        actual_end_date,
                        lead_auditor_id,
                        status,
                        audit_objectives,
                        audit_scope,
                        audit_criteria,
                        audit_methodology,
                        risk_level,
                        risk_factors,
                        audit_conclusion,
                        recommendations,
                        created_by,
                        updated_by,
                        isactive,
                        created_at,
                        updated_at
                    ) VALUES (
                        p_tenant_id,
                        v_session_code,
                        p_session_name,
                        p_description,
                        p_audit_period_id,
                        p_scheduled_date,
                        p_actual_start_date,
                        p_actual_end_date,
                        p_lead_auditor_id,
                        p_status,
                        p_audit_objectives,
                        p_audit_scope,
                        p_audit_criteria,
                        p_audit_methodology,
                        p_risk_level,
                        p_risk_factors,
                        p_audit_conclusion,
                        p_recommendations,
                        p_user_id,
                        p_user_id,
                        TRUE,
                        p_current_time,
                        p_current_time
                    ) RETURNING id, session_code INTO v_session_id, v_session_code;
                    
                    v_action_type := 'created';
                    
                    -- Capture new data for logging
                    SELECT to_jsonb(a.*) INTO v_new_data
                    FROM audit_sessions a
                    WHERE id = v_session_id;
                    
                    -- Log activity for create
                    BEGIN
                        v_log_id := log_activity(
                            'audit_session.created',
                            'Audit session created: ' || v_session_code || ' by ' || p_user_name,
                            'audit_sessions',
                            v_session_id,
                            'user',
                            p_user_id,
                            jsonb_build_object(
                                'session_code', v_session_code,
                                'session_name', p_session_name,
                                'action', 'create',
                                'data', v_new_data,
                                'created_by', p_user_name,
                                'created_at', p_current_time
                            ),
                            p_tenant_id
                        );
                    EXCEPTION WHEN OTHERS THEN
                        RAISE NOTICE 'Log activity failed: %', SQLERRM;
                    END;
                END IF;

                RETURN jsonb_build_object(
                    'status', 'SUCCESS',
                    'message', CASE 
                        WHEN v_is_update THEN 'Audit session updated successfully'
                        ELSE 'Audit session created successfully'
                    END,
                    'session_id', v_session_id,
                    'session_code', v_session_code,
                    'is_update', v_is_update,
                    'activity_log_id', v_log_id
                );

            EXCEPTION
                WHEN foreign_key_violation THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'Invalid reference: check audit_period_id or lead_auditor_id'
                    );
                WHEN unique_violation THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'Session name already exists for this audit period'
                    );
                WHEN OTHERS THEN
                    RETURN jsonb_build_object(
                        'status', 'ERROR',
                        'message', 'Database error: ' || SQLERRM
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
        DB::unprepared('DROP FUNCTION IF EXISTS create_or_update_audit_session CASCADE');
    }
};
