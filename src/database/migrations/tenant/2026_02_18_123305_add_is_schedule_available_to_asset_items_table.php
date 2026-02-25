<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('asset_items', function (Blueprint $table) {

            $table->boolean('is_schedule_available')
                  ->default(true)
                  ->after('booking_availability'); // change position if needed

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_items', function (Blueprint $table) {

            $table->dropColumn('is_schedule_available');

        });
    }
};
