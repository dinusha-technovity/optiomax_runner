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
        // Direct asset transfer request items table
        Schema::create('direct_asset_transfer_request_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('direct_asset_transfer_request_id');
            $table->unsignedBigInteger('asset_item_id');
            
            // Current values (before transfer)
            $table->unsignedBigInteger('current_owner_id')->nullable();
            $table->unsignedBigInteger('current_department_id')->nullable();
            $table->decimal('current_location_latitude', 10, 8)->nullable();
            $table->decimal('current_location_longitude', 11, 8)->nullable();
            $table->text('current_location_address')->nullable();
            
            // New values (after transfer)
            $table->unsignedBigInteger('new_owner_id')->nullable();
            $table->unsignedBigInteger('new_department_id')->nullable();
            $table->decimal('new_location_latitude', 10, 8)->nullable();
            $table->decimal('new_location_longitude', 11, 8)->nullable();
            $table->text('new_location_address')->nullable();
            
            // Schedule reset flags (like requisition_based_asset_transfer_requwest_items)
            $table->boolean('is_reset_current_employee_schedule')->default(false);
            $table->boolean('is_reset_current_availability_schedule')->default(false);
            
            // Transfer execution and approval
            $table->boolean('is_transferred')->default(false);
            $table->timestamp('transferred_at')->nullable();
            $table->text('special_note')->nullable();
            
            // Asset owner approval (for scenarios where current owner needs to approve)
            $table->string('asset_owner_approval_status', 255)->nullable();
            $table->text('asset_owner_note')->nullable();
            $table->timestamp('asset_owner_action_date')->nullable();
            
            // Target person (new owner) approval - Item level review
            $table->string('target_person_approval_status', 255)->default('PENDING'); // PENDING, APPROVED, REJECTED
            $table->text('target_person_note')->nullable();
            $table->timestamp('target_person_action_date')->nullable();
            
            // Soft delete and active flag
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('direct_asset_transfer_request_id');
            $table->index('asset_item_id');
            $table->index(['tenant_id', 'direct_asset_transfer_request_id']);
            
            // Foreign keys
            $table->foreign('direct_asset_transfer_request_id', 'datri_transfer_request_fk')
                ->references('id')
                ->on('direct_asset_transfer_requests')
                ->onDelete('restrict');
            $table->foreign('asset_item_id')->references('id')->on('asset_items')->onDelete('restrict');
            $table->foreign('current_owner_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('new_owner_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('current_department_id')->references('id')->on('organization')->onDelete('set null');
            $table->foreign('new_department_id')->references('id')->on('organization')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('direct_asset_transfer_request_items');
    }
};