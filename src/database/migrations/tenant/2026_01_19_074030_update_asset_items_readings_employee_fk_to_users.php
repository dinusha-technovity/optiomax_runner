<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_items_readings', function (Blueprint $table) {
            // Drop old FK (employees)
            $table->dropForeign(['employee']);
        });

        // ⚠️ IMPORTANT: Fix existing data BEFORE adding new FK
        DB::statement("
            UPDATE asset_items_readings air
            SET employee = e.user_id
            FROM employees e
            WHERE air.employee = e.id
        ");

        Schema::table('asset_items_readings', function (Blueprint $table) {
            // Add new FK (users)
            $table->foreign('employee')
                  ->references('id')
                  ->on('users')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('asset_items_readings', function (Blueprint $table) {
            // Drop users FK
            $table->dropForeign(['employee']);
        });

        Schema::table('asset_items_readings', function (Blueprint $table) {
            // Restore employees FK
            $table->foreign('employee')
                  ->references('id')
                  ->on('employees')
                  ->onDelete('restrict');
        });
    }
};
