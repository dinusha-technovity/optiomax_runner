<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_requisition_widget_data(
                p_tenant_id BIGINT,
                p_type TEXT DEFAULT 'web'

            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                requisition_data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                years_arr TEXT[];
                stats_obj JSONB := '{}';
                monthly_obj JSONB := '{}';
                yearly_pending INTEGER[] := ARRAY[]::INTEGER[];
                yearly_partial INTEGER[] := ARRAY[]::INTEGER[];
                year_rec TEXT;
                month_idx INTEGER;
                months_arr TEXT[] := ARRAY['01','02','03','04','05','06','07','08','09','10','11','12'];
                pending_arr INTEGER[];
                partial_arr INTEGER[];
                total_pending INTEGER;
                total_partial INTEGER;
                result_data JSONB;
            BEGIN
                -- Check if p_type is valid
                IF p_type != 'web' THEN
                    RETURN QUERY SELECT
                        'failed'::TEXT AS status,
                        'data process failed for requisition widget because your type is invalid'::TEXT AS message,
                        '{}'::JSONB AS requisition_data;
                    RETURN;
                END IF;

                -- Get all years with requisitions for this tenant
                SELECT ARRAY_AGG(DISTINCT TO_CHAR(requisition_date, 'YYYY') ORDER BY TO_CHAR(requisition_date, 'YYYY'))
                  INTO years_arr
                FROM asset_requisitions
                WHERE tenant_id = p_tenant_id;

                FOREACH year_rec IN ARRAY years_arr LOOP
                    -- Stats for the year
                    stats_obj := stats_obj || jsonb_build_object(
                        year_rec,
                        jsonb_build_object(
                            'totalRequisitions', (
                                SELECT COUNT(DISTINCT ar.id)
                                FROM asset_requisitions ar
                                WHERE ar.tenant_id = p_tenant_id
                                  AND TO_CHAR(ar.requisition_date, 'YYYY') = year_rec
                            ),
                            'totalItems', (
                                SELECT COUNT(ari.id)
                                FROM asset_requisitions ar
                                JOIN asset_requisitions_items ari ON ari.asset_requisition_id = ar.id
                                WHERE ar.tenant_id = p_tenant_id
                                  AND TO_CHAR(ar.requisition_date, 'YYYY') = year_rec
                            )
                        )
                    );

                    -- Monthly breakdowns
                    pending_arr := ARRAY[]::INTEGER[];
                    partial_arr := ARRAY[]::INTEGER[];
                    total_pending := 0;
                    total_partial := 0;
                    FOR month_idx IN 1..12 LOOP
                        -- Format month as 2 digits
                        DECLARE
                            month_str TEXT := months_arr[month_idx];
                            pending_count INTEGER := 0;
                            partial_count INTEGER := 0;
                        BEGIN
                            -- Fully Pending: all items in requisition have quantity = item_count
                            SELECT COUNT(*) INTO pending_count
                            FROM (
                                SELECT ar.id
                                FROM asset_requisitions ar
                                JOIN asset_requisitions_items ari ON ari.asset_requisition_id = ar.id
                                WHERE ar.tenant_id = p_tenant_id
                                  AND TO_CHAR(ar.requisition_date, 'YYYY') = year_rec
                                  AND TO_CHAR(ar.requisition_date, 'MM') = month_str
                                GROUP BY ar.id
                                HAVING BOOL_AND(ari.quantity = ari.item_count)
                            ) sub;

                            -- Partially Completed: at least one item quantity != item_count
                            SELECT COUNT(*) INTO partial_count
                            FROM (
                                SELECT ar.id
                                FROM asset_requisitions ar
                                JOIN asset_requisitions_items ari ON ari.asset_requisition_id = ar.id
                                WHERE ar.tenant_id = p_tenant_id
                                  AND TO_CHAR(ar.requisition_date, 'YYYY') = year_rec
                                  AND TO_CHAR(ar.requisition_date, 'MM') = month_str
                                GROUP BY ar.id
                                HAVING BOOL_OR(ari.quantity != ari.item_count) AND NOT BOOL_AND(ari.quantity != ari.item_count)
                            ) sub;

                            pending_arr := pending_arr || pending_count;
                            partial_arr := partial_arr || partial_count;
                            total_pending := total_pending + pending_count;
                            total_partial := total_partial + partial_count;
                        END;
                    END LOOP;
                    monthly_obj := monthly_obj || jsonb_build_object(
                        year_rec,
                        jsonb_build_object(
                            'pending', pending_arr,
                            'partial', partial_arr
                        )
                    );
                    yearly_pending := array_append(yearly_pending, total_pending);
                    yearly_partial := array_append(yearly_partial, total_partial);
                END LOOP;

                result_data := jsonb_build_object(
                    'years', years_arr,
                    'stats', stats_obj,
                    'monthly', monthly_obj,
                    'yearly', jsonb_build_object(
                        'pending', yearly_pending,
                        'partial', yearly_partial
                    )
                );

                RETURN QUERY SELECT
                    'SUCCESS'::TEXT AS status,
                    'requisition widget data proceced succesfully'::TEXT AS message,
                    result_data AS requisition_data;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_requisition_widget_data(BIGINT, TEXT);");
    }
};
