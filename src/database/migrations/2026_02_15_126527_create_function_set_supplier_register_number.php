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
                    SELECT oid::regprocedure::text AS proc_signature
                    FROM pg_proc
                    WHERE proname = 'set_supplier_register_number'
                LOOP
                    EXECUTE format('DROP PROCEDURE %s CASCADE;', r.proc_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE PROCEDURE set_supplier_register_number(
                IN p_supplier_id BIGINT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                curr_val BIGINT;
                new_supplier_reg_no VARCHAR(50);
            BEGIN
                -- Generate new registration number
                SELECT nextval('supplier_id_seq') INTO curr_val;
                new_supplier_reg_no := 'SUPPLIER-' || LPAD(curr_val::TEXT, 4, '0');

                -- Update supplier record
                UPDATE suppliers
                SET supplier_reg_no = new_supplier_reg_no
                WHERE id = p_supplier_id;
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
            FOR r IN
                SELECT oid::regprocedure::text AS proc_signature
                FROM pg_proc
                WHERE proname = 'set_supplier_register_number'
            LOOP
                EXECUTE format('DROP PROCEDURE %s CASCADE;', r.proc_signature);
            END LOOP;
        END$$;
        SQL);
    }
};
