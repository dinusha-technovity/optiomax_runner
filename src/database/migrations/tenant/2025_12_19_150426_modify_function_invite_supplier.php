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
            -- Ensure a dedicated sequence exists for invited supplier reg numbers
            CREATE SEQUENCE IF NOT EXISTS inv_supplier_reg_no_seq START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;

            DO $$
            DECLARE
                r RECORD;
            BEGIN
                -- Drop all existing versions of the function
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'invite_supplier'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION invite_supplier(
                IN p_invite_data JSONB,              -- array of objects
                IN p_tenant_id BIGINT,
                IN p_current_time TIMESTAMP WITH TIME ZONE,
                IN p_user_id BIGINT,
                IN p_user_name VARCHAR
            )
            RETURNS JSONB
            LANGUAGE plpgsql
            AS $$
            DECLARE
                item JSONB;
                v_email TEXT;
                new_invite_token TEXT;
                v_expires_at TIMESTAMP WITH TIME ZONE;

                curr_val INT;
                generated_supplier_id BIGINT;
                new_supplier_reg_no VARCHAR(50);
                v_existing_data JSON;
                v_log_success BOOLEAN;
                p_supplier_register_status TEXT := 'INVITED';

                results JSONB := '[]'::jsonb;
            BEGIN
                ----------------------------------------------------------------------
                -- VALIDATE TENANT
                ----------------------------------------------------------------------
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN jsonb_build_array(
                        jsonb_build_object(
                            'email', NULL,
                            'status', 'FAILURE',
                            'message', 'Invalid tenant ID provided',
                            'supplier_id', NULL
                            -- 'supplier_reg_no', NULL
                        )
                    );
                END IF;

                ----------------------------------------------------------------------
                -- LOOP THROUGH INPUT JSON ARRAY
                ----------------------------------------------------------------------
                FOR item IN SELECT * FROM jsonb_array_elements(p_invite_data)
                LOOP
                    v_email := item->>'email';
                    new_invite_token := item->>'new_invite_token';
                    v_expires_at := (item->>'expires_at')::TIMESTAMP WITH TIME ZONE;

                    generated_supplier_id := NULL;
                    new_supplier_reg_no := NULL;

                    ----------------------------------------------------------------------
                    -- CHECK 1: Already existing supplier (not INVITED)
                    ----------------------------------------------------------------------
                    IF EXISTS (
                        SELECT 1 FROM suppliers 
                        WHERE tenant_id = p_tenant_id 
                        AND email = v_email
                        AND supplier_reg_status != 'INVITED'
                    ) THEN
                        results := results || jsonb_build_array(
                            jsonb_build_object(
                                'email', v_email,
                                'status', 'EXISTING',
                                'message', 'Supplier already exists',
                                'supplier_id', NULL
                                -- 'supplier_reg_no', NULL
                            )
                        );
                        CONTINUE;
                    END IF;

                    ----------------------------------------------------------------------
                    -- CHECK 2: existing pending invite
                    ----------------------------------------------------------------------
                    IF EXISTS (
                        SELECT 1 FROM supplier_invites
                        WHERE tenant_id = p_tenant_id 
                        AND email = v_email
                        AND status = 'pending'
                        AND expires_at >= p_current_time
                    ) THEN
                        results := results || jsonb_build_array(
                            jsonb_build_object(
                                'email', v_email,
                                'status', 'PENDING',
                                'message', 'Supplier already invited',
                                'supplier_id', NULL
                                -- 'supplier_reg_no', NULL
                            )
                        );
                        CONTINUE;
                    END IF;

                    ----------------------------------------------------------------------
                    -- FIND EXISTING SUPPLIER (INVITED)
                    ----------------------------------------------------------------------
                    SELECT id INTO generated_supplier_id
                    FROM suppliers
                    WHERE tenant_id = p_tenant_id AND email = v_email
                    LIMIT 1;

                    ----------------------------------------------------------------------
                    -- CREATE NEW SUPPLIER IF NOT EXISTS
                    ----------------------------------------------------------------------
                    IF generated_supplier_id IS NULL THEN
                        SELECT nextval('inv_supplier_reg_no_seq') INTO curr_val;
                        new_supplier_reg_no := 'INV-SPPLIER-' || LPAD(curr_val::TEXT, 4, '0');

                        INSERT INTO suppliers (
                            created_at, updated_at, supplier_reg_status, tenant_id, email, created_by
                        ) VALUES (
                            p_current_time, p_current_time,
                            p_supplier_register_status,
                            p_tenant_id, v_email, p_user_id
                        )
                        RETURNING id INTO generated_supplier_id;

                        UPDATE supplier_invites SET invite_reg_number = new_supplier_reg_no
                        WHERE suppliers_id = generated_supplier_id;
                    END IF;

                    ----------------------------------------------------------------------
                    -- OLD SNAPSHOT
                    ----------------------------------------------------------------------
                    SELECT row_to_json(s) INTO v_existing_data
                    FROM suppliers s WHERE s.id = generated_supplier_id;

                    ----------------------------------------------------------------------
                    -- INSERT INVITE
                    ----------------------------------------------------------------------
                    INSERT INTO supplier_invites (
                        token, email, suppliers_id, expires_at, tenant_id, created_at, updated_at
                    ) VALUES (
                        new_invite_token,
                        v_email,
                        generated_supplier_id,
                        v_expires_at,
                        p_tenant_id,
                        p_current_time,
                        p_current_time
                    );

                    ----------------------------------------------------------------------
                    -- LOG
                    ----------------------------------------------------------------------
                    BEGIN
                        PERFORM log_activity(
                            'invite_supplier_bulk',
                            format('User %s invited supplier %s', p_user_name, new_supplier_reg_no),
                            'suppliers',
                            generated_supplier_id,
                            'user',
                            p_user_id,
                            v_existing_data,
                            p_tenant_id
                        );
                    EXCEPTION WHEN OTHERS THEN
                        v_log_success := FALSE;
                    END;

                    ----------------------------------------------------------------------
                    -- APPEND SUCCESS
                    ----------------------------------------------------------------------
                    results := results || jsonb_build_array(
                        jsonb_build_object(
                            'email', v_email,
                            'status', 'SUCCESS',
                            'message', 'Supplier invited successfully',
                            'supplier_id', generated_supplier_id,
                            'supplier_reg_no', new_supplier_reg_no,
                            'invite_token', new_invite_token
                        )
                    );

                END LOOP;

                RETURN results;
            END;
            $$;

        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(<<<SQL
            DROP FUNCTION IF EXISTS invite_supplier;
            DROP SEQUENCE IF EXISTS inv_supplier_reg_no_seq;
        SQL);
    }
};
