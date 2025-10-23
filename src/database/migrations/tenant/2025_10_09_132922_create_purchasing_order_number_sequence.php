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
        DB::statement("
            CREATE SEQUENCE IF NOT EXISTS po_number_seq START WITH 1 INCREMENT BY 1;
        ");

        DB::statement("
            CREATE OR REPLACE FUNCTION generate_po_number(p_year TEXT)
            RETURNS TEXT AS $$
            DECLARE
                next_val BIGINT;
                formatted_val TEXT;
            BEGIN
                SELECT NEXTVAL('po_number_seq') INTO next_val;
                formatted_val := 'PO-' || p_year || '-' || LPAD(next_val::TEXT, 4, '0');
                RETURN formatted_val;
            END;
            $$ LANGUAGE plpgsql;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP FUNCTION IF EXISTS generate_po_number();");
        DB::statement("DROP SEQUENCE IF EXISTS po_number_seq;");
    }
};
