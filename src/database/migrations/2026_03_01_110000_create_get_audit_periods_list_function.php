<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * PostgreSQL function to retrieve paginated audit periods list with filters
     */
    public function up(): void
    {
        DB::unprepared("
            CREATE OR REPLACE FUNCTION get_audit_periods_list(
                p_tenant_id BIGINT,
                p_page INT DEFAULT 1,
                p_per_page INT DEFAULT 10,
                p_search TEXT DEFAULT NULL,
                p_status TEXT DEFAULT NULL,
                p_financial_year_id BIGINT DEFAULT NULL,
                p_period_leader_id BIGINT DEFAULT NULL,
                p_sort_by TEXT DEFAULT 'created_at',
                p_sort_order TEXT DEFAULT 'desc'
            )
            RETURNS JSONB
            LANGUAGE plpgsql
            AS \$\$
            DECLARE
                v_offset INT;
                v_total_count INT;
                v_result JSONB;
                v_data JSONB;
            BEGIN
                -- Calculate offset for pagination
                v_offset := (p_page - 1) * p_per_page;
                
                -- Get total count with filters
                SELECT COUNT(*)
                INTO v_total_count
                FROM audit_periods ap
                WHERE ap.tenant_id = p_tenant_id
                    AND ap.deleted_at IS NULL
                    AND ap.isactive = TRUE
                    AND (p_search IS NULL OR 
                         ap.period_name ILIKE '%' || p_search || '%' OR
                         ap.description ILIKE '%' || p_search || '%')
                    AND (p_status IS NULL OR ap.status = p_status)
                    AND (p_financial_year_id IS NULL OR ap.financial_year_id = p_financial_year_id)
                    AND (p_period_leader_id IS NULL OR ap.period_leader_id = p_period_leader_id);
                
                -- Get paginated data with enriched information including full user details
                SELECT json_agg(period_data)
                INTO v_data
                FROM (
                    SELECT 
                        ap.id,
                        ap.period_name,
                        ap.description,
                        ap.financial_year_id,
                        fy.year_name AS financial_year_name,
                        fy.start_date AS financial_year_start,
                        fy.end_date AS financial_year_end,
                        fy.is_running_year AS financial_year_is_running,
                        ap.start_date,
                        ap.end_date,
                        ap.status,
                        ap.created_at,
                        ap.updated_at,
                        -- Period Leader details as nested object
                        CASE 
                            WHEN u_leader.id IS NOT NULL THEN
                                jsonb_build_object(
                                    'id', u_leader.id,
                                    'name', u_leader.name,
                                    'user_name', u_leader.user_name,
                                    'email', u_leader.email,
                                    'contact_no', u_leader.contact_no,
                                    'profile_image', u_leader.profile_image,
                                    'designation', CASE 
                                        WHEN d_leader.id IS NOT NULL THEN
                                            jsonb_build_object(
                                                'id', d_leader.id,
                                                'name', d_leader.designation
                                            )
                                        ELSE NULL
                                    END
                                )
                            ELSE NULL
                        END AS period_leader,
                        -- Created By details as nested object
                        CASE 
                            WHEN u_creator.id IS NOT NULL THEN
                                jsonb_build_object(
                                    'id', u_creator.id,
                                    'name', u_creator.name,
                                    'user_name', u_creator.user_name,
                                    'email', u_creator.email,
                                    'contact_no', u_creator.contact_no,
                                    'profile_image', u_creator.profile_image,
                                    'designation', CASE 
                                        WHEN d_creator.id IS NOT NULL THEN
                                            jsonb_build_object(
                                                'id', d_creator.id,
                                                'name', d_creator.designation
                                            )
                                        ELSE NULL
                                    END
                                )
                            ELSE NULL
                        END AS created_by,
                        -- Updated By name (keeping simple for now)
                        u_updater.user_name AS updated_by_name
                    FROM audit_periods ap
                    LEFT JOIN financial_years fy ON ap.financial_year_id = fy.id
                    LEFT JOIN users u_leader ON ap.period_leader_id = u_leader.id
                    LEFT JOIN designations d_leader ON u_leader.designation_id = d_leader.id
                    LEFT JOIN users u_creator ON ap.created_by = u_creator.id
                    LEFT JOIN designations d_creator ON u_creator.designation_id = d_creator.id
                    LEFT JOIN users u_updater ON ap.updated_by = u_updater.id
                    WHERE ap.tenant_id = p_tenant_id
                        AND ap.deleted_at IS NULL
                        AND ap.isactive = TRUE
                        AND (p_search IS NULL OR 
                             ap.period_name ILIKE '%' || p_search || '%' OR
                             ap.description ILIKE '%' || p_search || '%')
                        AND (p_status IS NULL OR ap.status = p_status)
                        AND (p_financial_year_id IS NULL OR ap.financial_year_id = p_financial_year_id)
                        AND (p_period_leader_id IS NULL OR ap.period_leader_id = p_period_leader_id)
                    ORDER BY 
                        CASE WHEN p_sort_by = 'period_name' AND p_sort_order = 'asc' THEN ap.period_name END ASC,
                        CASE WHEN p_sort_by = 'period_name' AND p_sort_order = 'desc' THEN ap.period_name END DESC,
                        CASE WHEN p_sort_by = 'start_date' AND p_sort_order = 'asc' THEN ap.start_date END ASC,
                        CASE WHEN p_sort_by = 'start_date' AND p_sort_order = 'desc' THEN ap.start_date END DESC,
                        CASE WHEN p_sort_by = 'status' AND p_sort_order = 'asc' THEN ap.status END ASC,
                        CASE WHEN p_sort_by = 'status' AND p_sort_order = 'desc' THEN ap.status END DESC,
                        CASE WHEN p_sort_by = 'created_at' AND p_sort_order = 'asc' THEN ap.created_at END ASC,
                        CASE WHEN p_sort_by = 'created_at' AND p_sort_order = 'desc' THEN ap.created_at END DESC
                    LIMIT p_per_page
                    OFFSET v_offset
                ) AS period_data;
                
                -- Handle empty result
                IF v_data IS NULL THEN
                    v_data := '[]'::JSONB;
                END IF;
                
                -- Build response
                v_result := jsonb_build_object(
                    'success', TRUE,
                    'status', 200,
                    'message', 'Audit periods retrieved successfully',
                    'data', v_data,
                    'pagination', jsonb_build_object(
                        'current_page', p_page,
                        'per_page', p_per_page,
                        'total', v_total_count,
                        'last_page', CEIL(v_total_count::DECIMAL / p_per_page)
                    )
                );
                
                RETURN v_result;
                
            EXCEPTION
                WHEN OTHERS THEN
                    RETURN jsonb_build_object(
                        'success', FALSE,
                        'status', 500,
                        'message', 'Error retrieving audit periods: ' || SQLERRM,
                        'data', '[]'::JSONB
                    );
            END;
            \$\$;
            
            COMMENT ON FUNCTION get_audit_periods_list IS 'Retrieve paginated audit periods with filters, sorting, and enriched data';
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_audit_periods_list');
    }
};
