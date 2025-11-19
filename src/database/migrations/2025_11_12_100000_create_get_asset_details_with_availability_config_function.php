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
                WHERE proname = 'get_asset_details_with_availability_config'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_asset_details_with_availability_config(
            p_tenant_id BIGINT,
            p_asset_item_id BIGINT DEFAULT NULL,
            p_action TEXT DEFAULT 'availability_external',
            p_check_date DATE DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            id BIGINT,
            asset_id BIGINT,
            asset_name VARCHAR,
            model_number VARCHAR,
            serial_number VARCHAR,
            thumbnail_image JSONB,
            qr_code VARCHAR,
            asset_tag VARCHAR,
            assets_type_id BIGINT,
            assets_type_name VARCHAR,
            category_id BIGINT,
            category_name VARCHAR,
            sub_category_id BIGINT,
            sub_category_name VARCHAR,
            config_id BIGINT,
            visibility_id BIGINT,
            visibility_name TEXT,
            approval_type_id BIGINT,
            approval_type_name TEXT,
            rate NUMERIC,
            rate_currency_type_id BIGINT,
            rate_currency_name TEXT,
            rate_period_type_id BIGINT, 
            rate_period_name TEXT,
            deposit_required BOOLEAN,
            deposit_amount NUMERIC,
            attachment JSONB,
            cancellation_enabled BOOLEAN,
            cancellation_notice_period INTEGER,
            cancellation_notice_period_type BIGINT,
            cancellation_notice_period_name TEXT,
            cancellation_fee_enabled BOOLEAN,
            cancellation_fee_type BIGINT,
            cancellation_fee_type_name TEXT,
            cancellation_fee_amount NUMERIC,
            cancellation_fee_percentage NUMERIC,
            asset_booking_cancellation_refund_policy_type BIGINT,
            refund_policy_type_name TEXT,
            term_types JSONB,
            required_documents JSONB,
            tenant_id BIGINT
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_check_date DATE;
        BEGIN
            -- Set check date to provided value (no default, NULL means return all occurrences)
            v_check_date := p_check_date;

            -- Tenant check
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT,'Invalid tenant ID provided'::TEXT,
                    NULL::BIGINT,NULL::BIGINT,NULL::VARCHAR,NULL::VARCHAR,NULL::VARCHAR,
                    NULL::JSONB,NULL::VARCHAR,NULL::VARCHAR,NULL::BIGINT,NULL::VARCHAR,
                    NULL::BIGINT,NULL::VARCHAR,NULL::BIGINT,NULL::VARCHAR,
                    NULL::BIGINT,NULL::BIGINT,NULL::TEXT,NULL::BIGINT,NULL::TEXT,
                    NULL::NUMERIC,NULL::BIGINT,NULL::TEXT,NULL::BIGINT,NULL::TEXT,
                    NULL::BOOLEAN,NULL::NUMERIC,NULL::JSONB,NULL::BOOLEAN,
                    NULL::INTEGER,NULL::BIGINT,NULL::TEXT,NULL::BOOLEAN,
                    NULL::BIGINT,NULL::TEXT,NULL::NUMERIC,NULL::NUMERIC,
                    NULL::BIGINT,NULL::TEXT,NULL::JSONB,NULL::JSONB,NULL::BIGINT;
                RETURN;
            END IF;

            -- Asset item check
            IF p_asset_item_id IS NOT NULL AND p_asset_item_id < 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT,'Invalid asset item ID provided'::TEXT,
                    NULL::BIGINT,NULL::BIGINT,NULL::VARCHAR,NULL::VARCHAR,NULL::VARCHAR,
                    NULL::JSONB,NULL::VARCHAR,NULL::VARCHAR,NULL::BIGINT,NULL::VARCHAR,
                    NULL::BIGINT,NULL::VARCHAR,NULL::BIGINT,NULL::VARCHAR,
                    NULL::BIGINT,NULL::BIGINT,NULL::TEXT,NULL::BIGINT,NULL::TEXT,
                    NULL::NUMERIC,NULL::BIGINT,NULL::TEXT,NULL::BIGINT,NULL::TEXT,
                    NULL::BOOLEAN,NULL::NUMERIC,NULL::JSONB,NULL::BOOLEAN,
                    NULL::INTEGER,NULL::BIGINT,NULL::TEXT,NULL::BOOLEAN,
                    NULL::BIGINT,NULL::TEXT,NULL::NUMERIC,NULL::NUMERIC,
                    NULL::BIGINT,NULL::TEXT,NULL::JSONB,NULL::JSONB,NULL::BIGINT;
                RETURN;
            END IF;

            -- Action-based conditions
            IF p_action = 'availability_external' THEN
                RETURN QUERY
                SELECT DISTINCT
                    'SUCCESS'::TEXT,
                    'Assets with published schedules and correct visibility assigned to responsible_person retrieved successfully'::TEXT,
                    ai.id, 
                    a.id AS asset_id, 
                    a.name AS asset_name,
                    ai.model_number, 
                    ai.serial_number,
                    ai.thumbnail_image, 
                    ai.qr_code, 
                    ai.asset_tag,
                    ac.assets_type AS assets_type_id,
                    ast.name AS assets_type_name,
                    a.category AS category_id,
                    ac.name AS category_name,
                    a.sub_category AS sub_category_id,
                    assc.name AS sub_category_name,
                    cfg.id AS config_id,
                    cfg.visibility_id,
                    avt.name::TEXT AS visibility_name,
                    cfg.approval_type_id,
                    abt.name::TEXT AS approval_type_name,
                    cfg.rate,
                    cfg.rate_currency_type_id,
                    c.name::TEXT AS rate_currency_name,
                    cfg.rate_period_type_id,
                    tpe.name::TEXT AS rate_period_name,
                    cfg.deposit_required,
                    cfg.deposit_amount,
                    cfg.attachment,
                    cfg.cancellation_enabled,
                    cfg.cancellation_notice_period,
                    cfg.cancellation_notice_period_type,
                    cnpt.name::TEXT AS cancellation_notice_period_name,
                    cfg.cancellation_fee_enabled,
                    cfg.cancellation_fee_type,
                    cft.name::TEXT AS cancellation_fee_type_name,
                    cfg.cancellation_fee_amount,
                    cfg.cancellation_fee_percentage,
                    cfg.asset_booking_cancellation_refund_policy_type,
                    rpt.name::TEXT AS refund_policy_type_name,
                    (
                        SELECT COALESCE(jsonb_agg(jsonb_build_object(
                            'id', tt.id,
                            'term_type_id', tt.term_type_id,
                            'term_type_name', att.name,
                            'created_at', tt.created_at,
                            'updated_at', tt.updated_at
                        )), '[]'::jsonb)
                        FROM asset_availability_schedule_term_types tt
                        LEFT JOIN asset_availability_term_types att ON tt.term_type_id = att.id
                        WHERE tt.asset_items_id = ai.id
                    ) AS term_types,
                    (
                        SELECT COALESCE(jsonb_agg(jsonb_build_object(
                            'id', rd.id,
                            'document_category_field_id', rd.document_category_field_id,
                            'field_name', dcf.document_field_name,
                            'created_at', rd.created_at,
                            'updated_at', rd.updated_at
                        )), '[]'::jsonb)
                        FROM asset_availability_required_documents_for_booking rd
                        LEFT JOIN document_category_field dcf ON rd.document_category_field_id = dcf.id
                        WHERE rd.asset_items_id = ai.id
                    ) AS required_documents,
                    a.tenant_id AS tenant_id
                FROM asset_items ai
                INNER JOIN assets a ON ai.asset_id = a.id
                INNER JOIN asset_categories ac ON a.category = ac.id
                INNER JOIN assets_types ast ON ac.assets_type = ast.id
                INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
                LEFT JOIN asset_availability_configurations cfg ON cfg.asset_items_id = ai.id
                LEFT JOIN asset_availability_visibility_types avt ON cfg.visibility_id = avt.id
                LEFT JOIN asset_booking_approval_types abt ON cfg.approval_type_id = abt.id
                LEFT JOIN currencies c ON cfg.rate_currency_type_id = c.id
                LEFT JOIN time_period_entries tpe ON cfg.rate_period_type_id = tpe.id
                LEFT JOIN time_period_entries cnpt ON cfg.cancellation_notice_period_type = cnpt.id
                LEFT JOIN asset_booking_cancelling_fee_types cft ON cfg.cancellation_fee_type = cft.id
                LEFT JOIN asset_booking_cancellation_refund_policy_type rpt ON cfg.asset_booking_cancellation_refund_policy_type = rpt.id
                WHERE (ai.id = p_asset_item_id OR p_asset_item_id IS NULL OR p_asset_item_id <= 0)
                AND ai.tenant_id = p_tenant_id
                AND ai.deleted_at IS NULL
                AND ai.isactive = TRUE
                AND a.deleted_at IS NULL
                AND a.isactive = TRUE
                AND cfg.id IS NOT NULL
                AND cfg.visibility_id IN (2, 3)
                AND EXISTS (
                    SELECT 1
                    FROM asset_availability_schedules aas
                    WHERE aas.asset_id = ai.id
                    AND aas.tenant_id = p_tenant_id
                    AND aas.deleted_at IS NULL
                    AND aas.is_active = TRUE
                    AND aas.publish_status = 'PUBLISHED'
                    AND EXISTS (
                        SELECT 1
                        FROM asset_availability_schedule_occurrences o
                        WHERE o.schedule_id = aas.id
                            AND o.deleted_at IS NULL
                            AND o.is_cancelled = FALSE
                            AND o.isactive = TRUE
                            AND (
                                p_check_date IS NULL 
                                OR v_check_date BETWEEN DATE(o.occurrence_start) AND DATE(o.occurrence_end)
                            )
                    )
                );

            ELSIF p_action = 'availability_internal' THEN
                RETURN QUERY
                SELECT DISTINCT
                    'SUCCESS'::TEXT,
                    'Assets with published schedules and correct visibility for internal users retrieved successfully'::TEXT,
                    ai.id, 
                    a.id AS asset_id, 
                    a.name AS asset_name,
                    ai.model_number, 
                    ai.serial_number,
                    ai.thumbnail_image, 
                    ai.qr_code, 
                    ai.asset_tag,
                    ac.assets_type AS assets_type_id,
                    ast.name AS assets_type_name,
                    a.category AS category_id,
                    ac.name AS category_name,
                    a.sub_category AS sub_category_id,
                    assc.name AS sub_category_name,
                    cfg.id AS config_id,
                    cfg.visibility_id,
                    avt.name::TEXT AS visibility_name,
                    cfg.approval_type_id,
                    abt.name::TEXT AS approval_type_name,
                    cfg.rate,
                    cfg.rate_currency_type_id,
                    c.name::TEXT AS rate_currency_name,
                    cfg.rate_period_type_id,
                    tpe.name::TEXT AS rate_period_name,
                    cfg.deposit_required,
                    cfg.deposit_amount,
                    cfg.attachment,
                    cfg.cancellation_enabled,
                    cfg.cancellation_notice_period,
                    cfg.cancellation_notice_period_type,
                    cnpt.name::TEXT AS cancellation_notice_period_name,
                    cfg.cancellation_fee_enabled,
                    cfg.cancellation_fee_type,
                    cft.name::TEXT AS cancellation_fee_type_name,
                    cfg.cancellation_fee_amount,
                    cfg.cancellation_fee_percentage,
                    cfg.asset_booking_cancellation_refund_policy_type,
                    rpt.name::TEXT AS refund_policy_type_name,
                    (
                        SELECT COALESCE(jsonb_agg(jsonb_build_object(
                            'id', tt.id,
                            'term_type_id', tt.term_type_id,
                            'term_type_name', att.name,
                            'created_at', tt.created_at,
                            'updated_at', tt.updated_at
                        )), '[]'::jsonb)
                        FROM asset_availability_schedule_term_types tt
                        LEFT JOIN asset_availability_term_types att ON tt.term_type_id = att.id
                        WHERE tt.asset_items_id = ai.id
                    ) AS term_types,
                    (
                        SELECT COALESCE(jsonb_agg(jsonb_build_object(
                            'id', rd.id,
                            'document_category_field_id', rd.document_category_field_id,
                            'field_name', dcf.document_field_name,
                            'created_at', rd.created_at,
                            'updated_at', rd.updated_at
                        )), '[]'::jsonb)
                        FROM asset_availability_required_documents_for_booking rd
                        LEFT JOIN document_category_field dcf ON rd.document_category_field_id = dcf.id
                        WHERE rd.asset_items_id = ai.id
                    ) AS required_documents,
                    a.tenant_id AS tenant_id
                FROM asset_items ai
                INNER JOIN assets a ON ai.asset_id = a.id
                INNER JOIN asset_categories ac ON a.category = ac.id
                INNER JOIN assets_types ast ON ac.assets_type = ast.id
                INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
                LEFT JOIN asset_availability_configurations cfg ON cfg.asset_items_id = ai.id
                LEFT JOIN asset_availability_visibility_types avt ON cfg.visibility_id = avt.id
                LEFT JOIN asset_booking_approval_types abt ON cfg.approval_type_id = abt.id
                LEFT JOIN currencies c ON cfg.rate_currency_type_id = c.id
                LEFT JOIN time_period_entries tpe ON cfg.rate_period_type_id = tpe.id
                LEFT JOIN time_period_entries cnpt ON cfg.cancellation_notice_period_type = cnpt.id
                LEFT JOIN asset_booking_cancelling_fee_types cft ON cfg.cancellation_fee_type = cft.id
                LEFT JOIN asset_booking_cancellation_refund_policy_type rpt ON cfg.asset_booking_cancellation_refund_policy_type = rpt.id
                WHERE (ai.id = p_asset_item_id OR p_asset_item_id IS NULL OR p_asset_item_id <= 0)
                AND ai.tenant_id = p_tenant_id
                AND ai.deleted_at IS NULL
                AND ai.isactive = TRUE
                AND a.deleted_at IS NULL
                AND a.isactive = TRUE
                AND cfg.id IS NOT NULL
                AND cfg.visibility_id IN (1, 3)
                AND EXISTS (
                    SELECT 1
                    FROM asset_availability_schedules aas
                    WHERE aas.asset_id = ai.id
                    AND aas.tenant_id = p_tenant_id
                    AND aas.deleted_at IS NULL
                    AND aas.is_active = TRUE
                    AND aas.publish_status = 'PUBLISHED'
                    AND EXISTS (
                        SELECT 1
                        FROM asset_availability_schedule_occurrences o
                        WHERE o.schedule_id = aas.id
                            AND o.deleted_at IS NULL
                            AND o.is_cancelled = FALSE
                            AND o.isactive = TRUE
                            AND (
                                p_check_date IS NULL 
                                OR v_check_date BETWEEN DATE(o.occurrence_start) AND DATE(o.occurrence_end)
                            )
                    )
                );

            ELSIF p_action = 'availability' THEN
                RETURN QUERY
                SELECT DISTINCT
                    'SUCCESS'::TEXT,
                    'Assets with published schedules retrieved successfully'::TEXT,
                    ai.id, 
                    a.id AS asset_id, 
                    a.name AS asset_name,
                    ai.model_number, 
                    ai.serial_number,
                    ai.thumbnail_image, 
                    ai.qr_code, 
                    ai.asset_tag,
                    ac.assets_type AS assets_type_id,
                    ast.name AS assets_type_name,
                    a.category AS category_id,
                    ac.name AS category_name,
                    a.sub_category AS sub_category_id,
                    assc.name AS sub_category_name,
                    cfg.id AS config_id,
                    cfg.visibility_id,
                    avt.name::TEXT AS visibility_name,
                    cfg.approval_type_id,
                    abt.name::TEXT AS approval_type_name,
                    cfg.rate,
                    cfg.rate_currency_type_id,
                    c.name::TEXT AS rate_currency_name,
                    cfg.rate_period_type_id,
                    tpe.name::TEXT AS rate_period_name,
                    cfg.deposit_required,
                    cfg.deposit_amount,
                    cfg.attachment,
                    cfg.cancellation_enabled,
                    cfg.cancellation_notice_period,
                    cfg.cancellation_notice_period_type,
                    cnpt.name::TEXT AS cancellation_notice_period_name,
                    cfg.cancellation_fee_enabled,
                    cfg.cancellation_fee_type,
                    cft.name::TEXT AS cancellation_fee_type_name,
                    cfg.cancellation_fee_amount,
                    cfg.cancellation_fee_percentage,
                    cfg.asset_booking_cancellation_refund_policy_type,
                    rpt.name::TEXT AS refund_policy_type_name,
                    (
                        SELECT COALESCE(jsonb_agg(jsonb_build_object(
                            'id', tt.id,
                            'term_type_id', tt.term_type_id,
                            'term_type_name', att.name,
                            'created_at', tt.created_at,
                            'updated_at', tt.updated_at
                        )), '[]'::jsonb)
                        FROM asset_availability_schedule_term_types tt
                        LEFT JOIN asset_availability_term_types att ON tt.term_type_id = att.id
                        WHERE tt.asset_items_id = ai.id
                    ) AS term_types,
                    (
                        SELECT COALESCE(jsonb_agg(jsonb_build_object(
                            'id', rd.id,
                            'document_category_field_id', rd.document_category_field_id,
                            'field_name', dcf.document_field_name,
                            'created_at', rd.created_at,
                            'updated_at', rd.updated_at
                        )), '[]'::jsonb)
                        FROM asset_availability_required_documents_for_booking rd
                        LEFT JOIN document_category_field dcf ON rd.document_category_field_id = dcf.id
                        WHERE rd.asset_items_id = ai.id
                    ) AS required_documents,
                    a.tenant_id AS tenant_id
                FROM asset_items ai
                INNER JOIN assets a ON ai.asset_id = a.id
                INNER JOIN asset_categories ac ON a.category = ac.id
                INNER JOIN assets_types ast ON ac.assets_type = ast.id
                INNER JOIN asset_sub_categories assc ON a.sub_category = assc.id
                LEFT JOIN asset_availability_configurations cfg ON cfg.asset_items_id = ai.id
                LEFT JOIN asset_availability_visibility_types avt ON cfg.visibility_id = avt.id
                LEFT JOIN asset_booking_approval_types abt ON cfg.approval_type_id = abt.id
                LEFT JOIN currencies c ON cfg.rate_currency_type_id = c.id
                LEFT JOIN time_period_entries tpe ON cfg.rate_period_type_id = tpe.id
                LEFT JOIN time_period_entries cnpt ON cfg.cancellation_notice_period_type = cnpt.id
                LEFT JOIN asset_booking_cancelling_fee_types cft ON cfg.cancellation_fee_type = cft.id
                LEFT JOIN asset_booking_cancellation_refund_policy_type rpt ON cfg.asset_booking_cancellation_refund_policy_type = rpt.id
                WHERE (ai.id = p_asset_item_id OR p_asset_item_id IS NULL OR p_asset_item_id <= 0)
                AND ai.tenant_id = p_tenant_id
                AND ai.deleted_at IS NULL
                AND ai.isactive = TRUE
                AND a.deleted_at IS NULL
                AND a.isactive = TRUE
                AND cfg.id IS NOT NULL
                AND cfg.visibility_id IN (1, 2, 3)
                AND EXISTS (
                    SELECT 1
                    FROM asset_availability_schedules aas
                    WHERE aas.asset_id = ai.id
                    AND aas.tenant_id = p_tenant_id
                    AND aas.deleted_at IS NULL
                    AND aas.is_active = TRUE
                    AND aas.publish_status = 'PUBLISHED'
                    AND EXISTS (
                        SELECT 1
                        FROM asset_availability_schedule_occurrences o
                        WHERE o.schedule_id = aas.id
                            AND o.deleted_at IS NULL
                            AND o.is_cancelled = FALSE
                            AND o.isactive = TRUE
                            AND (
                                p_check_date IS NULL 
                                OR v_check_date BETWEEN DATE(o.occurrence_start) AND DATE(o.occurrence_end)
                            )
                    )
                );

            ELSE
                -- Default case: return error for unsupported action
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT,'Unsupported action. Use availability_external, availability_internal, or availability'::TEXT,
                    NULL::BIGINT,NULL::BIGINT,NULL::VARCHAR,NULL::VARCHAR,NULL::VARCHAR,
                    NULL::JSONB,NULL::VARCHAR,NULL::VARCHAR,NULL::BIGINT,NULL::VARCHAR,
                    NULL::BIGINT,NULL::VARCHAR,NULL::BIGINT,NULL::VARCHAR,
                    NULL::BIGINT,NULL::BIGINT,NULL::TEXT,NULL::BIGINT,NULL::TEXT,
                    NULL::NUMERIC,NULL::BIGINT,NULL::TEXT,NULL::BIGINT,NULL::TEXT,
                    NULL::BOOLEAN,NULL::NUMERIC,NULL::JSONB,NULL::BOOLEAN,
                    NULL::INTEGER,NULL::BIGINT,NULL::TEXT,NULL::BOOLEAN,
                    NULL::BIGINT,NULL::TEXT,NULL::NUMERIC,NULL::NUMERIC,
                    NULL::BIGINT,NULL::TEXT,NULL::JSONB,NULL::JSONB,NULL::BIGINT;
                RETURN;
            END IF;
        END;
        $$
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_details_with_availability_config(BIGINT, BIGINT, TEXT, DATE);');
    }
};