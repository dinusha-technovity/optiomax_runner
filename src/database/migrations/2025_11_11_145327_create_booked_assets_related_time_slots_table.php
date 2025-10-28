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
        Schema::create('asset_booking_time_slots', function (Blueprint $table) {
            // Primary key
            $table->id();
            
            // Core relationships
            $table->unsignedBigInteger('asset_booking_item_id')->index(); // Reference to booking items
            $table->unsignedBigInteger('asset_availability_schedule_occurrences_id')->nullable()->index();
            
            // Timing information
            $table->timestampTz('start_datetime')->index();
            $table->timestampTz('end_datetime')->index();
            $table->decimal('duration_hours', 8, 2);
            $table->string('timezone', 50)->default('UTC');
            
            // Slot management
            $table->unsignedInteger('sequence_order')->index();
            $table->unsignedInteger('total_slots_in_booking')->default(1);
            $table->boolean('is_break_between_slots')->default(false); // For maintenance breaks
            $table->unsignedInteger('break_duration_minutes')->nullable();
            
            // Slot Status
            $table->enum('slot_status', [
                'PENDING', 'CONFIRMED', 'IN_PROGRESS', 'COMPLETED', 
                'CANCELLED', 'NO_SHOW', 'RESCHEDULED'
            ])->default('PENDING')->index();
            
            // Pricing per slot (for variable pricing)
            $table->decimal('slot_rate', 15, 4)->nullable();
            $table->decimal('slot_cost', 15, 2)->nullable();
            $table->unsignedBigInteger('slot_currency_id')->nullable();
            
            // Check-in/out specific to this slot
            $table->timestampTz('slot_checkin_at')->nullable();
            $table->timestampTz('slot_checkout_at')->nullable();
            $table->unsignedBigInteger('slot_checkin_by')->nullable();
            $table->unsignedBigInteger('slot_checkout_by')->nullable();
            $table->text('slot_notes')->nullable();
            
            // Asset condition for this specific slot
            $table->enum('asset_condition_start', ['EXCELLENT', 'GOOD', 'FAIR', 'POOR', 'DAMAGED'])->nullable();
            $table->enum('asset_condition_end', ['EXCELLENT', 'GOOD', 'FAIR', 'POOR', 'DAMAGED'])->nullable();
            $table->jsonb('condition_photos')->nullable(); // Metadata for photos
            $table->text('condition_notes')->nullable();
            
            // Attendance and usage tracking
            $table->unsignedInteger('expected_attendees')->nullable();
            $table->unsignedInteger('actual_attendees')->nullable();
            $table->jsonb('attendee_list')->nullable(); // For tracking who attended
            $table->decimal('actual_usage_hours', 8, 2)->nullable();
            $table->enum('usage_efficiency', ['UNDER_UTILIZED', 'OPTIMAL', 'OVER_UTILIZED'])->nullable();
            
            // Equipment and setup requirements
            $table->jsonb('required_equipment')->nullable(); // List of additional equipment needed
            $table->jsonb('setup_requirements')->nullable(); // Special setup needs
            $table->timestampTz('setup_start_time')->nullable();
            $table->timestampTz('setup_complete_time')->nullable();
            $table->timestampTz('breakdown_start_time')->nullable();
            $table->timestampTz('breakdown_complete_time')->nullable();
            
            // Communication and notifications
            $table->boolean('reminder_sent')->default(false);
            $table->timestampTz('reminder_sent_at')->nullable();
            $table->jsonb('notification_settings')->nullable(); // Customizable notifications
            
            // Document verification (for compliance)
            $table->boolean('all_documents_verified')->default(false)->index();
            $table->unsignedBigInteger('documents_verified_by')->nullable();
            $table->timestampTz('documents_verified_at')->nullable();
            
            // Quality and feedback
            $table->tinyInteger('quality_rating')->nullable(); // 1-5 stars
            $table->text('feedback')->nullable();
            $table->timestampTz('feedback_submitted_at')->nullable();
            
            // Multi-tenancy and partitioning
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('partition_key', 50)->nullable()->index();
            
            // Flexible attributes for different asset types
            $table->jsonb('custom_attributes')->nullable();
            $table->jsonb('compliance_checklist')->nullable(); // For regulated assets
            
            // Audit trail
            $table->boolean('isactive')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->ipAddress('created_ip')->nullable();
            $table->ipAddress('updated_ip')->nullable();
            $table->timestampTz('deleted_at')->nullable()->index();
            $table->timestampsTz(0);
            
            // Optimized indexes for performance
            $table->index(['asset_booking_item_id', 'sequence_order'], 'idx_item_sequence');
            $table->index(['asset_availability_schedule_occurrences_id', 'start_datetime', 'end_datetime'], 'idx_schedule_datetime');
            $table->index(['tenant_id', 'start_datetime'], 'idx_slots_tenant_datetime');
            $table->index(['slot_status', 'start_datetime'], 'idx_slots_status_datetime');
            $table->index(['partition_key', 'start_datetime'], 'idx_slots_partition_datetime');
            $table->index(['all_documents_verified', 'start_datetime'], 'idx_docs_datetime');
            $table->index(['created_at', 'slot_status'], 'idx_slots_created_status');
            
            // Unique constraints
            $table->unique(['asset_booking_item_id', 'sequence_order'], 'unq_item_slot_sequence');
            
            // Foreign key constraints
            $table->foreign('asset_booking_item_id', 'fk_time_slots_booking_item')
                  ->references('id')->on('asset_booking_items')->onDelete('cascade');
            $table->foreign('asset_availability_schedule_occurrences_id', 'fk_time_slots_schedule')
                  ->references('id')->on('asset_availability_schedule_occurrences')->onDelete('set null');
            $table->foreign('slot_currency_id', 'fk_time_slots_currency')
                  ->references('id')->on('currencies')->onDelete('restrict');
            $table->foreign('documents_verified_by', 'fk_time_slots_doc_verifier')
                  ->references('id')->on('users')->onDelete('set null');
            $table->foreign('slot_checkin_by', 'fk_time_slots_checkin')
                  ->references('id')->on('users')->onDelete('set null');
            $table->foreign('slot_checkout_by', 'fk_time_slots_checkout')
                  ->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by', 'fk_time_slots_created_by')
                  ->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'fk_time_slots_updated_by')
                  ->references('id')->on('users')->onDelete('set null');
        });
        
        // Add check constraints for data integrity
        DB::statement('ALTER TABLE asset_booking_time_slots ADD CONSTRAINT chk_valid_slot_datetime CHECK (end_datetime > start_datetime)');
        DB::statement('ALTER TABLE asset_booking_time_slots ADD CONSTRAINT chk_valid_slot_duration CHECK (duration_hours > 0)');
        DB::statement('ALTER TABLE asset_booking_time_slots ADD CONSTRAINT chk_valid_sequence_order CHECK (sequence_order > 0)');
        DB::statement('ALTER TABLE asset_booking_time_slots ADD CONSTRAINT chk_valid_quality_rating CHECK (quality_rating >= 1 AND quality_rating <= 5 OR quality_rating IS NULL)');
        DB::statement('ALTER TABLE asset_booking_time_slots ADD CONSTRAINT chk_valid_attendees CHECK (actual_attendees >= 0 OR actual_attendees IS NULL)');
        DB::statement('ALTER TABLE asset_booking_time_slots ADD CONSTRAINT chk_valid_usage_hours CHECK (actual_usage_hours >= 0 OR actual_usage_hours IS NULL)');
        
        // Create partial indexes for better performance (removed CONCURRENTLY for migration compatibility)
        DB::statement('CREATE INDEX idx_active_time_slots ON asset_booking_time_slots (start_datetime, end_datetime) WHERE isactive = true AND deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_unverified_documents ON asset_booking_time_slots (created_at) WHERE all_documents_verified = false');
        DB::statement('CREATE INDEX idx_slot_datetime_range ON asset_booking_time_slots (asset_booking_item_id, start_datetime, end_datetime) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_feedback_pending ON asset_booking_time_slots (end_datetime) WHERE slot_status = \'COMPLETED\' AND feedback IS NULL');
        
        // Add table comment for documentation
        DB::statement('COMMENT ON TABLE asset_booking_time_slots IS \'Individual time slots within booking items with detailed tracking, document management, and quality control\'');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_booking_time_slots');
    }
};