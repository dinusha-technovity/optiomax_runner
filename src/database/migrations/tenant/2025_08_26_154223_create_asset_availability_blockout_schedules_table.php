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
        Schema::create('asset_availability_blockout_schedules', function (Blueprint $table) {
            $table->id(); 
            $table->unsignedBigInteger('asset_id');
            $table->timestampTz('block_start_datetime');
            $table->timestampTz('block_end_datetime');
            $table->string('publish_status', 50)->default('draft');
            $table->unsignedBigInteger('reason_type_id')->nullable();
            $table->string('custom_reason')->nullable();
            $table->text('description')->nullable();
            $table->boolean('recurring_enabled')->default(false);
            $table->string('recurring_pattern', 50)->nullable();
            $table->jsonb('recurring_config')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('is_active')->default(true); 
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestampsTz(0);

            $table->foreign('asset_id')->references('id')->on('asset_items')->onDelete('restrict');
            $table->foreign('reason_type_id')->references('id')->on('asset_availability_blockout_reason_types')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_availability_blockout_schedules');
    }
};