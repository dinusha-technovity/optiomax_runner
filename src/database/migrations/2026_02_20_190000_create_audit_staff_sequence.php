<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Create audit staff sequence for auditor_code generation
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- Create sequence for auditor_code generation
        CREATE SEQUENCE IF NOT EXISTS audit_staff_code_seq
            START WITH 1000
            INCREMENT BY 1
            MINVALUE 1000
            MAXVALUE 999999999
            CACHE 1;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP SEQUENCE IF EXISTS audit_staff_code_seq;');
    }
};
