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
        Schema::create('asset_requisition_supplier_pivot', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('asset_requisition_type_id');
            $table->unsignedBigInteger('asset_requisition_data_id');
            $table->unsignedBigInteger('supplier_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('asset_requisition_type_id')->references('id')->on('asset_requisition_types')->onDelete('restrict');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');

            $table->unique([
                'tenant_id',
                'asset_requisition_type_id',
                'asset_requisition_data_id',
                'supplier_id'
            ], 'uq_arsp_composite');
        });

        DB::statement('CREATE INDEX idx_arsp_tenant_supplier ON asset_requisition_supplier_pivot (tenant_id, supplier_id)');
        DB::statement('CREATE INDEX idx_arsp_tenant_req_type ON asset_requisition_supplier_pivot (tenant_id, asset_requisition_type_id)');
    }

    /**
     * Run the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_requisition_supplier_pivot');
    }
};