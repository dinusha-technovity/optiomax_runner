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
        Schema::table('asset_availability_schedules', function (Blueprint $table) {
            $table->string('title', 250)->nullable()->after('term_type_id');
            $table->jsonb('attachment')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_availability_schedules', function (Blueprint $table) {
            $table->dropColumn('title');
            $table->dropColumn('attachment');
        });
    }
};