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
        Schema::create('asset_availability_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_id');
            $table->timestampTz('start_datetime');
            $table->timestampTz('end_datetime');
            $table->string('publish_status', 50)->default('draft');
            $table->unsignedBigInteger('visibility_id')->nullable();
            $table->unsignedBigInteger('approval_type_id')->nullable();
            $table->unsignedBigInteger('term_type_id')->nullable();
            $table->decimal('rate', 15, 2)->nullable();
            $table->unsignedBigInteger('rate_currency_type_id')->nullable();
            $table->unsignedBigInteger('rate_period_type_id')->nullable();
            $table->boolean('deposit_required')->default(false);
            $table->decimal('deposit_amount', 15, 2)->nullable();
            $table->text('description')->nullable();
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
            $table->foreign('visibility_id')->references('id')->on('asset_availability_visibility_types')->onDelete('restrict');
            $table->foreign('approval_type_id')->references('id')->on('asset_booking_approval_types')->onDelete('restrict');
            $table->foreign('term_type_id')->references('id')->on('asset_availability_term_types')->onDelete('restrict');
            $table->foreign('rate_currency_type_id')->references('id')->on('currencies')->onDelete('restrict');
            $table->foreign('rate_period_type_id')->references('id')->on('time_period_entries')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_availability_schedules');
    }
};