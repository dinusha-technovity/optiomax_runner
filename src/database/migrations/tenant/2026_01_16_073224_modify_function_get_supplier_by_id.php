<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<SQL

            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_supplier_by_id'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION get_supplier_by_id(
            p_tenant_id BIGINT,
            p_supplier_id BIGINT
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            id BIGINT,
            name TEXT,
            address TEXT,
            description TEXT,
            supplier_type TEXT,
            supplier_reg_no TEXT,
            supplier_reg_status TEXT,
            supplier_asset_classes JSON,
            supplier_rating BIGINT,
            supplier_business_name TEXT,
            supplier_business_register_no TEXT,
            supplier_primary_email TEXT,
            supplier_secondary_email TEXT,
            supplier_br_attachment JSON,
            supplier_website TEXT,
            supplier_tel_no TEXT,
            contact_no_code BIGINT,
            supplier_mobile TEXT,
            mobile_no_code BIGINT,
            supplier_fax TEXT,
            supplier_city TEXT,
            supplier_location_latitude TEXT,
            supplier_location_longitude TEXT,
            contact_no JSON,
            email TEXT,
            asset_categories JSON,
            assigned_asset_count BIGINT,
            assigned_item_count BIGINT,
            workflow_details JSON
        )
        LANGUAGE plpgsql
        AS $$
        BEGIN
            -- Validation
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT
                    'FAILURE'::TEXT, 'Invalid tenant ID provided'::TEXT,
                    NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::JSON, NULL::BIGINT,
                    NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::JSON, NULL::TEXT, NULL::TEXT,
                    NULL::BIGINT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT,
                    NULL::JSON, NULL::TEXT, NULL::JSON,
                    NULL::BIGINT, NULL::BIGINT, NULL::JSON;
                RETURN;
            END IF;

            IF p_supplier_id IS NULL OR p_supplier_id <= 0 THEN
                RETURN QUERY SELECT
                    'FAILURE'::TEXT, 'Invalid supplier ID provided'::TEXT,
                    NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::JSON, NULL::BIGINT,
                    NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::JSON, NULL::TEXT, NULL::TEXT,
                    NULL::BIGINT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT,
                    NULL::JSON, NULL::TEXT, NULL::JSON,
                    NULL::BIGINT, NULL::BIGINT, NULL::JSON;
                RETURN;
            END IF;

            -- Check existence
            IF NOT EXISTS (
                SELECT 1
                FROM suppliers s
                WHERE s.id = p_supplier_id
                AND s.tenant_id = p_tenant_id
                AND s.deleted_at IS NULL
            ) THEN
                RETURN QUERY SELECT
                    'FAILURE'::TEXT, 'Supplier not found'::TEXT,
                    NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::JSON, NULL::BIGINT,
                    NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::JSON, NULL::TEXT, NULL::TEXT,
                    NULL::BIGINT, NULL::TEXT, NULL::BIGINT, NULL::TEXT, NULL::TEXT, NULL::TEXT, NULL::TEXT,
                    NULL::JSON, NULL::TEXT, NULL::JSON,
                    NULL::BIGINT, NULL::BIGINT, NULL::JSON;
                RETURN;
            END IF;

            -- Return supplier
            RETURN QUERY
            SELECT
                'SUCCESS'::TEXT,
                'Supplier details fetched successfully'::TEXT,
                s.id,
                s.name::TEXT,
                s.address::TEXT,
                s.description::TEXT,
                s.supplier_type::TEXT,
                s.supplier_reg_no::TEXT,
                s.supplier_reg_status::TEXT,
                s.supplier_asset_classes::JSON,
                s.supplier_rating,
                s.supplier_business_name::TEXT,
                s.supplier_business_register_no::TEXT,
                s.supplier_primary_email::TEXT,
                s.supplier_secondary_email::TEXT,
                s.supplier_br_attachment::JSON,
                s.supplier_website::TEXT,
                s.supplier_tel_no::TEXT,
                s.contact_no_code,
                s.supplier_mobile::TEXT,
                s.mobile_no_code,
                s.supplier_fax::TEXT,
                s.supplier_city::TEXT,
                s.supplier_location_latitude::TEXT,
                s.supplier_location_longitude::TEXT,
                s.contact_no::JSON,
                s.email::TEXT,

                -- asset categories (unchanged)
                (
                    SELECT COALESCE(
                        json_agg(json_build_object('name', ac.name, 'id', ac.id)),
                        '[]'::json
                    )
                    FROM asset_categories ac
                    WHERE ac.deleted_at IS NULL
                    AND ac.id IN (
                        SELECT (elem->>'id')::bigint
                        FROM jsonb_array_elements(
                            COALESCE(s.asset_categories, '[]'::jsonb)
                        ) AS elem
                    )
                ),

                -- assigned asset count (unchanged)
                (
                    SELECT COUNT(*)
                    FROM asset_items ai
                    WHERE ai.tenant_id = p_tenant_id
                    AND ai.supplier = p_supplier_id
                    AND ai.deleted_at IS NULL
                ),

                -- assigned item count (unchanged)
                (
                    SELECT COUNT(*)
                    FROM items i
                    INNER JOIN suppliers_for_item si
                        ON i.id = si.master_item_id
                    AND si.supplier_id = p_supplier_id
                    AND si.deleted_at IS NULL
                    WHERE i.tenant_id = p_tenant_id
                    AND i.deleted_at IS NULL
                    AND i.isactive = TRUE
                ),

                -- workflow details (ADDITIVE, NULL-SAFE)
                CASE
                    WHEN s.workflow_queue_id IS NOT NULL THEN
                        json_build_object(
                            'submit_date', wrqd.created_at,
                            'action_date', wrqd.updated_at,
                            'approver_user_id', wrqd.approver_user_id,
                            'approver_name', u.user_name,
                            'comment_for_action', wrqd.comment_for_action
                        )
                    ELSE NULL
                END AS workflow_details

            FROM suppliers s
            LEFT JOIN workflow_request_queue_details wrqd
                ON s.workflow_queue_id = wrqd.id
            LEFT JOIN users u
                ON wrqd.approver_user_id = u.id
            WHERE s.id = p_supplier_id
            AND s.tenant_id = p_tenant_id
            AND s.deleted_at IS NULL;

        END;
        $$;

        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_supplier_by_id');
    }
};
