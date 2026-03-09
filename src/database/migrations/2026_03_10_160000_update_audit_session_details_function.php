<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Function: update_audit_session_details - Partial update for audit sessions
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION update_audit_session_details(
                p_audit_session_id BIGINT,
                p_tenant_id BIGINT,
                p_update_data JSONB,
                p_user_id BIGINT,
                p_user_name TEXT,
                p_current_time TIMESTAMP WITH TIME ZONE
            )
            RETURNS TABLE (
                status TEXT, 
                message TEXT,
                before_data JSONB,
                after_data JSONB,
                activity_log_id BIGINT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                rows_updated INT;
                data_before JSONB;
                data_after JSONB;
                query TEXT;
                key TEXT;
                value TEXT;
                first BOOLEAN := TRUE;
                new_session_name TEXT;
                existing_session_count INTEGER;
                log_id BIGINT;
                changed_fields TEXT[];
                v_audit_period_id BIGINT;
            BEGIN
                -- Check if updating session_name
                IF p_update_data ? 'session_name' THEN
                    new_session_name := p_update_data->>'session_name';
                    
                    -- Validate name is not empty
                    IF new_session_name IS NULL OR LENGTH(TRIM(new_session_name)) = 0 THEN
                        RETURN QUERY SELECT 
                            'ERROR'::TEXT,
                            'Session name cannot be empty'::TEXT,
                            NULL::JSONB,
                            NULL::JSONB,
                            NULL::BIGINT;
                        RETURN;
                    END IF;
                    
                    -- Get current audit_period_id
                    SELECT audit_period_id INTO v_audit_period_id
                    FROM audit_sessions
                    WHERE id = p_audit_session_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;
                    
                    -- Check for duplicate session name in same audit period
                    SELECT COUNT(*) INTO existing_session_count
                    FROM audit_sessions
                    WHERE LOWER(TRIM(session_name)) = LOWER(TRIM(new_session_name))
                    AND audit_period_id = v_audit_period_id
                    AND tenant_id = p_tenant_id
                    AND id != p_audit_session_id
                    AND deleted_at IS NULL;

                    IF existing_session_count > 0 THEN
                        RETURN QUERY SELECT 
                            'ERROR'::TEXT, 
                            'Session name already exists for this audit period'::TEXT,
                            NULL::JSONB,
                            NULL::JSONB,
                            NULL::BIGINT;
                        RETURN;
                    END IF;
                END IF;

                -- Validate date range if both dates are being updated
                IF p_update_data ? 'actual_start_date' AND p_update_data ? 'actual_end_date' THEN
                    IF (p_update_data->>'actual_end_date')::DATE < (p_update_data->>'actual_start_date')::DATE THEN
                        RETURN QUERY SELECT 
                            'ERROR'::TEXT,
                            'Actual end date cannot be before start date'::TEXT,
                            NULL::JSONB,
                            NULL::JSONB,
                            NULL::BIGINT;
                        RETURN;
                    END IF;
                END IF;

                -- Capture before data
                SELECT to_jsonb(ases.*) INTO data_before
                FROM audit_sessions ases
                WHERE id = p_audit_session_id 
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;
                
                -- Check if record exists
                IF data_before IS NULL THEN
                    RETURN QUERY SELECT 
                        'ERROR'::TEXT, 
                        'Audit session not found or tenant mismatch'::TEXT,
                        NULL::JSONB,
                        NULL::JSONB,
                        NULL::BIGINT;
                    RETURN;
                END IF;
                
                -- Build dynamic update query
                query := 'UPDATE audit_sessions SET ';
                
                FOR key, value IN SELECT * FROM jsonb_each_text(p_update_data) LOOP
                    IF NOT first THEN
                        query := query || ', ';
                    ELSE
                        first := FALSE;
                    END IF;
                    changed_fields := array_append(changed_fields, key);
                    
                    -- Handle JSONB fields
                    IF key IN ('audit_objectives', 'audit_scope', 'audit_criteria', 'risk_factors', 'zombie_assets_summary', 'recommendations') THEN
                        query := query || format('%I = %L::jsonb', key, value);
                    ELSE
                        query := query || format('%I = %L', key, value);
                    END IF;
                END LOOP;
                
                query := query || format(', updated_by = %L, updated_at = %L WHERE id = %L AND tenant_id = %L AND deleted_at IS NULL', 
                                        p_user_id, p_current_time, p_audit_session_id, p_tenant_id);
                
                -- Execute update
                EXECUTE query;
                
                GET DIAGNOSTICS rows_updated = ROW_COUNT;
                
                IF rows_updated > 0 THEN
                    -- Capture after data
                    SELECT to_jsonb(ases.*) INTO data_after
                    FROM audit_sessions ases
                    WHERE id = p_audit_session_id AND tenant_id = p_tenant_id;
                    
                    -- Create activity log entry using log_activity function
                    BEGIN
                        log_id := log_activity(
                            'audit_session.updated',
                            format('Updated audit session "%s" by %s', (data_after->>'session_name')::TEXT, p_user_name),
                            'audit_sessions',
                            p_audit_session_id,
                            'user',
                            p_user_id,
                            jsonb_build_object(
                                'action', 'update',
                                'before', data_before,
                                'after', data_after,
                                'changed_fields', changed_fields,
                                'user_name', p_user_name,
                                'tenant_id', p_tenant_id
                            ),
                            p_tenant_id
                        );
                    EXCEPTION WHEN OTHERS THEN
                        RAISE NOTICE 'Activity log failed: %', SQLERRM;
                    END;
                    
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT, 
                        'Audit session updated successfully'::TEXT,
                        data_before,
                        data_after,
                        log_id;
                ELSE
                    RETURN QUERY SELECT 
                        'ERROR'::TEXT, 
                        'No rows updated. Audit session not found or tenant mismatch.'::TEXT,
                        data_before,
                        NULL::JSONB,
                        NULL::BIGINT;
                END IF;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS update_audit_session_details CASCADE');
    }
};
