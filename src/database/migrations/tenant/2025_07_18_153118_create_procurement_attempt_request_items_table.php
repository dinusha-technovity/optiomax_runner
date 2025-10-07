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
        Schema::create('procurement_attempt_request_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('procurement_id');
            $table->unsignedBigInteger('attempted_id');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->decimal('expected_budget_per_item', 10, 2)->nullable();
            $table->decimal('requested_quantity', 10, 2)->nullable();
            $table->jsonb('rfp_document')->nullable();
            $table->jsonb('attachment')->nullable();
            $table->date('required_date')->nullable();
            $table->text('message_to_supplier')->nullable();
            $table->boolean('is_receive_quotation')->default(false);
            $table->boolean('is_available_on_quotation')->default(false);
            $table->date('available_date')->nullable();
            $table->decimal('normal_price_per_item', 10, 2)->nullable();
            $table->decimal('with_tax_price_per_item', 10, 2)->nullable();
            $table->decimal('available_quantity', 10, 2)->nullable();
            $table->boolean('can_full_fill_requested_quantity')->default(false);
            $table->text('message_from_supplier')->nullable();
            $table->jsonb('supplier_terms_and_conditions')->nullable();
            $table->jsonb('supplier_attachment')->nullable();
            $table->decimal('delivery_cost', 10, 2)->nullable();
            $table->text('reason_for_not_available')->nullable();
            $table->unsignedBigInteger('quotation_submitted_by')->nullable();
            $table->unsignedInteger('quotation_version')->default(0);
            $table->boolean('is_selected_for_finalization')->default(false);
            $table->boolean('is_approved_with_finalization')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true); 
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps(); 

            $table->foreign('procurement_id')->references('id')->on('procurements')->onDelete('restrict');
            $table->foreign('attempted_id')->references('id')->on('procurements_quotation_request_attempts')->onDelete('restrict');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');
            $table->foreign('item_id')->references('id')->on('asset_requisitions_items')->onDelete('restrict');
            $table->foreign('quotation_submitted_by')->references('id')->on('users')->onDelete('restrict');
            
            $table->index(['procurement_id', 'attempted_id', 'supplier_id', 'item_id', 'quotation_submitted_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurement_attempt_request_items');
    }
};