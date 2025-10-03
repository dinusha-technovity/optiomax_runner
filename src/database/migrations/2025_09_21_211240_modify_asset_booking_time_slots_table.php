<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{ 
    public function up(): void
    {
        Schema::table('asset_booking_time_slots', function (Blueprint $table) {
            // Drop FK first
            $table->dropForeign(['availability_schedule_id']);
            // Drop column
            $table->dropColumn('availability_schedule_id');
        });

        Schema::table('asset_booking_time_slots', function (Blueprint $table) {
            // Add new column + FK
            $table->unsignedBigInteger('asset_availability_schedule_occurrences_id')->after('booking_id');

            $table->foreign('asset_availability_schedule_occurrences_id')
                  ->references('id')
                  ->on('asset_availability_schedule_occurrences')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('asset_booking_time_slots', function (Blueprint $table) {
            // Rollback new FK + column
            $table->dropForeign(['asset_availability_schedule_occurrences_id']);
            $table->dropColumn('asset_availability_schedule_occurrences_id');
        });

        Schema::table('asset_booking_time_slots', function (Blueprint $table) {
            // Restore old column
            $table->unsignedBigInteger('availability_schedule_id')->after('booking_id');

            $table->foreign('availability_schedule_id')
                  ->references('id')
                  ->on('asset_availability_schedules')
                  ->onDelete('restrict');
        });
    }
};