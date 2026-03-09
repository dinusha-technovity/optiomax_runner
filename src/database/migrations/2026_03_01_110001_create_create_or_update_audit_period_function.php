<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * PostgreSQL function to create or update audit periods with validation
     */
    public function up(): void
    {
        DB::unprepared("
            CREATE OR REPLACE FUNCTION create_or_update_audit_period(
                p_tenant_id BIGINT,
                p_user_id BIGINT,
                p_period_id BIGINT DEFAULT NULL,
                p_period_name TEXT DEFAULT NULL,
                p_description TEXT DEFAULT NULL,
                p_financial_year_id BIGINT DEFAULT NULL,
                p_start_date DATE DEFAULT NULL,
                p_end_date DATE DEFAULT NULL,
                p_period_leader_id BIGINT DEFAULT NULL,
                p_status TEXT DEFAULT 'active',
                p_user_name TEXT DEFAULT NULL,
                p_current_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
            RETURNS JSONB
            LANGUAGE plpgsql
            AS \$\$
            DECLARE
                v_period_id BIGINT;
                v_result JSONB;
                v_operation TEXT;
                v_name_exists BOOLEAN;
                v_fy_exists BOOLEAN;
                v_leader_exists BOOLEAN;
                v_overlap_exists BOOLEAN;
                v_overlap_period_name TEXT;
            BEGIN
                -- Validation: Check if financial year exists
                SELECT EXISTS(
                    SELECT 1 FROM financial_years 
                    WHERE id = p_financial_year_id 
                        AND tenant_id = p_tenant_id 
                        AND deleted_at IS NULL
                ) INTO v_fy_exists;
                
                IF NOT v_fy_exists THEN
                    RETURN jsonb_build_object(
                        'success', FALSE,
                        'status', 400,
                        'message', 'Financial year not found or inactive',
                        'data', NULL
                    );
                END IF;
                
                -- Validation: Check if period leader exists
                SELECT EXISTS(
                    SELECT 1 FROM users 
                    WHERE id = p_period_leader_id 
                        AND tenant_id = p_tenant_id 
                        AND deleted_at IS NULL
                ) INTO v_leader_exists;
                
                IF NOT v_leader_exists THEN
                    RETURN jsonb_build_object(
                        'success', FALSE,
                        'status', 400,
                        'message', 'Period leader not found or inactive',
                        'data', NULL
                    );
                END IF;
                
                -- Validation: Check date range
                IF p_end_date < p_start_date THEN
                    RETURN jsonb_build_object(
                        'success', FALSE,
                        'status', 400,
                        'message', 'End date must be greater than or equal to start date',
                        'data', NULL
                    );
                END IF;
                
                -- Validation: Check for overlapping audit periods
                -- Overlap occurs when: new_start <= existing_end AND new_end >= existing_start
                IF p_period_id IS NOT NULL THEN
                    -- For UPDATE: Check overlap excluding current period
                    SELECT EXISTS(
                        SELECT 1 FROM audit_periods 
                        WHERE tenant_id = p_tenant_id 
                            AND id != p_period_id
                            AND deleted_at IS NULL
                            AND isactive = TRUE
                            AND p_start_date <= end_date 
                            AND p_end_date >= start_date
                    ) INTO v_overlap_exists;
                    
                    IF v_overlap_exists THEN
                        -- Get the name of the overlapping period for better error message
                        SELECT period_name INTO v_overlap_period_name
                        FROM audit_periods 
                        WHERE tenant_id = p_tenant_id 
                            AND id != p_period_id
                            AND deleted_at IS NULL
                            AND isactive = TRUE
                            AND p_start_date <= end_date 
                            AND p_end_date >= start_date
                        LIMIT 1;
                        
                        RETURN jsonb_build_object(
                            'success', FALSE,
                            'status', 400,
                            'message', 'Date range overlaps with existing audit period: ' || v_overlap_period_name,
                            'data', NULL
                        );
                    END IF;
                ELSE
                    -- For CREATE: Check overlap with any existing period
                    SELECT EXISTS(
                        SELECT 1 FROM audit_periods 
                        WHERE tenant_id = p_tenant_id 
                            AND deleted_at IS NULL
                            AND isactive = TRUE
                            AND p_start_date <= end_date 
                            AND p_end_date >= start_date
                    ) INTO v_overlap_exists;
                    
                    IF v_overlap_exists THEN
                        -- Get the name of the overlapping period for better error message
                        SELECT period_name INTO v_overlap_period_name
                        FROM audit_periods 
                        WHERE tenant_id = p_tenant_id 
                            AND deleted_at IS NULL
                            AND isactive = TRUE
                            AND p_start_date <= end_date 
                            AND p_end_date >= start_date
                        LIMIT 1;
                        
                        RETURN jsonb_build_object(
                            'success', FALSE,
                            'status', 400,
                            'message', 'Date range overlaps with existing audit period: ' || v_overlap_period_name,
                            'data', NULL
                        );
                    END IF;
                END IF;
                
                -- UPDATE existing audit period
                IF p_period_id IS NOT NULL THEN
                    -- Check if period exists
                    IF NOT EXISTS(
                        SELECT 1 FROM audit_periods 
                        WHERE id = p_period_id 
                            AND tenant_id = p_tenant_id 
                            AND deleted_at IS NULL
                    ) THEN
                        RETURN jsonb_build_object(
                            'success', FALSE,
                            'status', 404,
                            'message', 'Audit period not found',
                            'data', NULL
                        );
                    END IF;
                    
                    -- Check for duplicate name (excluding current record)
                    SELECT EXISTS(
                        SELECT 1 FROM audit_periods 
                        WHERE period_name = p_period_name 
                            AND tenant_id = p_tenant_id 
                            AND id != p_period_id 
                            AND deleted_at IS NULL
                    ) INTO v_name_exists;
                    
                    IF v_name_exists THEN
                        RETURN jsonb_build_object(
                            'success', FALSE,
                            'status', 400,
                            'message', 'Audit period name already exists',
                            'data', NULL
                        );
                    END IF;
                    
                    -- Update audit period
                    UPDATE audit_periods
                    SET period_name = COALESCE(p_period_name, period_name),
                        description = COALESCE(p_description, description),
                        financial_year_id = COALESCE(p_financial_year_id, financial_year_id),
                        start_date = COALESCE(p_start_date, start_date),
                        end_date = COALESCE(p_end_date, end_date),
                        period_leader_id = COALESCE(p_period_leader_id, period_leader_id),
                        status = COALESCE(p_status, status),
                        updated_by = p_user_id,
                        updated_at = p_current_time
                    WHERE id = p_period_id 
                        AND tenant_id = p_tenant_id;
                    
                    v_period_id := p_period_id;
                    v_operation := 'updated';
                    
                    -- Log activity
                    PERFORM log_activity(
                        'audit_period_updated',
                        p_user_name || ' updated audit period: ' || p_period_name,
                        'Audit Period',
                        v_period_id,
                        'User',
                        p_user_id,
                        NULL,
                        p_tenant_id
                    );
                    
                ELSE
                    -- CREATE new audit period
                    
                    -- Check for duplicate name
                    SELECT EXISTS(
                        SELECT 1 FROM audit_periods 
                        WHERE period_name = p_period_name 
                            AND tenant_id = p_tenant_id 
                            AND deleted_at IS NULL
                    ) INTO v_name_exists;
                    
                    IF v_name_exists THEN
                        RETURN jsonb_build_object(
                            'success', FALSE,
                            'status', 400,
                            'message', 'Audit period name already exists',
                            'data', NULL
                        );
                    END IF;
                    
                    -- Insert new audit period
                    INSERT INTO audit_periods (
                        period_name,
                        description,
                        financial_year_id,
                        start_date,
                        end_date,
                        period_leader_id,
                        status,
                        created_by,
                        tenant_id,
                        created_at,
                        updated_at
                    ) VALUES (
                        p_period_name,
                        p_description,
                        p_financial_year_id,
                        p_start_date,
                        p_end_date,
                        p_period_leader_id,
                        p_status,
                        p_user_id,
                        p_tenant_id,
                        p_current_time,
                        p_current_time
                    )
                    RETURNING id INTO v_period_id;
                    
                    v_operation := 'created';
                    
                    -- Log activity
                    PERFORM log_activity(
                        'audit_period_created',
                        p_user_name || ' created audit period: ' || p_period_name,
                        'Audit Period',
                        v_period_id,
                        'User',
                        p_user_id,
                        NULL,
                        p_tenant_id
                    );
                END IF;
                
                -- Return success response
                v_result := jsonb_build_object(
                    'success', TRUE,
                    'status', 200,
                    'message', 'Audit period ' || v_operation || ' successfully',
                    'data', jsonb_build_object('id', v_period_id)
                );
                
                RETURN v_result;
                
            EXCEPTION
                WHEN OTHERS THEN
                    RETURN jsonb_build_object(
                        'success', FALSE,
                        'status', 500,
                        'message', 'Error processing audit period: ' || SQLERRM,
                        'data', NULL
                    );
            END;
            \$\$;
            
            COMMENT ON FUNCTION create_or_update_audit_period IS 'Create or update audit period with validation and activity logging';
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS create_or_update_audit_period');
    }
};
