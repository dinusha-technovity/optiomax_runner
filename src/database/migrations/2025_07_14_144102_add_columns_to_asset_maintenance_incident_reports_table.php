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
        Schema::table('asset_maintenance_incident_reports', function (Blueprint $table) {
            $table->string('report_number', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_maintenance_incident_reports', function (Blueprint $table) {
            $table->dropColumn([
                'report_number'
            ]);
        });
    }
};
