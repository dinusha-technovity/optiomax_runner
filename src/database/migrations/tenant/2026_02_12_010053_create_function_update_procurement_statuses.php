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

            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'update_procurement_statuses'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

        CREATE OR REPLACE FUNCTION update_procurement_statuses(
            IN p_procurement_id BIGINT,
            IN p_attempt_id BIGINT,
            IN p_updated_by BIGINT,
            IN p_updated_at TIMESTAMP WITH TIME ZONE,
            IN p_tenant_id BIGINT
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            old_proc JSONB;
            new_proc JSONB;
            old_attempt JSONB;
            new_attempt JSONB;
            v_closing_date DATE;
            v_request_id TEXT;
            supplier_rec RECORD;
        BEGIN
            SELECT to_jsonb(p), p.request_id INTO old_proc, v_request_id
            FROM procurements p WHERE id = p_procurement_id;
            IF NOT FOUND THEN
                RETURN QUERY SELECT 'FAILURE', format('Procurement ID %s not found', p_procurement_id);
                RETURN;
            END IF;

            SELECT to_jsonb(pa), pa.closing_date INTO old_attempt, v_closing_date
            FROM procurements_quotation_request_attempts pa WHERE id = p_attempt_id;
            IF NOT FOUND THEN
                RETURN QUERY SELECT 'FAILURE', format('Attempt ID %s not found', p_attempt_id);
                RETURN;
            END IF;

            IF p_updated_at < v_closing_date THEN
                RETURN QUERY SELECT 
                    'FAILURE',
                    format('Cannot mark COMPLETE before closing date for request_id %s', v_request_id);
                RETURN;
            END IF;

            UPDATE procurements
            SET 
                procurement_status = 'COMPLETE',
                updated_at = p_updated_at
            WHERE id = p_procurement_id;

            UPDATE procurements_quotation_request_attempts
            SET 
                request_attempts_status = 'CLOSED',
                updated_at = p_updated_at
            WHERE id = p_attempt_id;

            SELECT to_jsonb(p) INTO new_proc FROM procurements p WHERE id = p_procurement_id;
            SELECT to_jsonb(pa) INTO new_attempt FROM procurements_quotation_request_attempts pa WHERE id = p_attempt_id;

            -- Calculate supplier ratings for all suppliers in this attempt
            FOR supplier_rec IN 
                SELECT DISTINCT supplier_id 
                FROM procurement_attempt_request_items 
                WHERE attempted_id = p_attempt_id AND supplier_id IS NOT NULL
            LOOP
                PERFORM calculate_supplier_rating(
                    (supplier_rec.supplier_id)::BIGINT, 
                    'QUOTATION_RESPONDED', 
                    '{}'::JSONB, 
                    p_tenant_id
                );
            END LOOP;

            BEGIN
                PERFORM log_activity(
                    'update_procurement_status',
                    'Procurement status marked COMPLETE',
                    'procurement',
                    p_procurement_id,
                    'user',
                    p_updated_by,
                    jsonb_build_object('before', old_proc, 'after', new_proc),
                    p_tenant_id
                );
                PERFORM log_activity(
                    'update_request_attempt_status',
                    'Request attempt status marked CLOSED',
                    'procurements_quotation_request_attempts',
                    p_attempt_id,
                    'user',
                    p_updated_by,
                    jsonb_build_object('before', old_attempt, 'after', new_attempt),
                    p_tenant_id
                );

                
            EXCEPTION WHEN OTHERS THEN
                NULL;
            END;

            RETURN QUERY SELECT 
                'SUCCESS', 
                format('Procurement marked COMPLETE for request_id %s', v_request_id);
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS update_procurement_statuses(
            BIGINT, BIGINT, BIGINT, TIMESTAMP WITH TIME ZONE, BIGINT
        );");
    }
};
