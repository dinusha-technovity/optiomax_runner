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
        // Change expected_duration to numeric(10,2)
        DB::statement('ALTER TABLE work_orders ALTER COLUMN expected_duration TYPE numeric(10,2) USING expected_duration::numeric;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert expected_duration back to integer
        DB::statement('ALTER TABLE work_orders ALTER COLUMN expected_duration TYPE integer USING expected_duration::integer;');
    }
};
