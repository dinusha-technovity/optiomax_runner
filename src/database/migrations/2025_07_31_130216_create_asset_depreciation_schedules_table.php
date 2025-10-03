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
        Schema::create('asset_depreciation_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_item_id');
            $table->integer('fiscal_year')->nullable();
            $table->integer('fiscal_month')->nullable();
            $table->decimal('book_value_start', 14, 2)->nullable();
            $table->decimal('depreciation_amount', 14, 2)->nullable();
            $table->decimal('book_value_end', 14, 2)->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->unsignedBigInteger('depreciation_method_id')->nullable();
            $table->date('record_date')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();

            $table->foreign('asset_item_id')->references('id')->on('asset_items')->onDelete('cascade');
            $table->foreign('depreciation_method_id')->references('id')->on('depreciation_method_table')->onDelete('set null');

            $table->timestamps();


            $table->index(['asset_item_id', 'depreciation_method_id', 'tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_depreciation_schedules');
    }
};
