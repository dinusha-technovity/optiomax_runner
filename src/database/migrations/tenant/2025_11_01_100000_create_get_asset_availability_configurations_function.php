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
                WHERE proname = 'get_asset_availability_configurations'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_asset_availability_configurations(
            p_asset_id BIGINT DEFAULT NULL,
            p_tenant_id BIGINT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            config_id BIGINT,
            asset_id BIGINT,
            asset_name TEXT,
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
            config_created_at TIMESTAMPTZ,
            config_updated_at TIMESTAMPTZ
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            config_count INT;
        BEGIN
            -- Validate required parameters
            IF p_asset_id IS NULL OR p_tenant_id IS NULL OR p_asset_id <= 0 OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 'Invalid asset ID or tenant ID provided'::TEXT,
                    NULL::BIGINT, NULL::BIGINT, NULL::TEXT,
                    NULL::BIGINT, NULL::TEXT, NULL::BIGINT, NULL::TEXT,
                    NULL::NUMERIC, NULL::BIGINT, NULL::TEXT,
                    NULL::BIGINT, NULL::TEXT, NULL::BOOLEAN, NULL::NUMERIC,
                    NULL::JSONB, NULL::BOOLEAN, NULL::INTEGER, NULL::BIGINT, NULL::TEXT,
                    NULL::BOOLEAN, NULL::BIGINT, NULL::TEXT, NULL::NUMERIC, NULL::NUMERIC,
                    NULL::BIGINT, NULL::TEXT, NULL::JSONB, NULL::JSONB,
                    NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ;
                RETURN;
            END IF;

            -- Verify asset belongs to tenant
            IF NOT EXISTS (
                SELECT 1 FROM asset_items ai 
                WHERE ai.id = p_asset_id AND ai.tenant_id = p_tenant_id
            ) THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 'Asset not found or does not belong to tenant'::TEXT,
                    NULL::BIGINT, NULL::BIGINT, NULL::TEXT,
                    NULL::BIGINT, NULL::TEXT, NULL::BIGINT, NULL::TEXT,
                    NULL::NUMERIC, NULL::BIGINT, NULL::TEXT,
                    NULL::BIGINT, NULL::TEXT, NULL::BOOLEAN, NULL::NUMERIC,
                    NULL::JSONB, NULL::BOOLEAN, NULL::INTEGER, NULL::BIGINT, NULL::TEXT,
                    NULL::BOOLEAN, NULL::BIGINT, NULL::TEXT, NULL::NUMERIC, NULL::NUMERIC,
                    NULL::BIGINT, NULL::TEXT, NULL::JSONB, NULL::JSONB,
                    NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ;
                RETURN;
            END IF;

            -- Count matching configurations
            SELECT COUNT(*)
            INTO config_count
            FROM asset_availability_configurations cfg
            WHERE cfg.asset_items_id = p_asset_id;

            IF config_count = 0 THEN
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT, 'No configuration found for this asset'::TEXT,
                    NULL::BIGINT, NULL::BIGINT, NULL::TEXT,
                    NULL::BIGINT, NULL::TEXT, NULL::BIGINT, NULL::TEXT,
                    NULL::NUMERIC, NULL::BIGINT, NULL::TEXT,
                    NULL::BIGINT, NULL::TEXT, NULL::BOOLEAN, NULL::NUMERIC,
                    NULL::JSONB, NULL::BOOLEAN, NULL::INTEGER, NULL::BIGINT, NULL::TEXT,
                    NULL::BOOLEAN, NULL::BIGINT, NULL::TEXT, NULL::NUMERIC, NULL::NUMERIC,
                    NULL::BIGINT, NULL::TEXT, NULL::JSONB, NULL::JSONB,
                    NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ;
                RETURN;
            END IF;

            -- Return configuration data with related information
            RETURN QUERY
            SELECT
                'SUCCESS'::TEXT AS status,
                'Configuration fetched successfully'::TEXT AS message,
                cfg.id AS config_id,
                cfg.asset_items_id AS asset_id,
                a.name::TEXT AS asset_name,
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
                    WHERE tt.asset_items_id = cfg.asset_items_id
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
                    WHERE rd.asset_items_id = cfg.asset_items_id
                ) AS required_documents,
                cfg.created_at AS config_created_at,
                cfg.updated_at AS config_updated_at
            FROM asset_availability_configurations cfg
            LEFT JOIN asset_items ai ON cfg.asset_items_id = ai.id
            LEFT JOIN assets a ON ai.asset_id = a.id
            LEFT JOIN asset_availability_visibility_types avt ON cfg.visibility_id = avt.id
            LEFT JOIN asset_booking_approval_types abt ON cfg.approval_type_id = abt.id
            LEFT JOIN currencies c ON cfg.rate_currency_type_id = c.id
            LEFT JOIN time_period_entries tpe ON cfg.rate_period_type_id = tpe.id
            LEFT JOIN time_period_entries cnpt ON cfg.cancellation_notice_period_type = cnpt.id
            LEFT JOIN asset_booking_cancelling_fee_types cft ON cfg.cancellation_fee_type = cft.id
            LEFT JOIN asset_booking_cancellation_refund_policy_type rpt ON cfg.asset_booking_cancellation_refund_policy_type = rpt.id
            WHERE cfg.asset_items_id = p_asset_id;
        END;
        $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_asset_availability_configurations(BIGINT, BIGINT);");
    }
};