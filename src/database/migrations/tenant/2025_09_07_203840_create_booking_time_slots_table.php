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
        Schema::create('asset_booking_time_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('availability_schedule_id');
            $table->timestampTz('start_datetime');
            $table->timestampTz('end_datetime');
            $table->decimal('duration_hours', 8, 2);
            $table->decimal('rate_applied', 10, 2)->nullable();
            $table->decimal('slot_cost', 15, 2)->nullable();
            $table->unsignedBigInteger('currency_code')->nullable();
            $table->unsignedBigInteger('approval_type_id')->nullable();
            $table->string('approval_status', 255)->default('APPROVED'); // PENDING, APPROVED, REJECTED
            $table->unsignedBigInteger('workflow_request_queues_id')->nullable();

            // Document metadata (not raw files)
            $table->jsonb('agreement_documents')->nullable(); // Store file links, type, etc.
            $table->jsonb('insurance_documents')->nullable();
            $table->jsonb('license_documents')->nullable();
            $table->jsonb('id_proof_documents')->nullable();
            $table->jsonb('any_attachment_document')->nullable();

            // Enhancements
            $table->string('partition_key', 50)->nullable();
            $table->jsonb('custom_attributes')->nullable();
            $table->ipAddress('created_ip')->nullable();
            $table->ipAddress('updated_ip')->nullable();

            $table->integer('sequence_order');
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->boolean('isactive')->default(true);
            $table->timestampTz('deleted_at')->nullable();
            $table->timestampsTz(0);

            // Foreign Keys
            $table->foreign('booking_id')->references('id')->on('asset_bookings')->onDelete('restrict');
            $table->foreign('availability_schedule_id')->references('id')->on('asset_availability_schedules')->onDelete('restrict');
            $table->foreign('workflow_request_queues_id')->references('id')->on('workflow_request_queues')->onDelete('restrict');
            $table->foreign('currency_code')->references('id')->on('currencies')->onDelete('restrict');
            $table->foreign('approval_type_id')->references('id')->on('asset_booking_approval_types')->onDelete('restrict');

            // Indexes
            $table->index(['booking_id', 'sequence_order']);
            $table->index(['availability_schedule_id', 'start_datetime', 'end_datetime']);
            $table->index(['tenant_id', 'availability_schedule_id']);
            $table->index('partition_key');
            $table->unique(['booking_id', 'sequence_order']); // Prevent slot order duplicates
        });

        // Constraints
        DB::statement('ALTER TABLE asset_booking_time_slots ADD CONSTRAINT valid_slot_datetime CHECK (end_datetime > start_datetime)');
        DB::statement('ALTER TABLE asset_booking_time_slots ADD CONSTRAINT valid_slot_duration CHECK (duration_hours > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_time_slots');
    }
};