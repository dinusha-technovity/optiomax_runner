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
                    WHERE proname = 'get_document_categories_with_fields'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION get_document_categories_with_fields(
                _tenant_id BIGINT DEFAULT NULL,
                p_category_id BIGINT DEFAULT NULL,
                p_action_type TEXT DEFAULT 'normal',   -- 'normal' or 'booking'
                p_asset_id BIGINT DEFAULT NULL          -- asset_id to filter required documents
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_required_field_ids BIGINT[];
            BEGIN
                -- If asset_id is provided, get the required document field IDs for that asset
                IF p_asset_id IS NOT NULL THEN
                    SELECT ARRAY_AGG(document_category_field_id)
                    INTO v_required_field_ids
                    FROM asset_availability_required_documents_for_booking
                    WHERE asset_items_id = p_asset_id
                    AND deleted_at IS NULL;
                END IF;

                IF p_action_type = 'booking' THEN
                    -- Booking type - categories with fields, without IDs
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT AS status,
                        'Categories with fields fetched successfully (booking mode)'::TEXT AS message,
                        COALESCE(
                            jsonb_agg(
                                jsonb_build_object(
                                    'category_name', dc.category_name,
                                    'category_description', dc.description,
                                    'category_tag', dc.category_tag,
                                    'isactive', dc.isactive,
                                    'tenant_id', dc.tenant_id,
                                    'created_by', dc.created_by,
                                    'created_at', dc.created_at,
                                    'updated_at', dc.updated_at,
                                    'fields', COALESCE(
                                        (
                                            SELECT jsonb_agg(
                                                jsonb_build_object(
                                                    'document_field_name', dcf.document_field_name,
                                                    'description', dcf.description,
                                                    'file_path', dcf.file_path,
                                                    'document_formats', dcf.document_formats,
                                                    'max_upload_count', dcf.max_upload_count,
                                                    'listable', dcf.listable,
                                                    'isactive', dcf.isactive,
                                                    'tenant_id', dcf.tenant_id,
                                                    'created_by', dcf.created_by,
                                                    'created_at', dcf.created_at,
                                                    'updated_at', dcf.updated_at
                                                )
                                            )
                                            FROM document_category_field dcf
                                            WHERE dcf.document_category_id = dc.id
                                            AND dcf.isactive = TRUE
                                            AND (_tenant_id IS NULL OR dcf.tenant_id = _tenant_id)
                                            AND (v_required_field_ids IS NULL OR dcf.id = ANY(v_required_field_ids))
                                        ),
                                        '[]'::jsonb
                                    )
                                )
                            ),
                            '[]'::jsonb
                        ) AS data
                    FROM document_category dc
                    WHERE (_tenant_id IS NULL OR dc.tenant_id = _tenant_id)
                    AND (p_category_id IS NULL OR dc.id = p_category_id)
                    AND (dc.isactive = TRUE OR dc.isactive IS NULL);

                ELSE
                    -- Normal type - categories with fields, with IDs
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT AS status,
                        'Categories with fields fetched successfully'::TEXT AS message,
                        COALESCE(
                            jsonb_agg(
                                jsonb_build_object(
                                    'id', dc.id,
                                    'category_name', dc.category_name,
                                    'category_description', dc.description,
                                    'category_tag', dc.category_tag,
                                    'isactive', dc.isactive,
                                    'tenant_id', dc.tenant_id,
                                    'created_by', dc.created_by,
                                    'created_at', dc.created_at,
                                    'updated_at', dc.updated_at,
                                    'fields', COALESCE(
                                        (
                                            SELECT jsonb_agg(
                                                jsonb_build_object(
                                                    'id', dcf.id,
                                                    'document_category_id', dcf.document_category_id,
                                                    'document_field_name', dcf.document_field_name,
                                                    'description', dcf.description,
                                                    'file_path', dcf.file_path,
                                                    'document_formats', dcf.document_formats,
                                                    'max_upload_count', dcf.max_upload_count,
                                                    'listable', dcf.listable,
                                                    'isactive', dcf.isactive,
                                                    'tenant_id', dcf.tenant_id,
                                                    'created_by', dcf.created_by,
                                                    'created_at', dcf.created_at,
                                                    'updated_at', dcf.updated_at
                                                )
                                            )
                                            FROM document_category_field dcf
                                            WHERE dcf.document_category_id = dc.id
                                            AND dcf.isactive = TRUE
                                            AND (_tenant_id IS NULL OR dcf.tenant_id = _tenant_id)
                                            AND (v_required_field_ids IS NULL OR dcf.id = ANY(v_required_field_ids))
                                        ),
                                        '[]'::jsonb
                                    )
                                )
                            ),
                            '[]'::jsonb
                        ) AS data
                    FROM document_category dc
                    WHERE (_tenant_id IS NULL OR dc.tenant_id = _tenant_id)
                    AND (p_category_id IS NULL OR dc.id = p_category_id)
                    AND (dc.isactive = TRUE OR dc.isactive IS NULL);
                END IF;

            END;
            $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_document_categories_with_fields(BIGINT, BIGINT, TEXT, BIGINT);');
    }
};
