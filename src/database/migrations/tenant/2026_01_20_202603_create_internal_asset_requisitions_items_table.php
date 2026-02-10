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
        Schema::create('internal_asset_requisitions_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('internal_asset_requisition_id');
            $table->unsignedBigInteger('internal_asset_requisitions_item_selection_types_id');
            $table->unsignedBigInteger('asset_item_id')->nullable();
            $table->string('item_name', 255)->nullable();
            $table->decimal('required_quantity', 10, 2)->nullable();
            $table->decimal('fulfilled_quantity', 10, 2)->nullable()->default(0);
            $table->date('required_date');
            $table->unsignedBigInteger('priority')->nullable();
            $table->unsignedBigInteger('department')->nullable();
            $table->string('required_location_latitude')->nullable();
            $table->string('required_location_longitude')->nullable();
            $table->text('reason_for_requirement')->nullable();
            $table->text('additional_notes')->nullable();
            $table->jsonb('other_details')->nullable(); 
            $table->jsonb('related_documents')->nullable(); 
            $table->boolean('is_rejected_by_responsible_person')->default(false);
            $table->text('rejection_reason')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->foreign('internal_asset_requisition_id')->references('id')->on('internal_asset_requisitions')->onDelete('restrict');
            $table->foreign('internal_asset_requisitions_item_selection_types_id')->references('id')->on('internal_asset_requisitions_item_selection_types')->onDelete('restrict');
            $table->foreign('asset_item_id')->references('id')->on('asset_items')->onDelete('restrict');
            $table->foreign('priority')->references('id')->on('asset_requisition_priority_types')->onDelete('restrict');
            $table->foreign('department')->references('id')->on('organization')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internal_asset_requisitions_items');
    }
};