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
            $table->jsonb('consumables_kpi')->nullable();
            $table->jsonb('maintenance_kpi')->nullable();
            $table->jsonb('service_support_kpi')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_items', function (Blueprint $table) {
            $table->dropColumn('consumables_kpi');
            $table->dropColumn('maintenance_kpi');
            $table->dropColumn('service_support_kpi');
        });
    }
};
