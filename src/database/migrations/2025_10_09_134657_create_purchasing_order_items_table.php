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
        Schema::create('purchasing_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('po_id');
            $table->unsignedBigInteger('procurement_finalized_item')->nullable();
            $table->unsignedBigInteger('procurement_attempt_request_item_id')->nullable();
            $table->unsignedBigInteger('requisition_id')->nullable();
            $table->decimal('quantity', 14, 2);
            $table->decimal('approved_quantity', 14, 2)->nullable();
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('total_price', 18, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('responded_supplier_id')->nullable();
            $table->boolean('can_supply')->nullable();
            $table->decimal('available_quantity', 14, 2)->nullable();
            $table->string('item_availability_status', 30)->nullable();
            $table->text('response_message')->nullable();
            $table->timestamp('respond_date')->useCurrent();
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->timestamps();

            $table->foreign('po_id')->references('id')->on('purchasing_orders')->onDelete('restrict');
            $table->foreign('procurement_finalized_item')->references('id')->on('procurement_finalize_items')->onDelete('restrict');
            $table->foreign('procurement_attempt_request_item_id')->references('id')->on('procurement_attempt_request_items')->onDelete('restrict');
            $table->foreign('requisition_id')->references('id')->on('asset_requisitions')->onDelete('restrict');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchasing_order_items');
    }
};
