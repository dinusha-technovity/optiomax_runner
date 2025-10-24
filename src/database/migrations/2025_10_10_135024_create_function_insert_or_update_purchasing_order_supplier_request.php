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
                WHERE proname = 'insert_or_update_purchasing_order_supplier_request'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION insert_or_update_purchasing_order_supplier_request(
            IN p_request_id BIGINT,
            IN p_request_token TEXT,
            IN p_expires_at TIMESTAMPTZ,
            IN p_email VARCHAR(255),
            IN p_purchasing_order_num VARCHAR(255),
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
            IF p_purchasing_order_num IS NULL THEN
                RETURN QUERY SELECT 'FAILURE', 'Purchasing order number cannot be null', NULL::JSONB, NULL::JSONB;
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
                INSERT INTO purchasing_order_supplier_request (
                    token, 
                    email, 
                    purchasing_order_number, 
                    suppliers_id, 
                    expires_at, 
                    tenant_id,
                    created_at, 
                    updated_at
                )
                VALUES (
                    p_request_token, 
                    p_email, 
                    p_purchasing_order_num, 
                    p_suppliers_id, 
                    p_expires_at, 
                    p_tenant_id, 
                    p_current_time, 
                    p_current_time
                )
                RETURNING to_jsonb(purchasing_order_supplier_request) INTO new_record;

                BEGIN
                    PERFORM log_activity(
                        'insert_purchasing_order_supplier_request',
                        format('User %s inserted purchasing_order_supplier_request', p_causer_name),
                        'purchasing_order_supplier_request',
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
                FROM purchasing_order_supplier_request sqr
                WHERE id = p_request_id;

                UPDATE purchasing_order_supplier_request
                SET
                    token = p_request_token,
                    email = p_email,
                    procurements_id = p_purchasing_order_num,
                    suppliers_id = p_suppliers_id,
                    expires_at = p_expires_at,
                    tenant_id = p_tenant_id,
                    updated_at = p_current_time
                WHERE id = p_request_id;

                SELECT to_jsonb(sqr) INTO new_record
                FROM purchasing_order_supplier_request sqr
                WHERE id = p_request_id;

                BEGIN
                    PERFORM log_activity(
                        'update_purchasing_order_supplier_request',
                        format('User %s updated purchasing_order_supplier_request', p_causer_name),
                        'purchasing_order_supplier_request',
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
        DB::unprepared('DROP FUNCTION IF EXISTS insert_or_update_purchasing_order_supplier_request');
    }
};
