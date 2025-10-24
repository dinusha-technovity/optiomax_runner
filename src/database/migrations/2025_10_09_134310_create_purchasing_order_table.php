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
        Schema::create('purchasing_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 50)->unique();
            $table->unsignedBigInteger('supplier_id');
            $table->text('submit_officer_comment')->nullable(); //submit officer comment 
            $table->text('purchasing_officer_comment')->nullable(); //before workflow comment
            $table->string('supplier_res_status')->nullable();
            $table->text('supplier_comment')->nullable();
            $table->dateTimeTz('due_date')->nullable();
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->string('status', 30)->default('PENDING');
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('attempt_id')->nullable(); // optional, not FK
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->jsonb('finalized_report')->nullable();
            $table->unsignedBigInteger('workflow_queue_id')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->boolean('is_direct_approved')->default(false);
         
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('workflow_queue_id')->references('id')->on('workflow_request_queues')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchasing_orders');
    }
};
