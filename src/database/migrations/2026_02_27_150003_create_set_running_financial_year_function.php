<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Create function to set running financial year
     * Ensures only one running year per tenant (PostgreSQL partial unique index handles constraint)
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        DROP FUNCTION IF EXISTS set_running_financial_year CASCADE;

        CREATE OR REPLACE FUNCTION set_running_financial_year(
            p_tenant_id BIGINT,
            p_user_id BIGINT,
            p_year_id BIGINT,
            p_user_name VARCHAR DEFAULT NULL,
            p_current_time TIMESTAMPTZ DEFAULT now()
        ) RETURNS JSONB LANGUAGE plpgsql AS $$
        DECLARE
            v_year_name VARCHAR;
            v_old_running_year_id BIGINT;
            v_old_running_year_name VARCHAR;
            v_year_exists BOOLEAN;
        BEGIN
            -- Validate inputs
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

            IF p_year_id IS NULL THEN
                RETURN jsonb_build_object(
                    'success', FALSE,
                    'status', 'ERROR',
                    'message', 'Year ID is required'
                );
            END IF;

            -- Check if the financial year exists and belongs to tenant
            SELECT 
                EXISTS(
                    SELECT 1 FROM financial_years
                    WHERE id = p_year_id
                        AND tenant_id = p_tenant_id
                        AND deleted_at IS NULL
                        AND isactive = TRUE
                ),
                year_name
            INTO v_year_exists, v_year_name
            FROM financial_years
            WHERE id = p_year_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL
                AND isactive = TRUE;

            IF NOT v_year_exists THEN
                RETURN jsonb_build_object(
                    'success', FALSE,
                    'status', 'ERROR',
                    'message', 'Financial year not found or does not belong to this tenant'
                );
            END IF;

            -- Get current running year (if any)
            SELECT id, year_name
            INTO v_old_running_year_id, v_old_running_year_name
            FROM financial_years
            WHERE tenant_id = p_tenant_id
                AND is_running_year = TRUE
                AND deleted_at IS NULL
                AND isactive = TRUE;

            -- Start transaction logic: Unset previous running year
            IF v_old_running_year_id IS NOT NULL AND v_old_running_year_id != p_year_id THEN
                UPDATE financial_years
                SET 
                    is_running_year = FALSE,
                    updated_by = p_user_id,
                    updated_at = p_current_time
                WHERE id = v_old_running_year_id
                    AND tenant_id = p_tenant_id;

                -- Log activity for unset
                BEGIN
                    PERFORM log_activity(
                        'financial_year.unset_running',
                        'Financial year "' || v_old_running_year_name || '" unset as running year',
                        'financial_years',
                        v_old_running_year_id,
                        'user',
                        p_user_id,
                        jsonb_build_object(
                            'year_id', v_old_running_year_id,
                            'year_name', v_old_running_year_name,
                            'action', 'unset_running_year',
                            'modified_by', p_user_name,
                            'action_time', p_current_time
                        ),
                        p_tenant_id
                    );
                EXCEPTION WHEN OTHERS THEN
                    RAISE NOTICE 'Log activity failed for unset: %', SQLERRM;
                END;
            END IF;

            -- Set new running year
            UPDATE financial_years
            SET 
                is_running_year = TRUE,
                updated_by = p_user_id,
                updated_at = p_current_time
            WHERE id = p_year_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

            -- Log activity for set
            BEGIN
                PERFORM log_activity(
                    'financial_year.set_running',
                    'Financial year "' || v_year_name || '" set as running year',
                    'financial_years',
                    p_year_id,
                    'user',
                    p_user_id,
                    jsonb_build_object(
                        'year_id', p_year_id,
                        'year_name', v_year_name,
                        'action', 'set_running_year',
                        'previous_running_year_id', v_old_running_year_id,
                        'previous_running_year_name', v_old_running_year_name,
                        'modified_by', p_user_name,
                        'action_time', p_current_time
                    ),
                    p_tenant_id
                );
            EXCEPTION WHEN OTHERS THEN
                RAISE NOTICE 'Log activity failed for set: %', SQLERRM;
            END;

            RETURN jsonb_build_object(
                'success', TRUE,
                'status', 'SUCCESS',
                'message', 'Running financial year updated successfully',
                'year_id', p_year_id,
                'year_name', v_year_name,
                'previous_running_year_id', v_old_running_year_id,
                'previous_running_year_name', v_old_running_year_name
            );

        EXCEPTION
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
        DB::unprepared('DROP FUNCTION IF EXISTS set_running_financial_year CASCADE');
    }
};
