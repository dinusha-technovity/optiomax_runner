<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Create function to create or update financial year
     * Follows ISO 8601 date format and includes complete audit logging
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        DROP FUNCTION IF EXISTS create_or_update_financial_year CASCADE;

        CREATE OR REPLACE FUNCTION create_or_update_financial_year(
            p_tenant_id BIGINT,
            p_user_id BIGINT,
            p_year_id BIGINT DEFAULT NULL,
            p_year_name VARCHAR DEFAULT NULL,
            p_start_date DATE DEFAULT NULL,
            p_end_date DATE DEFAULT NULL,
            p_status VARCHAR DEFAULT 'active',
            p_user_name VARCHAR DEFAULT NULL,
            p_current_time TIMESTAMPTZ DEFAULT now()
        ) RETURNS JSONB LANGUAGE plpgsql AS $$
        DECLARE
            v_year_id BIGINT;
            v_is_update BOOLEAN := FALSE;
            v_old_data JSONB;
            v_new_data JSONB;
            v_duplicate_exists BOOLEAN;
        BEGIN
            -- Validate required inputs
            IF p_tenant_id IS NULL THEN
                RETURN jsonb_build_object(
                    'success', FALSE,
                    'status', 'ERROR',
                    'message', 'Tenant ID is required'
                );
            END IF;

            IF p_user_id IS NULL THEN
                RETURN jsonb_build_object(
                    'success', FALSE,
                    'status', 'ERROR',
                    'message', 'User ID is required'
                );
            END IF;

            IF p_year_name IS NULL OR TRIM(p_year_name) = '' THEN
                RETURN jsonb_build_object(
                    'success', FALSE,
                    'status', 'ERROR',
                    'message', 'Year name is required'
                );
            END IF;

            IF p_start_date IS NULL THEN
                RETURN jsonb_build_object(
                    'success', FALSE,
                    'status', 'ERROR',
                    'message', 'Start date is required (ISO 8601 format: YYYY-MM-DD)'
                );
            END IF;

            IF p_end_date IS NULL THEN
                RETURN jsonb_build_object(
                    'success', FALSE,
                    'status', 'ERROR',
                    'message', 'End date is required (ISO 8601 format: YYYY-MM-DD)'
                );
            END IF;

            -- Validate date range
            IF p_end_date <= p_start_date THEN
                RETURN jsonb_build_object(
                    'success', FALSE,
                    'status', 'ERROR',
                    'message', 'End date must be after start date'
                );
            END IF;

            -- Validate status
            IF p_status NOT IN ('active', 'archived') THEN
                RETURN jsonb_build_object(
                    'success', FALSE,
                    'status', 'ERROR',
                    'message', 'Status must be either active or archived'
                );
            END IF;

            -- Check for duplicate year name (excluding current record if updating)
            SELECT EXISTS(
                SELECT 1 FROM financial_years
                WHERE tenant_id = p_tenant_id
                    AND year_name = TRIM(p_year_name)
                    AND deleted_at IS NULL
                    AND isactive = TRUE
                    AND (p_year_id IS NULL OR id != p_year_id)
            ) INTO v_duplicate_exists;

            IF v_duplicate_exists THEN
                RETURN jsonb_build_object(
                    'success', FALSE,
                    'status', 'ERROR',
                    'message', 'A financial year with this name already exists'
                );
            END IF;

            -- UPDATE existing financial year
            IF p_year_id IS NOT NULL THEN
                v_is_update := TRUE;

                -- Get old data for logging
                SELECT jsonb_build_object(
                    'year_name', year_name,
                    'start_date', start_date,
                    'end_date', end_date,
                    'is_running_year', is_running_year,
                    'status', status,
                    'isactive', isactive
                ) INTO v_old_data
                FROM financial_years
                WHERE id = p_year_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;

                IF v_old_data IS NULL THEN
                    RETURN jsonb_build_object(
                        'success', FALSE,
                        'status', 'ERROR',
                        'message', 'Financial year not found'
                    );
                END IF;

                -- Update the financial year
                UPDATE financial_years
                SET 
                    year_name = TRIM(p_year_name),
                    start_date = p_start_date,
                    end_date = p_end_date,
                    status = p_status,
                    updated_by = p_user_id,
                    updated_at = p_current_time
                WHERE id = p_year_id
                    AND tenant_id = p_tenant_id
                    AND deleted_at IS NULL;

                v_year_id := p_year_id;

                -- Get new data for logging
                SELECT jsonb_build_object(
                    'year_name', year_name,
                    'start_date', start_date,
                    'end_date', end_date,
                    'is_running_year', is_running_year,
                    'status', status,
                    'isactive', isactive
                ) INTO v_new_data
                FROM financial_years
                WHERE id = p_year_id;

                -- Log activity
                BEGIN
                    PERFORM log_activity(
                        'financial_year.updated',
                        'Financial year "' || TRIM(p_year_name) || '" updated',
                        'financial_years',
                        v_year_id,
                        'user',
                        p_user_id,
                        jsonb_build_object(
                            'year_id', v_year_id,
                            'year_name', TRIM(p_year_name),
                            'old_data', v_old_data,
                            'new_data', v_new_data,
                            'modified_by', p_user_name,
                            'action_time', p_current_time
                        ),
                        p_tenant_id
                    );
                EXCEPTION WHEN OTHERS THEN
                    RAISE NOTICE 'Log activity failed: %', SQLERRM;
                END;

            ELSE
                -- CREATE new financial year
                INSERT INTO financial_years (
                    tenant_id,
                    year_name,
                    start_date,
                    end_date,
                    is_running_year,
                    status,
                    created_by,
                    updated_by,
                    isactive,
                    created_at,
                    updated_at
                ) VALUES (
                    p_tenant_id,
                    TRIM(p_year_name),
                    p_start_date,
                    p_end_date,
                    FALSE, -- New years are not running by default
                    p_status,
                    p_user_id,
                    p_user_id,
                    TRUE,
                    p_current_time,
                    p_current_time
                ) RETURNING id INTO v_year_id;

                -- Log activity
                BEGIN
                    PERFORM log_activity(
                        'financial_year.created',
                        'Financial year "' || TRIM(p_year_name) || '" created',
                        'financial_years',
                        v_year_id,
                        'user',
                        p_user_id,
                        jsonb_build_object(
                            'year_id', v_year_id,
                            'year_name', TRIM(p_year_name),
                            'start_date', p_start_date,
                            'end_date', p_end_date,
                            'status', p_status,
                            'created_by', p_user_name,
                            'action_time', p_current_time
                        ),
                        p_tenant_id
                    );
                EXCEPTION WHEN OTHERS THEN
                    RAISE NOTICE 'Log activity failed: %', SQLERRM;
                END;
            END IF;

            -- Return success response
            RETURN jsonb_build_object(
                'success', TRUE,
                'status', 'SUCCESS',
                'message', CASE 
                    WHEN v_is_update THEN 'Financial year updated successfully'
                    ELSE 'Financial year created successfully'
                END,
                'year_id', v_year_id,
                'is_update', v_is_update
            );

        EXCEPTION
            WHEN unique_violation THEN
                RETURN jsonb_build_object(
                    'success', FALSE,
                    'status', 'ERROR',
                    'message', 'A financial year with this name already exists'
                );
            WHEN OTHERS THEN
                RETURN jsonb_build_object(
                    'success', FALSE,
                    'status', 'ERROR',
                    'message', 'An error occurred: ' || SQLERRM
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
        DB::unprepared('DROP FUNCTION IF EXISTS create_or_update_financial_year CASCADE');
    }
};
