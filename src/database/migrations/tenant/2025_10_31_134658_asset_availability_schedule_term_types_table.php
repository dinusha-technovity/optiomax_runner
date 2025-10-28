<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_availability_schedule_term_types', function (Blueprint $table) {
            // Drop foreign key and column for asset_availability_schedule_id
            $table->dropForeign(['asset_availability_schedule_id']);
            $table->dropColumn('asset_availability_schedule_id');

            // Add asset_items_id with foreign key
            $table->unsignedBigInteger('asset_items_id');
            $table->foreign('asset_items_id')->references('id')->on('asset_items')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('asset_availability_schedule_term_types', function (Blueprint $table) {
            // Drop new foreign key and column
            $table->dropForeign(['asset_items_id']);
            $table->dropColumn('asset_items_id');

            // Restore asset_availability_schedule_id with foreign key
            $table->unsignedBigInteger('asset_availability_schedule_id');
            $table->foreign('asset_availability_schedule_id')->references('id')->on('asset_availability_schedules')->onDelete('cascade');
        });
    }
};