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
        Schema::create('asset_requisition_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('asset_requisition_id');
            $table->unsignedBigInteger('log_type_id');
            $table->unsignedBigInteger('action_by');
            $table->timestamp('action_at')->useCurrent();
            $table->jsonb('payload')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('asset_requisition_id')->references('id')->on('asset_requisitions')->onDelete('cascade');
            $table->foreign('log_type_id')->references('id')->on('asset_requisition_log_types')->onDelete('restrict');
            $table->foreign('action_by')->references('id')->on('users')->onDelete('restrict');
        });

        DB::statement('CREATE INDEX idx_arl_tenant_req ON asset_requisition_logs (tenant_id, asset_requisition_id)');
        DB::statement('CREATE INDEX idx_arl_tenant_type ON asset_requisition_logs (tenant_id, log_type_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_requisition_logs');
    }
};
