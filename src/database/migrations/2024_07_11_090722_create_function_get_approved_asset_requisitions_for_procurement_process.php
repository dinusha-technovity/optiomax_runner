<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    { 

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_auth_user_related_asset_type_with_approved_asset_requisition(
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
                -- Validate user ID
                IF _user_id IS NULL OR _user_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid user ID provided'::TEXT AS message,
                        NULL::BIGINT, NULL::JSONB, NULL::JSONB, NULL::TIMESTAMP, NULL::TIMESTAMP,
                        NULL::BIGINT, NULL::TEXT, NULL::DATE, NULL::TEXT;
                    RETURN;
                END IF;
        
                -- Validate tenant ID
                IF _tenant_id IS NULL OR _tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT, NULL::JSONB, NULL::JSONB, NULL::TIMESTAMP, NULL::TIMESTAMP,
                        NULL::BIGINT, NULL::TEXT, NULL::DATE, NULL::TEXT;
                    RETURN;
                END IF;
        
                -- Fetch and return the data
                RETURN QUERY
                SELECT 
                    'SUCCESS'::TEXT AS status,
                    'Approved requisitions retrieved successfully'::TEXT AS message,
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
                            'asset_type', at.name,
                            'files', ari.files,
                            'budget', ari.budget,
                            'period', ari.period,
                            'reason', ari.reason,
                            'priority', ari.priority,
                            'quantity', ari.quantity,
                            'period_to', ari.period_to,
                            'suppliers', ari.suppliers,
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
                    ar.requisition_by::BIGINT AS requisition_by,
                    ar.requisition_id::TEXT,
                    ar.requisition_date::DATE, -- Cast to DATE
                    ar.requisition_status::TEXT -- Cast to TEXT
                FROM
                    procurement_staff ps
                INNER JOIN asset_requisitions_items ari ON ps.asset_type_id = ari.asset_type
                INNER JOIN asset_requisitions ar ON ari.asset_requisition_id = ar.id
                INNER JOIN assets_types at ON ari.asset_type = at.id
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

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_auth_user_related_asset_type_with_approved_asset_requisition');
    }
};