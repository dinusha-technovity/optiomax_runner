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
        Schema::create('asset_requisition_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('decision_id')->nullable();
            $table->unsignedBigInteger('asset_requisition_id');
            $table->unsignedBigInteger('action_by');
            $table->string('action_type', 50);
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->text('additional_note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('decision_id')->references('id')->on('asset_requisition_decision')->onDelete('set null');
            $table->foreign('asset_requisition_id')->references('id')->on('asset_requisitions')->onDelete('cascade');
            $table->foreign('action_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('work_order_id')->references('id')->on('work_orders')->onDelete('set null');
        });

        DB::statement('CREATE INDEX idx_ara_tenant_req ON asset_requisition_actions (tenant_id, asset_requisition_id)');
        DB::statement('CREATE INDEX idx_ara_tenant_type ON asset_requisition_actions (tenant_id, action_type)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_requisition_actions');
    }
};
