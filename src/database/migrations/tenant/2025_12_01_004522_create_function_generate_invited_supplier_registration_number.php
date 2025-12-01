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
                -- Drop all existing versions of the function
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'generate_invited_supplier_reg_number'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;


            CREATE OR REPLACE FUNCTION generate_invited_supplier_reg_number(
                IN supplier_id BIGINT,
                IN p_tenant_id BIGINT
            )
            RETURNS JSONB
            LANGUAGE plpgsql
            AS $$
            DECLARE
                curr_val INT;
                new_supplier_reg_no VARCHAR(50);
                results JSONB := '[]'::jsonb;
            BEGIN
                -- Validate supplier existence
                IF NOT EXISTS (SELECT 1 FROM suppliers WHERE tenant_id = p_tenant_id AND id = supplier_id) THEN
                    results := results || jsonb_build_array(
                        jsonb_build_object(
                            'status', 'FAILED',
                            'message', 'Not a valid supplier ID'
                        )
                    );
                    RETURN results;  -- no CONTINUE allowed here
                END IF;

                -- Generate new registration number
                SELECT nextval('supplier_id_seq') INTO curr_val;
                new_supplier_reg_no := 'SUPPLIER-' || LPAD(curr_val::TEXT, 4, '0');

                -- Update supplier record
                UPDATE suppliers
                SET supplier_reg_no = new_supplier_reg_no
                WHERE id = supplier_id;

                -- Success JSON response
                results := results || jsonb_build_array(
                    jsonb_build_object(
                        'status', 'SUCCESS',
                        'message', 'Supplier registration number saved successfully',
                        'supplier_reg_no', new_supplier_reg_no
                    )
                );

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
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                -- Drop all existing versions of the function
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'generate_invited_supplier_reg_number'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
        SQL);
    }
};
