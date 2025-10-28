<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop related functions first to avoid dependency issues
        DB::unprepared("DROP FUNCTION IF EXISTS insert_or_update_asset_booking_with_time_slots");
        DB::unprepared("DROP FUNCTION IF EXISTS get_auth_user_asset_bookings_details");
        DB::unprepared("DROP FUNCTION IF EXISTS get_asset_booking_time_slots");
        DB::unprepared("DROP FUNCTION IF EXISTS get_auth_asset_booking_time_slots");

        // Drop asset_booking_time_slots table first (child table with FK dependency)
        Schema::dropIfExists('asset_booking_time_slots');
        
        // Drop asset_bookings table (parent table)
        Schema::dropIfExists('asset_bookings');

        // Drop the sequence used for booking register numbers
        DB::statement('DROP SEQUENCE IF EXISTS asset_booking_register_number_seq CASCADE');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the sequence
        DB::statement('CREATE SEQUENCE IF NOT EXISTS asset_booking_register_number_seq START WITH 1');

        // Recreate asset_bookings table
        Schema::create('asset_bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_booking_id')->nullable(); // Hierarchical bookings
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('asset_id');
            $table->string('booking_register_number', 100)->unique();
            $table->string('booking_status', 255)->nullable();
            $table->unsignedBigInteger('booking_type_id')->nullable();
            $table->string('location_latitude')->nullable();
            $table->string('location_longitude')->nullable();
            $table->text('description')->nullable();
            $table->timestampTz('start_datetime');
            $table->timestampTz('end_datetime');
            $table->decimal('duration_hours', 8, 2);
            $table->string('contact_email', 255)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->integer('attendees_count')->default(1);
            $table->text('special_requirements')->nullable();
            $table->decimal('rate_applied', 10, 2)->nullable();
            $table->unsignedBigInteger('currency_code')->nullable();
            $table->decimal('total_cost', 15, 2)->nullable();
            $table->boolean('deposit_required')->default(false);
            $table->decimal('deposit_amount', 10, 2)->nullable();
            $table->boolean('deposit_paid')->default(false);
            $table->timestampTz('deposit_paid_at')->nullable();
            $table->boolean('approval_required')->default(false);
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->decimal('cancellation_fee', 10, 2)->default(0);
            $table->timestampTz('checked_in_at')->nullable();
            $table->unsignedBigInteger('checked_in_by')->nullable();
            $table->timestampTz('checked_out_at')->nullable();
            $table->unsignedBigInteger('checked_out_by')->nullable();
            $table->boolean('is_multi_slot')->default(false);
            $table->integer('slot_sequence')->default(1);
            $table->boolean('reminder_sent')->default(false);
            $table->timestampTz('reminder_sent_at')->nullable();
            $table->string('locale', 10)->default('en');
            $table->string('partition_key', 50)->nullable();
            $table->jsonb('custom_attributes')->nullable();
            $table->ipAddress('created_ip')->nullable();
            $table->ipAddress('updated_ip')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->boolean('isactive')->default(true);
            $table->timestampTz('deleted_at')->nullable();
            $table->timestampsTz(0);

            // Modified structure columns (from the modify migration)
            $table->unsignedBigInteger('asset_booking_purpose_or_use_case_type_id')->nullable();
            $table->text('custom_purpose_name')->nullable();
            $table->text('custom_purpose_description')->nullable();
            $table->unsignedBigInteger('booked_by_user_id')->nullable();
            $table->unsignedBigInteger('booked_by_customer_id')->nullable();
            $table->unsignedBigInteger('booking_created_by_user_id')->nullable();

            // Foreign Keys
            $table->foreign('parent_booking_id')->references('id')->on('asset_bookings')->onDelete('restrict');
            $table->foreign('organization_id')->references('id')->on('organization')->onDelete('restrict');
            $table->foreign('asset_id')->references('id')->on('asset_items')->onDelete('restrict');
            $table->foreign('booking_type_id')->references('id')->on('asset_booking_type')->onDelete('restrict');
            $table->foreign('currency_code')->references('id')->on('currencies')->onDelete('restrict');
            $table->foreign('cancelled_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('checked_in_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('checked_out_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('asset_booking_purpose_or_use_case_type_id', 'asset_bookings_purpose_type_fk')
                  ->references('id')
                  ->on('asset_booking_purpose_or_use_case_type')
                  ->onDelete('restrict');
            $table->foreign('booked_by_user_id')
                  ->references('id')->on('users')->onDelete('restrict');
            $table->foreign('booked_by_customer_id')
                  ->references('id')->on('customers')->onDelete('restrict');
            $table->foreign('booking_created_by_user_id')
                  ->references('id')->on('users')->onDelete('restrict');

            // Indexes
            $table->index(['organization_id', 'asset_id', 'start_datetime', 'end_datetime']);
            $table->index(['asset_id', 'start_datetime', 'end_datetime']);
            $table->index('parent_booking_id');
            $table->index(['tenant_id', 'asset_id']);
            $table->index('booking_register_number');
            $table->index('created_at');
            $table->index('partition_key');
        });

        // Recreate asset_booking_time_slots table
        Schema::create('asset_booking_time_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('asset_availability_schedule_occurrences_id');
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
            $table->foreign('asset_availability_schedule_occurrences_id')
                  ->references('id')
                  ->on('asset_availability_schedule_occurrences')
                  ->onDelete('restrict');
            $table->foreign('workflow_request_queues_id')->references('id')->on('workflow_request_queues')->onDelete('restrict');
            $table->foreign('currency_code')->references('id')->on('currencies')->onDelete('restrict');
            $table->foreign('approval_type_id')->references('id')->on('asset_booking_approval_types')->onDelete('restrict');

            // Indexes
            $table->index(['booking_id', 'sequence_order']);
            $table->index(['asset_availability_schedule_occurrences_id', 'start_datetime', 'end_datetime']);
            $table->index(['tenant_id', 'asset_availability_schedule_occurrences_id']);
            $table->index('partition_key');
            $table->unique(['booking_id', 'sequence_order']); // Prevent slot order duplicates
        });

        // Recreate constraints
        DB::statement('ALTER TABLE asset_bookings ADD CONSTRAINT valid_booking_datetime CHECK (end_datetime > start_datetime)');
        DB::statement('ALTER TABLE asset_bookings ADD CONSTRAINT valid_duration CHECK (duration_hours > 0)');
        DB::statement('ALTER TABLE asset_bookings ADD CONSTRAINT valid_attendees CHECK (attendees_count > 0)');
        
        DB::statement('ALTER TABLE asset_booking_time_slots ADD CONSTRAINT valid_slot_datetime CHECK (end_datetime > start_datetime)');
        DB::statement('ALTER TABLE asset_booking_time_slots ADD CONSTRAINT valid_slot_duration CHECK (duration_hours > 0)');
    }
};
