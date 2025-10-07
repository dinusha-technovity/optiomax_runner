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
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
    
            // Identification and Basic Info
            $table->string('work_order_number')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            
            // Relationships
            $table->unsignedBigInteger('asset_item_id')->nullable();
            $table->unsignedBigInteger('technician_id')->nullable();
            $table->unsignedBigInteger('maintenance_type_id')->nullable();
            $table->unsignedBigInteger('budget_code_id')->nullable();
            $table->unsignedBigInteger('approved_supervisor_id')->nullable();

            $table->unsignedBigInteger('user_id')->nullable();


            
            // Classification
            $table->string('type');
            $table->string('priority');
            $table->string('status')->default('scheduled');
            $table->string('job_title');
            $table->text('job_title_description')->nullable();
            
            // Work Details
            $table->text('scope_of_work');
            $table->text('skills_certifications')->nullable();
            $table->text('risk_assessment')->nullable();
            $table->text('safety_instruction')->nullable();
            $table->text('compliance_note')->nullable();
            
            // Planning
            $table->dateTime('work_order_start')->nullable();
            $table->dateTime('work_order_end')->nullable();
            $table->integer('expected_duration')->nullable();
            $table->string('expected_duration_unit')->nullable();
            $table->decimal('labour_hours', 8, 2)->nullable();
            $table->decimal('est_cost', 10, 2)->nullable();
            
            // Related Documents
            $table->json('permit_documents')->nullable();
            $table->json('work_order_materials')->nullable();
            $table->json('work_order_equipments')->nullable();
            
            // Completion Details (to be filled after work)
            $table->dateTime('actual_work_order_start')->nullable();
            $table->dateTime('actual_work_order_end')->nullable();
            $table->text('completion_note')->nullable();
            $table->json('actual_used_materials')->nullable();
            $table->text('technician_comment')->nullable();
            $table->json('completion_images')->nullable();
            
            // Common columns
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('asset_item_id')->references('id')->on('asset_items')->onDelete('set null');
            $table->foreign('technician_id')->references('id')->on('work_order_technicians')->onDelete('set null');
            $table->foreign('maintenance_type_id')->references('id')->on('work_order_maintenance_types')->onDelete('set null');
            $table->foreign('budget_code_id')->references('id')->on('work_order_budget_codes')->onDelete('set null');
            $table->foreign('approved_supervisor_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
