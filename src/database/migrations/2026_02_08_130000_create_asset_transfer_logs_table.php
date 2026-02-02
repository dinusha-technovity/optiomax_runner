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
        Schema::create('asset_transfer_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_item_id');
            $table->unsignedBigInteger('transfer_request_item_id');
            $table->unsignedBigInteger('internal_requisition_id');
            $table->unsignedBigInteger('internal_requisition_item_id');
            
            // From (old values)
            $table->unsignedBigInteger('from_responsible_person')->nullable();
            $table->unsignedBigInteger('from_department')->nullable();
            $table->string('from_location_latitude')->nullable();
            $table->string('from_location_longitude')->nullable();
            
            // To (new values)
            $table->unsignedBigInteger('to_responsible_person')->nullable();
            $table->unsignedBigInteger('to_department')->nullable();
            $table->string('to_location_latitude')->nullable();
            $table->string('to_location_longitude')->nullable();
            
            // Approval details
            $table->string('approval_status', 50); // APPROVED, REJECTED
            $table->text('approval_note')->nullable();
            $table->timestamp('approval_date');
            $table->unsignedBigInteger('approved_by')->nullable();
            
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->foreign('asset_item_id')->references('id')->on('asset_items')->onDelete('restrict');
            $table->foreign('transfer_request_item_id')->references('id')->on('requisition_based_asset_transfer_requwest_items')->onDelete('restrict');
            $table->foreign('internal_requisition_id')->references('id')->on('internal_asset_requisitions')->onDelete('restrict');
            $table->foreign('internal_requisition_item_id')->references('id')->on('internal_asset_requisitions_items')->onDelete('restrict');
            $table->foreign('from_responsible_person')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('to_responsible_person')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('from_department')->references('id')->on('organization')->onDelete('restrict');
            $table->foreign('to_department')->references('id')->on('organization')->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_transfer_logs');
    }
};
