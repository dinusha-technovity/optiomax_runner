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
        Schema::table('app_widgets', function (Blueprint $table) {
            $table->boolean('is_enable_for_web_app')->default(true);
            $table->boolean('is_enable_for_mobile_app')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_widgets', function (Blueprint $table) {
            $table->dropColumn('is_enable_for_web_app');
            $table->dropColumn('is_enable_for_mobile_app');
        });
    }
};