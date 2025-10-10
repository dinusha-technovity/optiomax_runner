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
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_active_document_categories'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION get_active_document_categories(
                _tenant_id BIGINT DEFAULT NULL,
                p_id BIGINT DEFAULT NULL,
                p_action_type TEXT DEFAULT 'normal'
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                category_name VARCHAR,
                description TEXT,
                category_tag VARCHAR,
                isactive BOOLEAN,
                tenant_id BIGINT,
                created_by BIGINT,
                created_at TIMESTAMP,
                updated_at TIMESTAMP
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- If p_id is NULL, return all active categories (default behavior)
                IF p_id IS NULL THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT AS status,
                        'Active document categories fetched successfully'::TEXT AS message,
                        dc.id,
                        dc.category_name,
                        dc.description,
                        dc.category_tag,
                        dc.isactive,
                        dc.tenant_id,
                        dc.created_by,
                        dc.created_at,
                        dc.updated_at
                    FROM document_category dc
                    WHERE (_tenant_id IS NULL OR dc.tenant_id = _tenant_id)
                    AND (dc.isactive = TRUE OR dc.isactive IS NULL);
                    RETURN;
                END IF;

                -- If action type is 'booking', return same data but keep IDs empty
                IF p_action_type = 'booking' THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT AS status,
                        'Booking type - categories fetched without IDs'::TEXT AS message,
                        NULL::BIGINT AS id,
                        dc.category_name,
                        dc.description,
                        dc.category_tag,
                        dc.isactive,
                        dc.tenant_id,
                        dc.created_by,
                        dc.created_at,
                        dc.updated_at
                    FROM document_category dc
                    WHERE (_tenant_id IS NULL OR dc.tenant_id = _tenant_id)
                    AND dc.id = p_id
                    AND (dc.isactive = TRUE OR dc.isactive IS NULL);

                ELSE
                    -- Normal mode: fetch relevant category normally
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT AS status,
                        'Active document categories fetched successfully'::TEXT AS message,
                        dc.id,
                        dc.category_name,
                        dc.description,
                        dc.category_tag,
                        dc.isactive,
                        dc.tenant_id,
                        dc.created_by,
                        dc.created_at,
                        dc.updated_at
                    FROM document_category dc
                    WHERE (_tenant_id IS NULL OR dc.tenant_id = _tenant_id)
                    AND dc.id = p_id
                    AND (dc.isactive = TRUE OR dc.isactive IS NULL);
                END IF;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_active_document_categories(BIGINT, BIGINT, TEXT);');
    }
};
