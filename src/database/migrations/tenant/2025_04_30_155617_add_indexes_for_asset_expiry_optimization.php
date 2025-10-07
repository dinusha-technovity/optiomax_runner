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
            $table->index('warranty_exparing_at', 'idx_warranty_expiring');
            $table->index('insurance_exparing_at', 'idx_insurance_expiring');
            $table->index('responsible_person', 'idx_responsible_person');
        });

        Schema::table('asset_item_action_queries', function (Blueprint $table) {
            $table->index(['asset_item', 'created_at'], 'idx_action_queries_asset_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_items', function (Blueprint $table) {
            $table->dropIndex('idx_warranty_expiring');
            $table->dropIndex('idx_insurance_expiring');
            $table->dropIndex('idx_responsible_person');
        });

        Schema::table('asset_item_action_queries', function (Blueprint $table) {
            $table->dropIndex('idx_action_queries_asset_date');
        });
    }
};
