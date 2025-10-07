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
        Schema::create('asset_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_id');
            $table->string('model_number')->nullable();
            $table->string('serial_number')->nullable();
            $table->jsonb('thumbnail_image')->nullable();
            $table->string('qr_code')->nullable();
            $table->decimal('item_value', 12, 2)->nullable();
            $table->jsonb('item_documents')->nullable(); 
            $table->unsignedBigInteger('supplier')->nullable();
            $table->string('purchase_order_number')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable(); 
            $table->unsignedBigInteger('purchase_type')->nullable();
            $table->text('other_purchase_details')->nullable();
            $table->jsonb('purchase_document')->nullable();
            $table->string('received_condition')->nullable(); 
            $table->string('warranty')->nullable();
            $table->date('warranty_exparing_at')->nullable();
            $table->string('insurance_number')->nullable();
            $table->date('insurance_exparing_at')->nullable();
            $table->jsonb('insurance_document')->nullable();
            $table->string('expected_life_time')->nullable();
            $table->decimal('depreciation_value', 5, 2)->nullable();
            $table->unsignedBigInteger('responsible_person')->nullable();
            $table->string('asset_location_latitude')->nullable();
            $table->string('asset_location_longitude')->nullable();
            $table->unsignedBigInteger('department')->nullable();
            $table->unsignedBigInteger('registered_by')->nullable();
            $table->jsonb('asset_classification')->nullable();
            $table->jsonb('reading_parameters')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->foreign('asset_id')->references('id')->on('assets')->onDelete('restrict');
            $table->foreign('supplier')->references('id')->on('suppliers')->onDelete('restrict');
            $table->foreign('purchase_type')->references('id')->on('asset_requisition_availability_types')->onDelete('restrict');
            $table->foreign('responsible_person')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('department')->references('id')->on('organization')->onDelete('restrict');
            $table->foreign('registered_by')->references('id')->on('users')->onDelete('restrict'); // Prevents cascading delete
        });
    }

    /**
     * Reverse the migrations. 
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_items');
    }
};