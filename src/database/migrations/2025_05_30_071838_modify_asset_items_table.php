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
            $table->string('asset_tag')->nullable();
            $table->unsignedBigInteger('purchase_cost_currency_id')->nullable();
            $table->unsignedBigInteger('warrenty_condition_type_id')->nullable();
            $table->unsignedBigInteger('item_value_currency_id')->nullable();
            $table->string('warrenty_usage_name')->nullable();
            $table->string('warranty_usage_value')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('depreciation_method')->nullable();
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('asset_items', function (Blueprint $table) {
            Schema::dropIfExists('asset_items');
        });
    }
};
