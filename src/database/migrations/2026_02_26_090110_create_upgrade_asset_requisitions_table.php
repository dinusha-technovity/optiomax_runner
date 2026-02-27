<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('upgrade_asset_requisitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('asset_requisition_id');
            $table->unsignedBigInteger('asset_id');
            $table->text('other_reason')->nullable();
            $table->text('justification')->nullable();
            $table->text('upgrade_description');
            $table->unsignedBigInteger('priority')->nullable();
            $table->date('expected_date');
            $table->jsonb('error_logs_performance_doc')->nullable();
            $table->jsonb('screenshots')->nullable();
            $table->jsonb('other_docs')->nullable();
            $table->jsonb('notified_maintenance_leaders')->nullable();
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->string('status', 50)->default('PENDING');
            $table->boolean('is_recommend_for_transition')->default(false);
            $table->unsignedBigInteger('transition_log_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('asset_requisition_id')->references('id')->on('asset_requisitions')->onDelete('cascade');
            $table->foreign('asset_id')->references('id')->on('asset_items')->onDelete('restrict');
            $table->foreign('priority')->references('id')->on('work_order_priority_levels')->onDelete('set null');
            $table->foreign('work_order_id')->references('id')->on('work_orders')->onDelete('set null');
            $table->foreign('transition_log_id')->references('id')->on('asset_requisition_type_transition')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');

    
        });

        DB::statement('CREATE INDEX idx_uar_tenant_asset ON upgrade_asset_requisitions (tenant_id, asset_id)');
        DB::statement('CREATE INDEX idx_uar_tenant_status ON upgrade_asset_requisitions (tenant_id, status)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upgrade_asset_requisitions');
    }
};
