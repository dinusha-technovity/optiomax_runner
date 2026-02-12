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
        // Main direct asset transfer requests table
        Schema::create('direct_asset_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_request_number', 255)->unique();
            $table->unsignedBigInteger('targeted_responsible_person')->nullable();
            $table->unsignedBigInteger('requester_id');
            $table->timestamp('requested_date');
            
            // Transfer type: OWNER_LOCATION_DEPARTMENT, LOCATION_DEPARTMENT, OWNER_ONLY, LOCATION_ONLY, DEPARTMENT_ONLY
            $table->string('transfer_type', 50);
            
            // Status: DRAFT, PENDING, APPROVED, REJECTED, CANCELLED, COMPLETED
            $table->string('transfer_status', 255)->nullable();
            
            // Workflow request queue reference
            $table->unsignedBigInteger('work_flow_request')->nullable();
            
            // Transfer reason and notes
            $table->text('transfer_reason');
            $table->text('special_note')->nullable();
            
            // Cancellation
            $table->boolean('is_cancelled')->default(false);
            $table->text('reason_for_cancellation')->nullable();
            
            // Target person receipt acknowledgement
            $table->boolean('is_received_by_target_person')->default(false);
            $table->timestamp('received_by_target_person_at')->nullable();
            
            // Soft delete and active flag
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['tenant_id', 'transfer_status']);
            $table->index('requester_id');
            $table->index('transfer_request_number');
            
            // Foreign keys
            $table->foreign('targeted_responsible_person')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('requester_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('work_flow_request')->references('id')->on('workflow_request_queues')->onDelete('restrict');
        });

        // Create sequence for transfer request numbers
        DB::statement("CREATE SEQUENCE IF NOT EXISTS direct_asset_transfer_request_number_seq START 1");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP SEQUENCE IF EXISTS direct_asset_transfer_request_number_seq");
        Schema::dropIfExists('direct_asset_transfer_requests');
    }
};