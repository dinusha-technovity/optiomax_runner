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
        Schema::create('procurement_finalize_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('procurement_id')->nullable();
            $table->unsignedBigInteger('finalize_items_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('asset_requisitions_id')->nullable();
            $table->unsignedBigInteger('asset_requisitions_item_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true); 
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps(); 

            $table->foreign('procurement_id')->references('id')->on('procurements')->onDelete('restrict');
            $table->foreign('finalize_items_id')->references('id')->on('procurement_attempt_request_items')->onDelete('restrict');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');
            $table->foreign('asset_requisitions_id')->references('id')->on('asset_requisitions')->onDelete('restrict');
            $table->foreign('asset_requisitions_item_id')->references('id')->on('asset_requisitions_items')->onDelete('restrict');

            $table->index(['procurement_id', 'finalize_items_id', 'supplier_id', 'asset_requisitions_id', 'asset_requisitions_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurement_finalize_items');
    }
};
