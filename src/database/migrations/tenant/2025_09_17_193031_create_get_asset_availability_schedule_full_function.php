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
            CREATE OR REPLACE FUNCTION get_asset_availability_schedule_full(
                p_schedule_id BIGINT DEFAULT NULL,
                p_tenant_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                schedule_id BIGINT,
                asset_id BIGINT,
                asset_name TEXT,
                title TEXT,
                start_datetime TIMESTAMPTZ,
                end_datetime TIMESTAMPTZ,
                publish_status TEXT,
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
                schedule_description TEXT,
                recurring_enabled BOOLEAN,
                recurring_pattern TEXT,
                recurring_config JSONB,
                attachment JSONB,
                created_by BIGINT,
                creator_name TEXT,
                cancellation_enabled BOOLEAN,
                cancellation_notice_period INTEGER,
                cancellation_notice_period_type BIGINT,
                cancellation_notice_period_type_name TEXT,
                cancellation_fee_enabled BOOLEAN,
                cancellation_fee_type BIGINT,
                cancellation_fee_type_name TEXT,
                cancellation_fee_amount NUMERIC,
                cancellation_fee_percentage NUMERIC,
                asset_booking_cancellation_refund_policy_type BIGINT,
                asset_booking_cancellation_refund_policy_type_name TEXT,
                occurrences JSONB,
                term_types JSONB,
                required_documents JSONB
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TEXT,
                        NULL::BIGINT, NULL::TEXT, NULL::BIGINT, NULL::TEXT,
                        NULL::NUMERIC, NULL::BIGINT, NULL::TEXT, NULL::BIGINT, NULL::TEXT,
                        NULL::BOOLEAN, NULL::NUMERIC, NULL::TEXT, NULL::BOOLEAN, NULL::TEXT,
                        NULL::JSONB, NULL::BIGINT, NULL::TEXT, NULL::BOOLEAN,
                        NULL::INTEGER, NULL::BIGINT, NULL::TEXT, NULL::BOOLEAN, NULL::BIGINT,
                        NULL::TEXT, NULL::NUMERIC, NULL::NUMERIC, NULL::BIGINT, NULL::TEXT,
                        NULL::JSONB, NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                -- Validate schedule ID
                IF p_schedule_id IS NULL OR p_schedule_id <= 0 THEN
                    RETURN QUERY SELECT
                        'FAILURE'::TEXT AS status,
                        'Invalid schedule ID provided'::TEXT AS message,
                        NULL::BIGINT, NULL::BIGINT, NULL::TEXT, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TEXT,
                        NULL::BIGINT, NULL::TEXT, NULL::BIGINT, NULL::TEXT,
                        NULL::NUMERIC, NULL::BIGINT, NULL::TEXT, NULL::BIGINT, NULL::TEXT,
                        NULL::BOOLEAN, NULL::NUMERIC, NULL::TEXT, NULL::BOOLEAN, NULL::TEXT,
                        NULL::JSONB, NULL::BIGINT, NULL::TEXT, NULL::BOOLEAN,
                        NULL::INTEGER, NULL::BIGINT, NULL::TEXT, NULL::BOOLEAN, NULL::BIGINT,
                        NULL::TEXT, NULL::NUMERIC, NULL::NUMERIC, NULL::BIGINT, NULL::TEXT,
                        NULL::JSONB, NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                -- Return the schedule with all related data
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Schedule fetched successfully'::TEXT AS message,
                    s.id AS schedule_id,
                    s.asset_id,
                    a.name::TEXT AS asset_name,
                    s.title::TEXT,
                    s.start_datetime::timestamptz,
                    s.end_datetime::timestamptz,
                    s.publish_status::TEXT,
                    s.visibility_id,
                    avt.name::TEXT AS visibility_name,
                    s.approval_type_id,
                    abt.name::TEXT AS approval_type_name,
                    s.rate,
                    s.rate_currency_type_id,
                    c.name::TEXT AS rate_currency_name,
                    s.rate_period_type_id,
                    tpe.name::TEXT AS rate_period_name,
                    s.deposit_required,
                    s.deposit_amount,
                    s.description AS schedule_description,
                    s.recurring_enabled,
                    s.recurring_pattern::TEXT,
                    s.recurring_config,
                    s.attachment,
                    s.created_by,
                    u.name::TEXT AS creator_name,
                    s.cancellation_enabled,
                    s.cancellation_notice_period,
                    s.cancellation_notice_period_type,
                    tpen.name::TEXT AS cancellation_notice_period_type_name,
                    s.cancellation_fee_enabled,
                    s.cancellation_fee_type,
                    acft.name::TEXT AS cancellation_fee_type_name,
                    s.cancellation_fee_amount,
                    s.cancellation_fee_percentage,
                    s.asset_booking_cancellation_refund_policy_type,
                    acrpt.name::TEXT AS asset_booking_cancellation_refund_policy_type_name,
                    (
                        SELECT COALESCE(jsonb_agg(jsonb_build_object(
                            'id', o.id,
                            'occurrence_start', o.occurrence_start,
                            'occurrence_end', o.occurrence_end,
                            'isactive', o.isactive,
                            'created_at', o.created_at,
                            'updated_at', o.updated_at,
                            'deleted_at', o.deleted_at
                        ) ORDER BY o.occurrence_start), '[]'::jsonb)
                        FROM asset_availability_schedule_occurrences o
                        WHERE o.schedule_id = s.id
                    ) AS occurrences,
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
                        WHERE tt.asset_availability_schedule_id = s.id
                    ) AS term_types,
                    (
                        SELECT COALESCE(jsonb_agg(jsonb_build_object(
                            'id', rd.id,
                            'document_category_field_id', rd.document_category_field_id,
                            'document_category_field_name', dcf.document_field_name,
                            'created_at', rd.created_at,
                            'updated_at', rd.updated_at
                        )), '[]'::jsonb)
                        FROM asset_availability_required_documents_for_booking rd
                        LEFT JOIN document_category_field dcf ON rd.document_category_field_id = dcf.id
                        WHERE rd.asset_availability_schedule_id = s.id
                    ) AS required_documents
                FROM asset_availability_schedules s
                LEFT JOIN asset_items ai ON s.asset_id = ai.id
                LEFT JOIN assets a ON ai.asset_id = a.id
                LEFT JOIN asset_availability_visibility_types avt ON s.visibility_id = avt.id
                LEFT JOIN asset_booking_approval_types abt ON s.approval_type_id = abt.id
                LEFT JOIN currencies c ON s.rate_currency_type_id = c.id
                LEFT JOIN time_period_entries tpe ON s.rate_period_type_id = tpe.id
                LEFT JOIN users u ON s.created_by = u.id
                LEFT JOIN time_period_entries tpen ON s.cancellation_notice_period_type = tpen.id
                LEFT JOIN asset_booking_cancelling_fee_types acft ON s.cancellation_fee_type = acft.id
                LEFT JOIN asset_booking_cancellation_refund_policy_type acrpt ON s.asset_booking_cancellation_refund_policy_type = acrpt.id
                WHERE s.id = p_schedule_id
                AND s.tenant_id = p_tenant_id
                AND s.deleted_at IS NULL;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_availability_schedule_full(BIGINT, BIGINT)');
    }
};