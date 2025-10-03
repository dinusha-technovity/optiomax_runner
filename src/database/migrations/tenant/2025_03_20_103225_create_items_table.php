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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('item_id')->nullable();
            $table->string('item_name')->nullable();
            $table->text('item_description')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('type_id')->nullable();
            $table->unsignedBigInteger('unit_of_measure_id')->nullable();
            $table->decimal('purchase_price', 15, 2);
            $table->unsignedBigInteger('purchase_price_currency_id');
            $table->decimal('selling_price', 15, 2);
            $table->unsignedBigInteger('selling_price_currency_id');
            $table->integer('max_inventory_level');
            $table->integer('min_inventory_level');
            $table->integer('re_order_level');
            $table->boolean('low_stock_alert')->default(false);
            $table->boolean('over_stock_alert')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->jsonb('image_links')->nullable();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('item_categories')->onDelete('restrict');
            $table->foreign('type_id')->references('id')->on('item_types')->onDelete('restrict');
            $table->foreign('unit_of_measure_id')->references('id')->on('measurements')->onDelete('restrict');
            $table->foreign('purchase_price_currency_id')->references('id')->on('currencies')->onDelete('restrict');
            $table->foreign('selling_price_currency_id')->references('id')->on('currencies')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
