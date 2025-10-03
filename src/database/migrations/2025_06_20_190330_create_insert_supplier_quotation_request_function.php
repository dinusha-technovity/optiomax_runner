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
        CREATE OR REPLACE FUNCTION insert_or_update_supplier_quotation_request(
            IN p_request_id BIGINT,
            IN p_request_token TEXT,
            IN p_expires_at TIMESTAMPTZ,
            IN p_email VARCHAR(255),
            IN p_procurements_id BIGINT,
            IN p_suppliers_id BIGINT,
            IN p_tenant_id BIGINT,
            IN p_current_time TIMESTAMPTZ,
            IN p_causer_id BIGINT DEFAULT NULL,
            IN p_causer_name TEXT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            old_data JSONB,
            new_data JSONB
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            old_record JSONB;
            new_record JSONB;
            log_success BOOLEAN;
        BEGIN
            IF p_procurements_id IS NULL OR p_procurements_id = 0 THEN
                RETURN QUERY SELECT 'FAILURE', 'Procurements id cannot be null or zero', NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;

            IF p_suppliers_id IS NULL OR p_suppliers_id = 0 THEN
                RETURN QUERY SELECT 'FAILURE', 'Suppliers id cannot be null or zero', NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;

            IF p_tenant_id IS NULL OR p_tenant_id = 0 THEN
                RETURN QUERY SELECT 'FAILURE', 'Tenant id cannot be null or zero', NULL::JSONB, NULL::JSONB;
                RETURN;
            END IF;

            IF p_request_id IS NULL OR p_request_id = 0 THEN
                INSERT INTO supplier_quotation_request (
                    token, 
                    email, 
                    procurements_id, 
                    suppliers_id, 
                    expires_at, 
                    tenant_id,
                    created_at, 
                    updated_at
                )
                VALUES (
                    p_request_token, 
                    p_email, 
                    p_procurements_id, 
                    p_suppliers_id, 
                    p_expires_at, 
                    p_tenant_id, 
                    p_current_time, 
                    p_current_time
                )
                RETURNING to_jsonb(supplier_quotation_request) INTO new_record;

                BEGIN
                    PERFORM log_activity(
                        'insert_supplier_quotation_request',
                        format('User %s inserted supplier_quotation_request', p_causer_name),
                        'supplier_quotation_request',
                        (new_record->>'id')::BIGINT,
                        'user',
                        p_causer_id,
                        new_record,
                        p_tenant_id
                    );
                    log_success := TRUE;
                EXCEPTION WHEN OTHERS THEN
                    log_success := FALSE;
                END;

                RETURN QUERY SELECT 'SUCCESS', 'Record inserted successfully', NULL::JSONB, new_record;
            ELSE
                SELECT to_jsonb(sqr) INTO old_record
                FROM supplier_quotation_request sqr
                WHERE id = p_request_id;

                UPDATE supplier_quotation_request
                SET
                    token = p_request_token,
                    email = p_email,
                    procurements_id = p_procurements_id,
                    suppliers_id = p_suppliers_id,
                    expires_at = p_expires_at,
                    tenant_id = p_tenant_id,
                    updated_at = p_current_time
                WHERE id = p_request_id;

                SELECT to_jsonb(sqr) INTO new_record
                FROM supplier_quotation_request sqr
                WHERE id = p_request_id;

                BEGIN
                    PERFORM log_activity(
                        'update_supplier_quotation_request',
                        format('User %s updated supplier_quotation_request', p_causer_name),
                        'supplier_quotation_request',
                        p_request_id,
                        'user',
                        p_causer_id,
                        new_record,
                        p_tenant_id
                    );
                    log_success := TRUE;
                EXCEPTION WHEN OTHERS THEN
                    log_success := FALSE;
                END;

                RETURN QUERY SELECT 'SUCCESS', 'Record updated successfully', old_record, new_record;
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
        DB::unprepared('DROP FUNCTION IF EXISTS insert_or_update_supplier_quotation_request');
    }
};
