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
        Schema::create('requisition_based_asset_transfer_requwest_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('requisition_based_asset_transfer_requwest');
            $table->unsignedBigInteger('asset_item_id');
            $table->unsignedBigInteger('based_internal_asset_requisitions_items');
            $table->boolean('is_reset_current_employee_schedule')->default(false);
            $table->boolean('is_reset_current_availability_schedule')->default(false);
            $table->text('special_note')->nullable();
            $table->string('asset_requester_approval_status', 255)->nullable();
            $table->text('asset_requester_note')->nullable();
            $table->timestamp('asset_requester_action_date')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->foreign('requisition_based_asset_transfer_requwest')->references('id')->on('requisition_based_asset_transfer_requwest')->onDelete('restrict');
            $table->foreign('asset_item_id')->references('id')->on('asset_items')->onDelete('restrict');
            $table->foreign('based_internal_asset_requisitions_items')->references('id')->on('internal_asset_requisitions_items')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requisition_based_asset_transfer_requwest_items');
    }
};