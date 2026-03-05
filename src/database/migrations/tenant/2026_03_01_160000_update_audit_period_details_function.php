<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Function: update_audit_period_details - Partial update for audit periods
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION update_audit_period_details(
                p_audit_period_id BIGINT,
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
                new_period_name TEXT;
                existing_period_count INTEGER;
                log_id BIGINT;
                changed_fields TEXT[];
            BEGIN
                -- Check if updating period_name
                IF p_update_data ? 'period_name' THEN
                    new_period_name := p_update_data->>'period_name';
                    
                    -- Validate name is not empty
                    IF new_period_name IS NULL OR LENGTH(TRIM(new_period_name)) = 0 THEN
                        RETURN QUERY SELECT 
                            'ERROR'::TEXT,
                            'Period name cannot be empty'::TEXT,
                            NULL::JSONB,
                            NULL::JSONB,
                            NULL::BIGINT;
                        RETURN;
                    END IF;
                    
                    -- Check for duplicate period name in the same tenant
                    SELECT COUNT(*) INTO existing_period_count
                    FROM audit_periods
                    WHERE LOWER(TRIM(period_name)) = LOWER(TRIM(new_period_name))
                    AND tenant_id = p_tenant_id
                    AND id != p_audit_period_id
                    AND deleted_at IS NULL;

                    IF existing_period_count > 0 THEN
                        RETURN QUERY SELECT 
                            'ERROR'::TEXT, 
                            'Audit period name already exists for this tenant'::TEXT,
                            NULL::JSONB,
                            NULL::JSONB,
                            NULL::BIGINT;
                        RETURN;
                    END IF;
                END IF;

                -- Validate date range if both dates are being updated
                IF p_update_data ? 'start_date' AND p_update_data ? 'end_date' THEN
                    IF (p_update_data->>'end_date')::DATE < (p_update_data->>'start_date')::DATE THEN
                        RETURN QUERY SELECT 
                            'ERROR'::TEXT,
                            'End date cannot be before start date'::TEXT,
                            NULL::JSONB,
                            NULL::JSONB,
                            NULL::BIGINT;
                        RETURN;
                    END IF;
                END IF;

                -- Capture before data
                SELECT to_jsonb(ap.*) INTO data_before
                FROM audit_periods ap
                WHERE id = p_audit_period_id 
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;
                
                -- Check if record exists
                IF data_before IS NULL THEN
                    RETURN QUERY SELECT 
                        'ERROR'::TEXT, 
                        'Audit period not found or tenant mismatch'::TEXT,
                        NULL::JSONB,
                        NULL::JSONB,
                        NULL::BIGINT;
                    RETURN;
                END IF;
                
                -- Build dynamic update query
                query := 'UPDATE audit_periods SET ';
                
                FOR key, value IN SELECT * FROM jsonb_each_text(p_update_data) LOOP
                    IF NOT first THEN
                        query := query || ', ';
                    ELSE
                        first := FALSE;
                    END IF;
                    changed_fields := array_append(changed_fields, key);
                    query := query || format('%I = %L', key, value);
                END LOOP;
                
                query := query || format(', updated_by = %L, updated_at = %L WHERE id = %L AND tenant_id = %L AND deleted_at IS NULL', 
                                        p_user_id, p_current_time, p_audit_period_id, p_tenant_id);
                
                -- Execute update
                EXECUTE query;
                
                GET DIAGNOSTICS rows_updated = ROW_COUNT;
                
                IF rows_updated > 0 THEN
                    -- Capture after data
                    SELECT to_jsonb(ap.*) INTO data_after
                    FROM audit_periods ap
                    WHERE id = p_audit_period_id AND tenant_id = p_tenant_id;
                    
                    -- Create activity log entry using log_activity function
                    BEGIN
                        log_id := log_activity(
                            'audit_period.updated',
                            format('Updated audit period "%s" by %s', (data_after->>'period_name')::TEXT, p_user_name),
                            'audit_periods',
                            p_audit_period_id,
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
                        'Audit period updated successfully'::TEXT,
                        data_before,
                        data_after,
                        log_id;
                ELSE
                    RETURN QUERY SELECT 
                        'ERROR'::TEXT, 
                        'No rows updated. Audit period not found or tenant mismatch.'::TEXT,
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
        DB::unprepared('DROP FUNCTION IF EXISTS update_audit_period_details CASCADE');
    }
};
