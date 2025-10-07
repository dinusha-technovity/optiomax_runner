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
        Schema::table('procurement_staff', function (Blueprint $table) {
            // Add new column and constraints
            $table->unsignedBigInteger('asset_category');
            $table->foreign('asset_category')->references('id')->on('asset_categories')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurement_staff', function (Blueprint $table) {
            // Drop new FK and unique constraint
            $table->dropForeign(['asset_category']);
            $table->dropColumn('asset_category');
        });
    }
};