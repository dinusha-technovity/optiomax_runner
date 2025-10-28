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
        Schema::create('asset_booking_items', function (Blueprint $table) {
            // Primary key
            $table->id();
            
            // Core relationships
            $table->unsignedBigInteger('asset_booking_id')->index();
            $table->unsignedBigInteger('asset_id')->index();
            $table->unsignedBigInteger('organization_id')->index();
            
            // Location and logistics
            $table->decimal('location_latitude', 10, 8)->nullable();
            $table->decimal('location_longitude', 11, 8)->nullable();
            // Removed PostGIS geography type - use lat/long directly
            $table->string('location_description')->nullable();
            $table->text('logistics_note')->nullable();
            
            // Timing details
            $table->timestampTz('start_datetime')->index();
            $table->timestampTz('end_datetime')->index();
            $table->decimal('duration_hours', 8, 2)->nullable();
            $table->string('timezone', 50)->default('UTC'); // Important for worldwide app

            // Priority and urgency for enterprise needs
            $table->tinyInteger('priority_level')->default(3)->index(); // 1=Critical, 5=Low
            $table->boolean('is_recurring')->default(false)->index();
            $table->jsonb('recurring_pattern')->nullable(); // Store recurrence rules
            
            // Pricing and financial
            $table->decimal('unit_rate', 15, 4)->nullable();
            $table->unsignedBigInteger('rate_currency_id')->nullable();
            $table->decimal('subtotal', 15, 2)->nullable();
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->nullable();
            $table->unsignedBigInteger('total_cost_currency_id')->nullable();
            $table->unsignedBigInteger('rate_period_type_id')->nullable();
            
            // Deposit management
            $table->boolean('deposit_required')->default(false)->index();
            $table->decimal('deposit_amount', 15, 2)->nullable();
            $table->decimal('deposit_percentage', 5, 2)->nullable();
            $table->unsignedBigInteger('deposit_currency_id')->nullable();
            $table->boolean('deposit_paid')->default(false)->index();
            $table->timestampTz('deposit_paid_at')->nullable();
            $table->string('deposit_payment_reference')->nullable();
            
            // Approval workflow
            $table->unsignedBigInteger('approval_type_id')->nullable();
            $table->enum('booking_status', [
                'DRAFT', 'PENDING', 'APPROVED', 'CONFIRMED',
                'IN_PROGRESS', 'COMPLETED', 'CANCELLED', 
                'REJECTED', 'ON_HOLD'
            ])->default('PENDING')->index();
            
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('workflow_request_queues_id')->nullable();
            
            // Cancellation policies
            $table->boolean('cancellation_enabled')->default(true);
            $table->unsignedInteger('cancellation_notice_hours')->nullable(); // More precise than periods
            $table->boolean('cancellation_fee_enabled')->default(false);
            $table->unsignedInteger('cancellation_fee_type')->nullable();
            $table->decimal('cancellation_fee_amount', 15, 2)->nullable();
            $table->decimal('cancellation_fee_percentage', 5, 2)->default(0);
            $table->timestampTz('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->boolean('cancellation_fee_applied')->default(false);
            
            // Multi-slot booking support
            $table->boolean('is_multi_slot')->default(false)->index();
            $table->unsignedInteger('slot_sequence')->default(1);
            $table->unsignedInteger('total_slots')->default(1);
            
            // Check-in/out tracking
            $table->timestampTz('scheduled_checkin_at')->nullable();
            $table->timestampTz('scheduled_checkout_at')->nullable();
            $table->timestampTz('actual_checkin_at')->nullable();
            $table->timestampTz('actual_checkout_at')->nullable();
            $table->unsignedBigInteger('checked_in_by')->nullable();
            $table->unsignedBigInteger('checked_out_by')->nullable();
            $table->text('checkin_notes')->nullable();
            $table->text('checkout_notes')->nullable();
            
            // Asset condition tracking
            $table->enum('asset_condition_before', ['EXCELLENT', 'GOOD', 'FAIR', 'POOR', 'DAMAGED'])->nullable();
            $table->enum('asset_condition_after', ['EXCELLENT', 'GOOD', 'FAIR', 'POOR', 'DAMAGED'])->nullable();
            $table->text('condition_notes')->nullable();
            
            // Communication and reminders
            $table->boolean('reminder_enabled')->default(true);
            $table->jsonb('reminder_schedule')->nullable(); // Flexible reminder timing
            $table->timestampTz('last_reminder_sent_at')->nullable();
            $table->unsignedInteger('reminder_count')->default(0);
            
            // Purpose and usage
            $table->unsignedBigInteger('purpose_type_id')->nullable();
            $table->string('custom_purpose_name')->nullable();
            $table->text('custom_purpose_description')->nullable();
            $table->jsonb('usage_requirements')->nullable(); // Flexible requirements storage
            
            // Multi-tenancy and partitioning
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('partition_key', 50)->nullable()->index();
            
            // Workflow integration
            $table->unsignedBigInteger('booking_created_by_user_id')->nullable();

            $table->jsonb('attachments')->nullable(); // Flexible requirements storage
            
            // Audit trail
            $table->boolean('isactive')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->ipAddress('created_ip')->nullable();
            $table->ipAddress('updated_ip')->nullable();
            $table->timestampTz('deleted_at')->nullable()->index();
            $table->timestampsTz(0);
            
            // Performance optimization indexes
            $table->index(['asset_booking_id', 'slot_sequence'], 'idx_booking_slot_seq');
            $table->index(['asset_id', 'start_datetime', 'end_datetime'], 'idx_asset_datetime_range');
            $table->index(['organization_id', 'booking_status'], 'idx_org_status');
            $table->index(['tenant_id', 'asset_id', 'start_datetime'], 'idx_tenant_asset_datetime');
            $table->index(['booking_status', 'start_datetime'], 'idx_status_datetime');
            $table->index(['deposit_required', 'deposit_paid'], 'idx_deposit_status');
            $table->index(['created_at', 'booking_status'], 'idx_created_status');
            $table->index(['partition_key', 'start_datetime'], 'idx_items_partition_datetime');
            $table->index(['is_recurring'], 'idx_recurring_datetime');
            
            // Unique constraints
            $table->unique(['asset_booking_id', 'slot_sequence'], 'unq_booking_slot');
            
            // Foreign key constraints with proper naming
            $table->foreign('asset_booking_id', 'fk_booking_items_booking')
                  ->references('id')->on('asset_bookings')->onDelete('cascade');
            $table->foreign('asset_id', 'fk_booking_items_asset')
                  ->references('id')->on('asset_items')->onDelete('restrict');
            $table->foreign('organization_id', 'fk_booking_items_org')
                  ->references('id')->on('organization')->onDelete('restrict');
            $table->foreign('rate_currency_id', 'fk_booking_items_rate_currency')
                  ->references('id')->on('currencies')->onDelete('restrict');
            $table->foreign('total_cost_currency_id', 'fk_booking_items_total_currency')
                  ->references('id')->on('currencies')->onDelete('restrict');
            $table->foreign('deposit_currency_id', 'fk_booking_items_deposit_currency')
                  ->references('id')->on('currencies')->onDelete('restrict');
            $table->foreign('rate_period_type_id', 'fk_booking_items_rate_period')
                  ->references('id')->on('time_period_entries')->onDelete('restrict');
            $table->foreign('approval_type_id', 'fk_booking_items_approval_type')
                  ->references('id')->on('asset_booking_approval_types')->onDelete('restrict');
            $table->foreign('approved_by', 'fk_booking_items_approved_by')
                  ->references('id')->on('users')->onDelete('set null');
            $table->foreign('rejected_by', 'fk_booking_items_rejected_by')
                  ->references('id')->on('users')->onDelete('set null');
            $table->foreign('workflow_request_queues_id')->references('id')->on('workflow_request_queues')->onDelete('restrict');
            $table->foreign('cancellation_fee_type')->references('id')->on('asset_booking_cancelling_fee_types')->onDelete('restrict');
            $table->foreign('cancelled_by', 'fk_booking_items_cancelled_by')
                  ->references('id')->on('users')->onDelete('set null');
            $table->foreign('checked_in_by', 'fk_booking_items_checkin_by')
                  ->references('id')->on('users')->onDelete('set null');
            $table->foreign('checked_out_by', 'fk_booking_items_checkout_by')
                  ->references('id')->on('users')->onDelete('set null');
            $table->foreign('purpose_type_id', 'fk_booking_items_purpose')
                  ->references('id')->on('asset_booking_purpose_or_use_case_type')->onDelete('set null');
            $table->foreign('booking_created_by_user_id', 'fk_booking_items_creator')
                  ->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by', 'fk_booking_items_created_by')
                  ->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'fk_booking_items_updated_by')
                  ->references('id')->on('users')->onDelete('set null');
        });
         
        // Add check constraints
        DB::statement('ALTER TABLE asset_booking_items ADD CONSTRAINT chk_valid_item_datetime CHECK (end_datetime > start_datetime)');
        DB::statement('ALTER TABLE asset_booking_items ADD CONSTRAINT chk_valid_item_duration CHECK (duration_hours > 0 OR duration_hours IS NULL)');
        DB::statement('ALTER TABLE asset_booking_items ADD CONSTRAINT chk_valid_deposit_percentage CHECK (deposit_percentage >= 0 AND deposit_percentage <= 100)');
        DB::statement('ALTER TABLE asset_booking_items ADD CONSTRAINT chk_valid_cancellation_fee CHECK (cancellation_fee_percentage >= 0 AND cancellation_fee_percentage <= 100)');
        DB::statement('ALTER TABLE asset_booking_items ADD CONSTRAINT chk_valid_coordinates CHECK ((location_latitude IS NULL AND location_longitude IS NULL) OR (location_latitude IS NOT NULL AND location_longitude IS NOT NULL))');
        
        // Create partial indexes for better performance (removed CONCURRENTLY for migration compatibility)
        DB::statement('CREATE INDEX idx_active_booking_items ON asset_booking_items (asset_id, start_datetime, end_datetime) WHERE isactive = true AND deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_pending_deposits ON asset_booking_items (created_at) WHERE deposit_required = true AND deposit_paid = false');
        DB::statement('CREATE INDEX idx_pending_checkins ON asset_booking_items (scheduled_checkin_at) WHERE actual_checkin_at IS NULL');
        
        // Add table comment
        DB::statement('COMMENT ON TABLE asset_booking_items IS \'Individual asset items within bookings with detailed tracking and financial management\'');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_booking_items');
    }
};