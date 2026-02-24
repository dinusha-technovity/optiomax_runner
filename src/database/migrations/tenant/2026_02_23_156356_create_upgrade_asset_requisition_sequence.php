<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE SEQUENCE IF NOT EXISTS upgrade_asset_requisition_id_seq START 1;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP SEQUENCE IF EXISTS upgrade_asset_requisition_id_seq;
        SQL);
    }
};
