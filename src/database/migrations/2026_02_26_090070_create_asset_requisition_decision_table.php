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
        Schema::create('asset_requisition_decision', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('asset_requisition_id');
            $table->unsignedBigInteger('requisition_type_id');
            $table->unsignedBigInteger('asset_requisition_data_id')->nullable();
            $table->string('status', 50);
            $table->boolean('is_get_action')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('asset_requisition_id', 'ard_asset_req_fk')->references('id')->on('asset_requisitions')->onDelete('cascade');
            $table->foreign('requisition_type_id', 'ard_req_type_fk')->references('id')->on('asset_requisition_types')->onDelete('restrict');
        });

        DB::statement('CREATE INDEX idx_ard_tenant_status ON asset_requisition_decision (tenant_id, status)');
        DB::statement('CREATE INDEX idx_ard_tenant_action ON asset_requisition_decision (tenant_id, is_get_action)');
        DB::statement('CREATE INDEX idx_ard_tenant_req ON asset_requisition_decision (tenant_id, asset_requisition_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_requisition_decision');
    }
};
