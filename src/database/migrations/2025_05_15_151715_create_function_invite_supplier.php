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
        // DB::unprepared(<<<SQL
        //     CREATE OR REPLACE FUNCTION invite_supplier(
        //         IN p_email VARCHAR(255),
        //         IN p_tenant_id BIGINT,
        //         IN p_current_time TIMESTAMP WITH TIME ZONE,
        //         IN p_user_id BIGINT,
        //         IN p_user_name VARCHAR
        //     )
        //     RETURNS TABLE (
        //         status TEXT,
        //         message TEXT,
        //         supplier_id BIGINT,
        //         supplier_reg_no VARCHAR(50)
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     DECLARE
        //         curr_val INT;
        //         generated_supplier_id BIGINT;
        //         new_supplier_reg_no VARCHAR(50);
        //         v_existing_data JSON;
        //         v_log_success BOOLEAN;
        //         p_supplier_register_status TEXT := 'INVITED'; -- Default register status
        //     BEGIN
        //         IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
        //             RETURN QUERY SELECT 
        //                 'FAILURE'::TEXT,
        //                 'Invalid tenant ID provided'::TEXT,
        //                 NULL::BIGINT,
        //                 NULL::VARCHAR;
        //             RETURN;
        //         END IF;

        //         -- Generate new supplier registration number
        //         SELECT nextval('supplier_id_seq') INTO curr_val;
        //         new_supplier_reg_no := 'SUPPLIER-' || LPAD(curr_val::TEXT, 4, '0');

        //         -- Insert into suppliers
        //         INSERT INTO suppliers (
        //             created_at, updated_at, supplier_reg_no, supplier_reg_status, tenant_id, supplier_primary_email
        //         )
        //         VALUES (
        //             p_current_time, p_current_time, new_supplier_reg_no, p_supplier_register_status, p_tenant_id, p_email
        //         )
        //         RETURNING suppliers.id, suppliers.supplier_reg_no INTO generated_supplier_id, new_supplier_reg_no;

        //         -- Fetch full inserted supplier row as JSON
        //         SELECT row_to_json(s) INTO v_existing_data
        //         FROM suppliers s
        //         WHERE s.id = generated_supplier_id;

        //         -- Log action
        //         BEGIN
        //             PERFORM log_activity(
        //                 'invite_supplier',
        //                 format('User %s invited supplier %s', p_user_name, new_supplier_reg_no),
        //                 'suppliers',
        //                 generated_supplier_id,
        //                 'user',
        //                 p_user_id,
        //                 v_existing_data,
        //                 p_tenant_id
        //             );
        //             v_log_success := TRUE;
        //         EXCEPTION WHEN OTHERS THEN
        //             v_log_success := FALSE;
        //         END;

        //         RETURN QUERY SELECT 
        //             'SUCCESS'::TEXT,
        //             'Supplier Invited successfully'::TEXT,
        //             generated_supplier_id,
        //             new_supplier_reg_no;
        //     END;
        //     $$;
        // SQL);

        // DB::unprepared(<<<SQL
        //     CREATE OR REPLACE FUNCTION invite_supplier(
        //         IN p_email VARCHAR(255),
        //         IN p_new_invite_token VARCHAR,
        //         IN p_tenant_id BIGINT,
        //         IN p_current_time TIMESTAMP WITH TIME ZONE,
        //         IN p_expires_at TIMESTAMP WITH TIME ZONE,
        //         IN p_user_id BIGINT,
        //         IN p_user_name VARCHAR
        //     )
        //     RETURNS TABLE (
        //         status TEXT,
        //         message TEXT,
        //         supplier_id BIGINT,
        //         supplier_reg_no VARCHAR(50)
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     DECLARE
        //         curr_val INT;
        //         generated_supplier_id BIGINT;
        //         new_supplier_reg_no VARCHAR(50);
        //         v_existing_data JSON;
        //         v_log_success BOOLEAN;
        //         p_supplier_register_status TEXT := 'INVITED';
        //     BEGIN
        //         IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
        //             RETURN QUERY SELECT 
        //                 'FAILURE'::TEXT,
        //                 'Invalid tenant ID provided'::TEXT,
        //                 NULL::BIGINT,
        //                 NULL::VARCHAR;
        //             RETURN;
        //         END IF;

        //         IF EXISTS (
        //             SELECT 1 FROM suppliers WHERE tenant_id = p_tenant_id AND email = p_email
        //         ) THEN
        //             RETURN QUERY SELECT 
        //                 'FAILURE',
        //                 'Supplier with this email already exists',
        //                 NULL,
        //                 NULL;
        //             RETURN;
        //         END IF;

        //         SELECT nextval('supplier_id_seq') INTO curr_val;
        //         new_supplier_reg_no := 'SUPPLIER-' || LPAD(curr_val::TEXT, 4, '0');

        //         INSERT INTO suppliers (
        //             created_at, updated_at, supplier_reg_no, supplier_reg_status, tenant_id, email
        //         )
        //         VALUES (
        //             p_current_time, p_current_time, new_supplier_reg_no, p_supplier_register_status, p_tenant_id, p_email
        //         )
        //         RETURNING suppliers.id, suppliers.supplier_reg_no INTO generated_supplier_id, new_supplier_reg_no;

        //         SELECT row_to_json(s) INTO v_existing_data
        //         FROM suppliers s
        //         WHERE s.id = generated_supplier_id;

        //         INSERT INTO supplier_invites (
        //             token, email, suppliers_id, expires_at, tenant_id, created_at, updated_at
        //         ) VALUES (
        //             p_new_invite_token, p_email, generated_supplier_id, p_expires_at, p_tenant_id, p_current_time, p_current_time
        //         );

        //         BEGIN
        //             PERFORM log_activity(
        //                 'invite_supplier',
        //                 format('User %s invited supplier %s', p_user_name, new_supplier_reg_no),
        //                 'suppliers',
        //                 generated_supplier_id,
        //                 'user',
        //                 p_user_id,
        //                 v_existing_data,
        //                 p_tenant_id
        //             );
        //             v_log_success := TRUE;
        //         EXCEPTION WHEN OTHERS THEN
        //             v_log_success := FALSE;
        //         END;

        //         RETURN QUERY SELECT 
        //             'SUCCESS'::TEXT,
        //             'Supplier Invited successfully'::TEXT,
        //             generated_supplier_id,
        //             new_supplier_reg_no;
        //     END;
        //     $$;
        // SQL);

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION invite_supplier(
                IN p_email VARCHAR(255),
                IN p_new_invite_token VARCHAR,
                IN p_tenant_id BIGINT,
                IN p_current_time TIMESTAMP WITH TIME ZONE,
                IN p_expires_at TIMESTAMP WITH TIME ZONE,
                IN p_user_id BIGINT,
                IN p_user_name VARCHAR
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                supplier_id BIGINT,
                supplier_reg_no VARCHAR(50)
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                curr_val INT;
                generated_supplier_id BIGINT;
                new_supplier_reg_no VARCHAR(50);
                v_existing_data JSON;
                v_log_success BOOLEAN;
                p_supplier_register_status TEXT := 'INVITED';
            BEGIN
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Invalid tenant ID provided'::TEXT,
                        NULL::BIGINT,
                        NULL::VARCHAR(50);
                    RETURN;
                END IF;

                IF EXISTS (
                    SELECT 1 FROM suppliers 
                    WHERE tenant_id = p_tenant_id 
                    AND email = p_email
                    AND supplier_reg_status != 'INVITED'
                ) THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Supplier with this email already exists'::TEXT,
                        NULL::BIGINT,
                        NULL::VARCHAR(50);
                    RETURN;
                END IF;

                IF EXISTS (
                    SELECT 1 FROM supplier_invites si
                    WHERE si.tenant_id = p_tenant_id 
                    AND si.email = p_email
                    AND si.status = 'pending'
                    AND si.expires_at >= p_current_time
                ) THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT,
                        'Supplier already invited. Check email.'::TEXT,
                        NULL::BIGINT,
                        NULL::VARCHAR(50);
                    RETURN;
                END IF;

                SELECT nextval('supplier_id_seq') INTO curr_val;
                new_supplier_reg_no := 'SUPPLIER-' || LPAD(curr_val::TEXT, 4, '0');

                INSERT INTO suppliers (
                    created_at, updated_at, supplier_reg_no, supplier_reg_status, tenant_id, email
                )
                VALUES (
                    p_current_time, p_current_time, new_supplier_reg_no, p_supplier_register_status, p_tenant_id, p_email
                )
                RETURNING suppliers.id, suppliers.supplier_reg_no INTO generated_supplier_id, new_supplier_reg_no;

                SELECT row_to_json(s) INTO v_existing_data
                FROM suppliers s
                WHERE s.id = generated_supplier_id;

                INSERT INTO supplier_invites (
                    token, email, suppliers_id, expires_at, tenant_id, created_at, updated_at
                ) VALUES (
                    p_new_invite_token, p_email, generated_supplier_id, p_expires_at, p_tenant_id, p_current_time, p_current_time
                );

                BEGIN
                    PERFORM log_activity(
                        'invite_supplier',
                        format('User %s invited supplier %s', p_user_name, new_supplier_reg_no),
                        'suppliers',
                        generated_supplier_id,
                        'user',
                        p_user_id,
                        v_existing_data,
                        p_tenant_id
                    );
                    v_log_success := TRUE;
                EXCEPTION WHEN OTHERS THEN
                    v_log_success := FALSE;
                END;

                RETURN QUERY SELECT 
                    'SUCCESS'::TEXT,
                    'Supplier Invited successfully'::TEXT,
                    generated_supplier_id,
                    new_supplier_reg_no;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS invite_supplier');
    }
};