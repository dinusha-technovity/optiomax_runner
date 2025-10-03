<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
        CREATE OR REPLACE FUNCTION get_top_suppliers_ranking_data(
            p_tenant_id BIGINT,
            p_limit INT DEFAULT 3,
            p_type TEXT DEFAULT 'web'
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            supplier_data JSONB
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_avg_response_time NUMERIC;
            v_avg_response_rate NUMERIC;
            v_avg_competitiveness NUMERIC;
            v_total_quotations INT;
            v_total_suppliers INT;
        BEGIN
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RAISE EXCEPTION 'Invalid tenant ID';
            END IF;

            -- Calculate overall metrics
            SELECT 
                COALESCE(AVG(CASE WHEN pari.is_receive_quotation THEN 
                    (pari.available_date - pari.created_at::DATE) * 24.0
                END), 0),
                COALESCE(AVG(CASE WHEN pari.is_receive_quotation THEN 100.0 ELSE 0 END), 0),
                COALESCE(AVG(CASE WHEN pari.is_receive_quotation THEN 
                    LEAST(100, (pari.expected_budget_per_item::NUMERIC / NULLIF(pari.delivery_cost + (pari.expected_budget_per_item * pari.requested_quantity), 0)) * 100)
                END), 0),
                COUNT(CASE WHEN pari.is_receive_quotation THEN 1 END),
                COUNT(DISTINCT pari.supplier_id)
            INTO v_avg_response_time, v_avg_response_rate, v_avg_competitiveness, v_total_quotations, v_total_suppliers
            FROM procurement_attempt_request_items pari
            WHERE pari.tenant_id = p_tenant_id;

            RETURN QUERY
            WITH supplier_base_metrics AS (
                SELECT 
                    s.id,
                    s.name,
                    s.address,
                    s.supplier_reg_no,
                    s.supplier_rating,
                    s.isactive,
                    COUNT(pari.procurement_id) as total_requests,
                    COUNT(CASE WHEN pari.is_receive_quotation THEN 1 END) as quotations_received,
                    COUNT(CASE WHEN pari.is_available_on_quotation THEN 1 END) as items_available,
                    COUNT(CASE WHEN pari.can_full_fill_requested_quantity THEN 1 END) as full_fulfillments,
                    AVG(CASE WHEN pari.is_receive_quotation AND pari.available_date IS NOT NULL THEN 
                        (pari.available_date - pari.created_at::DATE) * 24.0
                    END) as avg_response_time_hours,
                    SUM(pari.expected_budget_per_item * pari.requested_quantity) as total_request_value,
                    SUM(pari.delivery_cost) as total_delivery_cost,
                    AVG(pari.available_quantity::NUMERIC / NULLIF(pari.requested_quantity, 0) * 100) as avg_fulfillment_rate
                FROM suppliers s
                LEFT JOIN procurement_attempt_request_items pari ON s.id = pari.supplier_id
                WHERE s.tenant_id = p_tenant_id 
                AND s.supplier_reg_status = 'APPROVED'
                AND s.isactive = true
                GROUP BY s.id, s.name, s.address, s.supplier_reg_no, s.supplier_rating, s.isactive
                HAVING COUNT(pari.procurement_id) > 0
            ),
            supplier_additional_data AS (
                SELECT 
                    sbm.id,
                    -- Count assets associated with this supplier
                    COUNT(DISTINCT ai.asset_id) as total_assets,
                    -- Count different item types they supply
                    COUNT(DISTINCT sfi.id) as service_variety,
                    -- Calculate relationship duration (months since first procurement)
                    COALESCE(
                        EXTRACT(YEAR FROM AGE(CURRENT_DATE, MIN(pari.created_at::DATE))) * 12 + 
                        EXTRACT(MONTH FROM AGE(CURRENT_DATE, MIN(pari.created_at::DATE)))
                    , 0)::INT as relationship_duration_months
                FROM supplier_base_metrics sbm
                LEFT JOIN asset_items ai ON ai.supplier = sbm.id
                LEFT JOIN suppliers_for_item sfi ON sfi.supplier_id = sbm.id
                LEFT JOIN procurement_attempt_request_items pari ON pari.supplier_id = sbm.id
                GROUP BY sbm.id
            ),
            supplier_scores AS (
                SELECT 
                    sbm.*,
                    sad.total_assets,
                    sad.service_variety,
                    sad.relationship_duration_months,
                    -- Calculate response rate
                    COALESCE(ROUND((sbm.quotations_received::NUMERIC / NULLIF(sbm.total_requests, 0)) * 100, 2), 0) as response_rate,
                    -- Calculate competitiveness score (based on cost efficiency)
                    COALESCE(ROUND(100 - (sbm.total_delivery_cost::NUMERIC / NULLIF(sbm.total_request_value, 0) * 100), 2), 50) as competitiveness_score,
                    -- Calculate value addition metrics
                    LEAST(sad.service_variety, 10) as additional_services_score,
                    COALESCE(ROUND((1 - (sbm.total_delivery_cost::NUMERIC / NULLIF(sbm.total_request_value, 0))) * 100, 2), 0) as discount_equivalent,
                    COALESCE(ROUND(sbm.avg_fulfillment_rate, 2), 0) as compliance_score,
                    -- Calculate overall performance score
                    COALESCE(ROUND(
                        (sbm.supplier_rating * 0.3) +
                        ((sbm.quotations_received::NUMERIC / NULLIF(sbm.total_requests, 0)) * 100 * 0.2) +
                        (LEAST(100, (100 - (COALESCE(sbm.avg_response_time_hours, 24) / 24 * 100))) * 0.2) +
                        ((sbm.full_fulfillments::NUMERIC / NULLIF(sbm.total_requests, 0)) * 100 * 0.3)
                    , 2), 0) as overall_score
                FROM supplier_base_metrics sbm
                LEFT JOIN supplier_additional_data sad ON sad.id = sbm.id
            ),
            ranked_suppliers AS (
                SELECT 
                    *,
                    ROW_NUMBER() OVER (ORDER BY overall_score DESC, supplier_rating DESC, response_rate DESC) as rank
                FROM supplier_scores
                WHERE total_requests >= 1  -- Only include suppliers with activity
            ),
            top_suppliers_with_badges AS (
                SELECT 
                    rs.*,
                    CASE 
                        WHEN rs.rank = 1 THEN ARRAY['Top Performer', 'Best Overall']
                        WHEN rs.avg_response_time_hours <= 2 THEN ARRAY['Fast Responder']
                        WHEN rs.response_rate >= 95 THEN ARRAY['Most Reliable']
                        WHEN rs.competitiveness_score >= 90 THEN ARRAY['Best Value']
                        WHEN rs.additional_services_score >= 8 THEN ARRAY['Service Leader']
                        ELSE ARRAY[]::TEXT[]
                    END as badges,
                    CASE 
                        WHEN rs.avg_response_time_hours <= 2 THEN ARRAY['Fastest response time']
                        ELSE ARRAY[]::TEXT[]
                    END ||
                    CASE 
                        WHEN rs.compliance_score >= 95 THEN ARRAY['High compliance rate']
                        ELSE ARRAY[]::TEXT[]
                    END ||
                    CASE 
                        WHEN rs.response_rate >= 95 THEN ARRAY['Excellent reliability']
                        ELSE ARRAY[]::TEXT[]
                    END as highlights
                FROM ranked_suppliers rs
                ORDER BY rs.rank
                LIMIT p_limit
            )
            SELECT 
                CASE WHEN p_type = 'web' THEN 'success' ELSE 'failed' END,
                CASE WHEN p_type = 'web' THEN 'supplier ranking data fetch success' ELSE 'your request type is invalid' END,
                CASE WHEN p_type = 'web' THEN 
                    jsonb_build_object(
                        'topSuppliers', (
                            SELECT jsonb_agg(
                                jsonb_build_object(
                                    'id', ts.id,
                                    'name', ts.name,
                                    'overallScore', ts.overall_score,
                                    'rating', ts.supplier_rating,
                                    'rank', ts.rank,
                                    'quotationMetrics', jsonb_build_object(
                                        'responseTime', COALESCE(ts.avg_response_time_hours, 0),
                                        'responseRate', ts.response_rate,
                                        'competitiveness', ts.competitiveness_score
                                    ),
                                    'valueAddition', jsonb_build_object(
                                        'additionalServices', ts.additional_services_score,
                                        'discountOffered', ts.discount_equivalent,
                                        'complianceScore', ts.compliance_score
                                    ),
                                    'customerSpecific', jsonb_build_object(
                                        'totalAssets', COALESCE(ts.total_assets, 0),
                                        'performanceRating', ts.supplier_rating,
                                        'relationshipDuration', ts.relationship_duration_months
                                    ),
                                    'globalMetrics', jsonb_build_object(
                                        'globalRating', ts.supplier_rating,
                                        'totalCustomers', GREATEST(1, ts.total_requests / 10), -- Estimated
                                        'marketRank', ts.rank
                                    ),
                                    'badges', to_jsonb(ts.badges),
                                    'highlights', to_jsonb(ts.highlights)
                                ) ORDER BY ts.rank
                            )
                            FROM top_suppliers_with_badges ts
                        ),
                        'metrics', jsonb_build_object(
                            'avgResponseTime', COALESCE(v_avg_response_time, 0),
                            'avgResponseRate', COALESCE(v_avg_response_rate, 0),
                            'avgCompetitiveness', COALESCE(v_avg_competitiveness, 0),
                            'totalQuotationsReceived', COALESCE(v_total_quotations, 0),
                            'totalSuppliersEngaged', COALESCE(v_total_suppliers, 0)
                        )
                    )
                ELSE NULL END;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_top_suppliers_ranking_data(BIGINT, INT, TEXT);");
    }
};
