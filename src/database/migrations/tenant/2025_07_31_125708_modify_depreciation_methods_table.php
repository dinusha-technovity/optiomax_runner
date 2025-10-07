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
        Schema::table('depreciation_method_table', function (Blueprint $table) {
            $table->string('slug')->unique()->nullable();
            $table->decimal('multiplier', 4, 2)->default(2.00);
            $table->boolean('is_accelerated')->default(false);
            $table->boolean('requires_units')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('depreciation_method_table', function (Blueprint $table) {
            $table->dropColumn(['multiplier', 'is_accelerated', 'requires_units', 'slug']);
        });
    }
};
