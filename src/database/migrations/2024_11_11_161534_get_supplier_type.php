<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared('
            CREATE OR REPLACE PROCEDURE get_supplier_type(
                IN p_supplier_id INT,
                OUT supplier_result NUMERIC
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                SELECT supplier_type FROM suppliers WHERE id = p_supplier_id;
            END;
            $$;
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS get_supplier_type');
    }
};
