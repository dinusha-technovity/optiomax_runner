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
        Schema::create('asset_requisition_reasons_pivot', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('asset_requisition_id');
            $table->unsignedBigInteger('asset_requisition_type_id');
            $table->unsignedBigInteger('asset_requisition_data_id');
            $table->unsignedBigInteger('reason_id');
            $table->timestamps();

            $table->foreign('asset_requisition_id')->references('id')->on('asset_requisitions')->onDelete('cascade');
            $table->foreign('asset_requisition_type_id')->references('id')->on('asset_requisition_types')->onDelete('restrict');
            $table->foreign('reason_id')->references('id')->on('asset_upgrade_replace_reasons')->onDelete('restrict');

            $table->unique([
                'tenant_id',
                'asset_requisition_id',
                'asset_requisition_type_id',
                'asset_requisition_data_id',
                'reason_id'
            ], 'uq_arrp_composite');
        });

        DB::statement('CREATE INDEX idx_arrp_tenant_req ON asset_requisition_reasons_pivot (tenant_id, asset_requisition_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_requisition_reasons_pivot');
    }
};
