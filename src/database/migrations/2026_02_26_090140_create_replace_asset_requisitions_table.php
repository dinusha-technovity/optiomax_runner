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
        Schema::create('replace_asset_requisitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('asset_requisition_id');
            $table->unsignedBigInteger('asset_id');
            $table->unsignedBigInteger('priority')->nullable();
            $table->unsignedBigInteger('mode_of_acquisition');
            $table->date('expected_date');
            $table->text('replacement_description');
            $table->text('other_reason')->nullable();
            $table->text('justification')->nullable();
            $table->jsonb('error_logs_performance_doc')->nullable();
            $table->jsonb('screenshots')->nullable();
            $table->jsonb('other_docs')->nullable();
            $table->jsonb('notified_maintenance_leaders')->nullable();
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->string('replace_requisition_number', 50);
            $table->string('status', 50)->default('PENDING');
            $table->boolean('is_came_from_upgrade_req')->default(false);
            $table->unsignedBigInteger('upgrade_action_id')->nullable();
            $table->boolean('is_disposal_recommended')->default(false);
            $table->unsignedBigInteger('disposal_recommended_type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('asset_requisition_id')->references('id')->on('asset_requisitions')->onDelete('cascade');
            $table->foreign('asset_id')->references('id')->on('asset_items')->onDelete('restrict');
            $table->foreign('priority')->references('id')->on('work_order_priority_levels')->onDelete('set null');
            $table->foreign('mode_of_acquisition')->references('id')->on('asset_requisition_availability_types')->onDelete('restrict');
            $table->foreign('work_order_id')->references('id')->on('work_orders')->onDelete('set null');
            $table->foreign('upgrade_action_id')->references('id')->on('asset_requisition_actions')->onDelete('set null');
            $table->foreign('disposal_recommended_type')->references('id')->on('asset_upgrade_replace_reasons')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');

            $table->unique(['tenant_id', 'replace_requisition_number'], 'uq_rar_tenant_number');
        });

        DB::statement('CREATE INDEX idx_rar_tenant_asset ON replace_asset_requisitions (tenant_id, asset_id)');
        DB::statement('CREATE INDEX idx_rar_tenant_status ON replace_asset_requisitions (tenant_id, status)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('replace_asset_requisitions');
    }
};
