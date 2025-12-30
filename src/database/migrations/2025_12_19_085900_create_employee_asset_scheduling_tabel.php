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
        Schema::create('employee_asset_scheduling', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_id');
            $table->timestampTz('start_datetime');
            $table->timestampTz('end_datetime');
            $table->string('status', 50)->nullable();
            $table->text('Note')->nullable();
            $table->boolean('recurring_enabled')->default(false);
            $table->string('recurring_pattern', 50)->nullable();
            $table->jsonb('recurring_config')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestampsTz(0);

            // Indexes for performance
            $table->index(['asset_id', 'start_datetime', 'end_datetime']);
            $table->index('tenant_id');
            $table->index('is_active');

            // Foreign key constraints
            $table->foreign('asset_id')->references('id')->on('asset_items')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_asset_scheduling');
    }
};