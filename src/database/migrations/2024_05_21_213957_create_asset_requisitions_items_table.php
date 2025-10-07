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
        Schema::create('asset_requisitions_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_requisition_id')->constrained('asset_requisitions')->onDelete('cascade');
            $table->string('item_name', 255); 
            $table->unsignedBigInteger('asset_type')->nullable();
            $table->integer('quantity');
            $table->integer('item_count')->nullable();
            $table->integer('requested_budget')->nullable();
            $table->decimal('budget', 10, 2)->nullable();
            $table->string('business_purpose', 255);
            $table->string('upgrade_or_new', 255);
            $table->unsignedBigInteger('period_status')->nullable();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('period', 255)->nullable();
            $table->unsignedBigInteger('availability_type')->nullable();
            $table->unsignedBigInteger('priority')->nullable();
            $table->date('required_date');
            $table->unsignedBigInteger('organization')->nullable();
            $table->text('reason');
            $table->text('business_impact');
            $table->json('suppliers')->nullable();
            $table->json('files')->nullable();
            $table->json('item_details')->nullable();
            $table->string('expected_conditions', 255);
            $table->json('maintenance_kpi')->nullable();
            $table->json('service_support_kpi')->nullable();
            $table->json('consumables_kpi')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->foreign('asset_type')->references('id')->on('assets_types')->onDelete('set null');
            $table->foreign('period_status')->references('id')->on('asset_requisition_period_types')->onDelete('set null');
            $table->foreign('availability_type')->references('id')->on('asset_requisition_availability_types')->onDelete('set null');
            $table->foreign('priority')->references('id')->on('asset_requisition_priority_types')->onDelete('set null');
            $table->foreign('organization')->references('id')->on('organization')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_requisitions_items');
    }
};