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
        Schema::create('requisition_based_asset_transfer_requwest', function (Blueprint $table) {
            $table->id();
            $table->string('requisition_id', 255);
            $table->unsignedBigInteger('based_asset_requisition')->nullable();
            $table->unsignedBigInteger('requisition_by')->nullable();
            $table->timestamp('requested_date');
            $table->string('requisition_status', 255)->nullable();
            $table->text('special_note')->nullable();
            $table->boolean('is_gatepass_required')->default(false);
            $table->boolean('is_cancelled')->default(true);
            $table->text('reason_for_cancellation')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->foreign('based_asset_requisition')->references('id')->on('internal_asset_requisitions')->onDelete('restrict');
            $table->foreign('requisition_by')->references('id')->on('users')->onDelete('restrict');
        });
    }
 
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requisition_based_asset_transfer_requwest');
    }
};