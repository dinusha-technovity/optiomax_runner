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
            $table->unsignedBigInteger('assignee_type_id')
                  ->nullable()
                  ->after('id');

            $table->foreign('assignee_type_id')
                  ->references('id')
                  ->on('assignee_types')
                  ->onDelete('restrict');

            // Booking availability
            $table->boolean('booking_availability')
                  ->default(true)
                  ->after('assignee_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_items', function (Blueprint $table) {

            $table->dropForeign(['assignee_type_id']);
            $table->dropColumn('assignee_type_id');

            $table->dropColumn('booking_availability');
        });
    }
};
