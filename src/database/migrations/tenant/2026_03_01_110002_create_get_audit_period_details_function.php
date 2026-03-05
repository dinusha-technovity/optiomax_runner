<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * PostgreSQL function to get single audit period details with enriched data
     */
    public function up(): void
    {
        DB::unprepared("
            CREATE OR REPLACE FUNCTION get_audit_period_details(
                p_tenant_id BIGINT,
                p_period_id BIGINT
            )
            RETURNS JSONB
            LANGUAGE plpgsql
            AS \$\$
            DECLARE
                v_result JSONB;
            BEGIN
                -- Get audit period details with enriched information
                SELECT jsonb_build_object(
                    'id', ap.id,
                    'period_name', ap.period_name,
                    'description', ap.description,
                    'financial_year_id', ap.financial_year_id,
                    'financial_year', jsonb_build_object(
                        'id', fy.id,
                        'year_name', fy.year_name,
                        'start_date', fy.start_date,
                        'end_date', fy.end_date,
                        'is_running_year', fy.is_running_year,
                        'status', fy.status
                    ),
                    'start_date', ap.start_date,
                    'end_date', ap.end_date,
                    'period_leader_id', ap.period_leader_id,
                    'period_leader', jsonb_build_object(
                        'id', u_leader.id,
                        'user_name', u_leader.user_name,
                        'name', u_leader.name,
                        'email', u_leader.email,
                        'contact_no', u_leader.contact_no
                    ),
                    'status', ap.status,
                    'created_at', ap.created_at,
                    'updated_at', ap.updated_at,
                    'created_by', jsonb_build_object(
                        'id', u_creator.id,
                        'user_name', u_creator.user_name,
                        'name', u_creator.name
                    ),
                    'updated_by', CASE 
                        WHEN u_updater.id IS NOT NULL THEN jsonb_build_object(
                            'id', u_updater.id,
                            'user_name', u_updater.user_name,
                            'name', u_updater.name
                        )
                        ELSE NULL
                    END
                )
                INTO v_result
                FROM audit_periods ap
                LEFT JOIN financial_years fy ON ap.financial_year_id = fy.id
                LEFT JOIN users u_leader ON ap.period_leader_id = u_leader.id
                LEFT JOIN users u_creator ON ap.created_by = u_creator.id
                LEFT JOIN users u_updater ON ap.updated_by = u_updater.id
                WHERE ap.id = p_period_id
                    AND ap.tenant_id = p_tenant_id
                    AND ap.deleted_at IS NULL
                    AND ap.isactive = TRUE;
                
                -- Check if audit period exists
                IF v_result IS NULL THEN
                    RETURN jsonb_build_object(
                        'success', FALSE,
                        'status', 404,
                        'message', 'Audit period not found',
                        'data', NULL
                    );
                END IF;
                
                -- Return success response
                RETURN jsonb_build_object(
                    'success', TRUE,
                    'status', 200,
                    'message', 'Audit period details retrieved successfully',
                    'data', v_result
                );
                
            EXCEPTION
                WHEN OTHERS THEN
                    RETURN jsonb_build_object(
                        'success', FALSE,
                        'status', 500,
                        'message', 'Error retrieving audit period details: ' || SQLERRM,
                        'data', NULL
                    );
            END;
            \$\$;
            
            COMMENT ON FUNCTION get_audit_period_details IS 'Get single audit period with enriched data including financial year and leader info';
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_audit_period_details');
    }
};
