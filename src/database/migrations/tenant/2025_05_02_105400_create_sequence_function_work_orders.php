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
            CREATE SEQUENCE work_order_sequence
            START WITH 1
            INCREMENT BY 1
            MINVALUE 1
            NO MAXVALUE
            CACHE 1;

            CREATE OR REPLACE FUNCTION generate_work_order_number()
            RETURNS TEXT AS $$
            DECLARE
                next_val INTEGER;
                year_part TEXT;
                seq_part TEXT;
            BEGIN
                -- Get the next sequence value
                SELECT nextval('work_order_sequence') INTO next_val;
                
                -- Get current year
                year_part := to_char(CURRENT_DATE, 'YYYY');
                
                -- Format sequence part with leading zeros
                seq_part := lpad(next_val::TEXT, 3, '0');
                
                -- Return formatted work order number
                RETURN 'WO-' || year_part || '-' || seq_part;
            END;
            $$ LANGUAGE plpgsql;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS generate_work_order_number');
    }
};
