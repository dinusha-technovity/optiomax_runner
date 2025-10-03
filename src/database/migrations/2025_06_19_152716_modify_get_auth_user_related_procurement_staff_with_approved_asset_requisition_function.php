<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            DROP FUNCTION IF EXISTS get_auth_user_related_procurement_staff_with_approved_asset_requisition(
                BIGINT, BIGINT
            );

            CREATE OR REPLACE FUNCTION get_auth_user_related_procurement_staff_with_approved_asset_requisition(
                _user_id BIGINT,
                _tenant_id BIGINT
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                user_details JSONB,
                items JSONB,
                created_at TIMESTAMP,
                updated_at TIMESTAMP,
                requisition_by BIGINT,
                requisition_id TEXT,
                requisition_date DATE,
                requisition_status TEXT
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF _user_id IS NULL OR _user_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Invalid user ID provided'::TEXT,
                        NULL::BIGINT, NULL::JSONB, NULL::JSONB, NULL::TIMESTAMP, NULL::TIMESTAMP,
                        NULL::BIGINT, NULL::TEXT, NULL::DATE, NULL::TEXT;
                    RETURN;
                END IF;

                IF _tenant_id IS NULL OR _tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Invalid tenant ID provided'::TEXT,
                        NULL::BIGINT, NULL::JSONB, NULL::JSONB, NULL::TIMESTAMP, NULL::TIMESTAMP,
                        NULL::BIGINT, NULL::TEXT, NULL::DATE, NULL::TEXT;
                    RETURN;
                END IF;

                RETURN QUERY
                SELECT 
                    'SUCCESS'::TEXT,
                    'Approved requisitions retrieved successfully'::TEXT,
                    ar.id,
                    jsonb_build_object(
                        'name', u.name,
                        'username', u.user_name,
                        'contact_no', u.contact_no,
                        'profile_image', u.profile_image,
                        'email', u.email,
                        'address', u.address,
                        'employee_code', u.employee_code,
                        'user_description', u.user_description
                    ) AS user_details,
                    jsonb_agg(
                        jsonb_build_object(
                            'id', ari.id,
                            'item_name', ari.item_name,
                            'asset_type', ac.name,
                            'files', ari.files,
                            'budget', ari.budget,
                            'period', ari.period,
                            'reason', ari.reason,
                            'priority', ari.priority,
                            'priority_name', arpt.name,
                            'quantity', ari.quantity,
                            'period_to', ari.period_to,
                            'suppliers', (
                                SELECT jsonb_agg(
                                    jsonb_build_object(
                                        'id', s.id,
                                        'name', s.name,
                                        'email', s.email,
                                        'supplier_rating', s.supplier_rating
                                    )
                                )
                                FROM jsonb_array_elements(ari.suppliers::jsonb) elem
                                JOIN suppliers s ON s.id = (elem->>'id')::BIGINT
                            ),
                            'created_at', ari.created_at,
                            'updated_at', ari.updated_at,
                            'period_from', ari.period_from,
                            'item_details', ari.item_details,
                            'organization', jsonb_build_object(
                                'organizationName', org.data ->> 'organizationName',
                                'email', org.data ->> 'email',
                                'address', org.data ->> 'address',
                                'website', org.data ->> 'website',
                                'telephoneNumber', org.data ->> 'telephoneNumber',
                                'organizationDescription', org.data ->> 'organizationDescription'
                            ),
                            'period_status', ari.period_status,
                            'required_date', ari.required_date,
                            'upgrade_or_new', ari.upgrade_or_new,
                            'business_impact', ari.business_impact,
                            'consumables_kpi', ari.consumables_kpi,
                            'maintenance_kpi', ari.maintenance_kpi,
                            'availability_type', ari.availability_type,
                            'business_purpose', ari.business_purpose,
                            'expected_conditions', ari.expected_conditions,
                            'service_support_kpi', ari.service_support_kpi,
                            'asset_requisition_id', ari.asset_requisition_id
                        )
                    ) AS items,
                    ar.created_at,
                    ar.updated_at,
                    ar.requisition_by::BIGINT,
                    ar.requisition_id::TEXT,
                    ar.requisition_date::DATE,
                    ar.requisition_status::TEXT
                FROM
                    procurement_staff ps
                INNER JOIN asset_requisitions_items ari ON ps.asset_category = ari.asset_category
                INNER JOIN asset_requisitions ar ON ari.asset_requisition_id = ar.id
                INNER JOIN asset_categories ac ON ari.asset_category = ac.id
                INNER JOIN asset_requisition_priority_types arpt ON ari.priority = arpt.id
                LEFT JOIN public.users u ON u.id = CAST(ar.requisition_by AS BIGINT)
                LEFT JOIN public.organization org ON org.id = ari.organization
                WHERE
                    ps.user_id = _user_id
                    AND ps.tenant_id = _tenant_id
                    AND ps.deleted_at IS NULL
                    AND ps.isactive = TRUE
                    AND ar.requisition_status = 'APPROVED'
                GROUP BY 
                    ar.id, u.id, org.id;

            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_auth_user_related_procurement_staff_with_approved_asset_requisition');
    }
};