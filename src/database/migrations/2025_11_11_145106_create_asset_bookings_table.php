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
        Schema::create('asset_bookings', function (Blueprint $table) {
            // Primary key with optimized auto-increment
            $table->id();

            // Business identification
            $table->string('booking_number', 50)->unique()->index();
            $table->string('booking_reference', 100)->nullable()->index();
            
            // Booking metadata
            $table->boolean('is_self_booking')->default(true)->index();
            $table->unsignedBigInteger('booking_type_id')->nullable();
            $table->unsignedBigInteger('parent_booking_id')->nullable(); // For recurring/group bookings
            $table->boolean('is_optiomesh_booking')->default(true)->index();
            
            // Customer information
            $table->unsignedBigInteger('optiomesh_customer_id')->nullable();
            $table->unsignedBigInteger('booked_by_user_id')->nullable();
            $table->jsonb('optiomesh_customer_details')->nullable();
            
            // Booking details
            $table->unsignedInteger('attendees_count')->default(1);
            $table->enum('booking_status', ['PENDING','APPROVED','CANCELLED','SUBMITTED','PROCESSING', 'EXPIRED', 'CONFIRMED', 'IN_PROGRESS', 'COMPLETED', 'REJECTED'])->default('APPROVED');
            
            $table->text('note')->nullable();
            $table->text('special_requirements')->nullable();
            
            // Multi-tenancy and partitioning
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('partition_key', 50)->nullable()->index(); // Format: YYYY-MM or tenant_id
            
            // Audit and lifecycle
            $table->boolean('isactive')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->ipAddress('created_ip')->nullable();
            $table->ipAddress('updated_ip')->nullable();
            $table->timestampTz('deleted_at')->nullable()->index(); // Soft delete
            $table->timestampsTz(0);
            
            // Performance optimization indexes
            $table->index(['tenant_id', 'booking_status'], 'idx_tenant_status_datetime');
            $table->index(['booked_by_user_id', 'booking_status'], 'idx_user_bookings');
            $table->index(['created_at', 'tenant_id'], 'idx_created_tenant');
            $table->index(['partition_key'], 'idx_bookings_partition_datetime');

            
            // Foreign key constraints
            $table->foreign('booking_type_id')->references('id')->on('asset_booking_type')->onDelete('restrict');
            $table->foreign('parent_booking_id')->references('id')->on('asset_bookings')->onDelete('cascade');
            $table->foreign('booked_by_user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
        
        // Add check constraints for data integrity
        DB::statement('ALTER TABLE asset_bookings ADD CONSTRAINT chk_valid_attendees CHECK (attendees_count > 0)');
        
        // Add table comment for documentation
        DB::statement('COMMENT ON TABLE asset_bookings IS \'Main booking records for asset reservations with multi-tenant support\'');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_bookings');
    }
};