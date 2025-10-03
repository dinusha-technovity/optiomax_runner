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
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_asset_availability_schedule_occurrences'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION get_asset_availability_schedule_occurrences(
                p_action_type TEXT DEFAULT 'Normal',
                p_tenant_id BIGINT DEFAULT NULL,
                p_timezone TEXT DEFAULT NULL,
                p_schedule_id BIGINT DEFAULT NULL,
                p_asset_id BIGINT DEFAULT NULL,
                p_start_datetime TIMESTAMPTZ DEFAULT NULL,
                p_end_datetime TIMESTAMPTZ DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                occurrence_id BIGINT,
                schedule_id BIGINT,
                asset_id BIGINT,
                asset_name TEXT,
                occurrence_start TIMESTAMPTZ,
                occurrence_end TIMESTAMPTZ,
                schedule_title TEXT,
                schedule_start_datetime TIMESTAMPTZ,
                schedule_end_datetime TIMESTAMPTZ,
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
                cancellation_fee_enabled BOOLEAN,
                cancellation_fee_type BIGINT,
                cancellation_fee_amount NUMERIC,
                cancellation_fee_percentage NUMERIC,
                asset_booking_cancellation_refund_policy_type BIGINT,
                term_types JSONB,
                required_documents JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                occurrence_count INT;
            BEGIN
                -- Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT, 'Invalid tenant ID provided'::TEXT,
                        NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TEXT,
                        NULL::BIGINT, NULL::TEXT, NULL::BIGINT,
                        NULL::TEXT, NULL::NUMERIC, NULL::BIGINT, NULL::TEXT,
                        NULL::BIGINT, NULL::TEXT, NULL::BOOLEAN,
                        NULL::NUMERIC, NULL::TEXT, NULL::BOOLEAN,
                        NULL::TEXT, NULL::JSONB, NULL::JSONB,
                        NULL::BIGINT, NULL::TEXT,
                        NULL::BOOLEAN, NULL::INTEGER, NULL::BIGINT,
                        NULL::BOOLEAN, NULL::BIGINT, NULL::NUMERIC, NULL::NUMERIC, NULL::BIGINT,
                        NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                -- Count matching occurrences (via parent schedule filters)
                SELECT COUNT(*)
                INTO occurrence_count
                FROM asset_availability_schedule_occurrences occ
                JOIN asset_availability_schedules s ON occ.schedule_id = s.id
                WHERE (p_schedule_id IS NULL OR s.id = p_schedule_id)
                AND s.tenant_id = p_tenant_id
                AND (p_asset_id IS NULL OR s.asset_id = p_asset_id)
                AND (p_start_datetime IS NULL OR occ.occurrence_end >= p_start_datetime)
                AND (p_end_datetime IS NULL OR occ.occurrence_start <= p_end_datetime)
                AND s.deleted_at IS NULL
                AND s.is_active = TRUE
                AND (
                    p_action_type = 'Normal'
                    OR (p_action_type = 'PublishedInternalOnly' AND s.publish_status = 'PUBLISHED' AND s.visibility_id IN (1, 3))
                    OR (p_action_type = 'PublishedExternalOnly' AND s.publish_status = 'PUBLISHED' AND s.visibility_id IN (2, 3))
                    OR (p_action_type = 'PublishedAll' AND s.publish_status = 'PUBLISHED' AND s.visibility_id IN (1, 2, 3))
                )
                AND occ.deleted_at IS NULL
                AND occ.isactive = TRUE;

                IF occurrence_count = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT, 'No matching occurrences found'::TEXT,
                        NULL::BIGINT, NULL::BIGINT, NULL::BIGINT, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TEXT,
                        NULL::TIMESTAMPTZ, NULL::TIMESTAMPTZ, NULL::TEXT,
                        NULL::BIGINT, NULL::TEXT, NULL::BIGINT,
                        NULL::TEXT, NULL::NUMERIC, NULL::BIGINT, NULL::TEXT,
                        NULL::BIGINT, NULL::TEXT, NULL::BOOLEAN,
                        NULL::NUMERIC, NULL::TEXT, NULL::BOOLEAN,
                        NULL::TEXT, NULL::JSONB, NULL::JSONB,
                        NULL::BIGINT, NULL::TEXT,
                        NULL::BOOLEAN, NULL::INTEGER, NULL::BIGINT,
                        NULL::BOOLEAN, NULL::BIGINT, NULL::NUMERIC, NULL::NUMERIC, NULL::BIGINT,
                        NULL::JSONB, NULL::JSONB;
                    RETURN;
                END IF;

                -- Return results with all joined fields and related JSONB arrays
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Occurrences fetched successfully'::TEXT AS message,
                    occ.id AS occurrence_id,
                    s.id AS schedule_id,
                    s.asset_id,
                    a.name::TEXT AS asset_name,
                    occ.occurrence_start::timestamptz,
                    occ.occurrence_end::timestamptz,
                    s.title::TEXT AS schedule_title,
                    s.start_datetime::timestamptz AS schedule_start_datetime,
                    s.end_datetime::timestamptz AS schedule_end_datetime,
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
                    s.description,
                    s.recurring_enabled,
                    s.recurring_pattern::TEXT,
                    s.recurring_config,
                    s.attachment,
                    s.created_by,
                    u.name::TEXT AS creator_name,
                    s.cancellation_enabled,
                    s.cancellation_notice_period,
                    s.cancellation_notice_period_type,
                    s.cancellation_fee_enabled,
                    s.cancellation_fee_type,
                    s.cancellation_fee_amount,
                    s.cancellation_fee_percentage,
                    s.asset_booking_cancellation_refund_policy_type,
                    (
                        SELECT COALESCE(jsonb_agg(jsonb_build_object(
                            'id', tt.id,
                            'term_type_id', tt.term_type_id,
                            'created_at', tt.created_at,
                            'updated_at', tt.updated_at
                        )), '[]'::jsonb)
                        FROM asset_availability_schedule_term_types tt
                        WHERE tt.asset_availability_schedule_id = s.id
                    ) AS term_types,
                    (
                        SELECT COALESCE(jsonb_agg(jsonb_build_object(
                            'id', rd.id,
                            'document_category_field_id', rd.document_category_field_id,
                            'created_at', rd.created_at,
                            'updated_at', rd.updated_at
                        )), '[]'::jsonb)
                        FROM asset_availability_required_documents_for_booking rd
                        WHERE rd.asset_availability_schedule_id = s.id
                    ) AS required_documents
                FROM asset_availability_schedule_occurrences occ
                JOIN asset_availability_schedules s ON occ.schedule_id = s.id
                LEFT JOIN asset_items ai ON s.asset_id = ai.id
                LEFT JOIN assets a ON ai.asset_id = a.id
                LEFT JOIN asset_availability_visibility_types avt ON s.visibility_id = avt.id
                LEFT JOIN asset_booking_approval_types abt ON s.approval_type_id = abt.id
                LEFT JOIN currencies c ON s.rate_currency_type_id = c.id
                LEFT JOIN time_period_entries tpe ON s.rate_period_type_id = tpe.id
                LEFT JOIN users u ON s.created_by = u.id
                WHERE 
                    (p_schedule_id IS NULL OR s.id = p_schedule_id)
                    AND s.tenant_id = p_tenant_id
                    AND (p_asset_id IS NULL OR s.asset_id = p_asset_id)
                    AND (p_start_datetime IS NULL OR occ.occurrence_end >= p_start_datetime)
                    AND (p_end_datetime IS NULL OR occ.occurrence_start <= p_end_datetime)
                    AND s.deleted_at IS NULL
                    AND s.is_active = TRUE
                    AND (
                        p_action_type = 'Normal'
                        OR (p_action_type = 'PublishedInternalOnly' AND s.publish_status = 'PUBLISHED' AND s.visibility_id IN (1, 3))
                        OR (p_action_type = 'PublishedExternalOnly' AND s.publish_status = 'PUBLISHED' AND s.visibility_id IN (2, 3))
                        OR (p_action_type = 'PublishedAll' AND s.publish_status = 'PUBLISHED' AND s.visibility_id IN (1, 2, 3))
                    )
                    AND occ.deleted_at IS NULL
                    AND occ.isactive = TRUE;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_asset_availability_schedule_occurrences(TEXT, BIGINT, TEXT, BIGINT, BIGINT, TIMESTAMPTZ, TIMESTAMPTZ);");
    }
};
