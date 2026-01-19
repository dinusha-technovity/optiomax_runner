<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_schedule_related_employees', function (Blueprint $table) {
            // Drop old foreign key (employees)
            $table->dropForeign(['employee_id']);

            // Add new foreign key (users)
            $table->foreign('employee_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('asset_schedule_related_employees', function (Blueprint $table) {
            // Drop users FK
            $table->dropForeign(['employee_id']);

            // Restore employees FK
            $table->foreign('employee_id')
                  ->references('id')
                  ->on('employees')
                  ->onDelete('restrict');
        });
    }
};
