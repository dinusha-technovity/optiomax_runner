<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_bookings', function (Blueprint $table) {
            // Drop old FKs and columns
            $table->dropForeign(['booked_by']); // or use the FK name if needed
            $table->dropColumn('booked_by');
            $table->dropColumn('purpose'); 

            // Add new columns and FKs
            $table->unsignedBigInteger('asset_booking_purpose_or_use_case_type_id')->nullable();
            $table->text('custom_purpose_name')->nullable();
            $table->text('custom_purpose_description')->nullable();
            $table->unsignedBigInteger('booked_by_user_id')->nullable();
            $table->unsignedBigInteger('booked_by_customer_id')->nullable();
            $table->unsignedBigInteger('booking_created_by_user_id')->nullable();

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
        });
    }

    public function down(): void
    {
        Schema::table('asset_bookings', function (Blueprint $table) {
            // Drop new FKs and columns
            $table->dropForeign(['asset_booking_purpose_or_use_case_type_id']);
            $table->dropForeign(['booked_by_user_id']);
            $table->dropForeign(['booked_by_customer_id']);
            $table->dropForeign(['booking_created_by_user_id']);

            $table->dropColumn('asset_booking_purpose_or_use_case_type_id');
            $table->dropColumn('custom_purpose_name');
            $table->dropColumn('custom_purpose_description');
            $table->dropColumn('booked_by_user_id');
            $table->dropColumn('booked_by_customer_id');
            $table->dropColumn('booking_created_by_user_id');

            // Restore old columns
            $table->unsignedBigInteger('booked_by')->nullable();
            $table->text('purpose')->nullable();

            // Restore old FK
            $table->foreign('booked_by')->references('id')->on('users')->onDelete('restrict');
        });
    }
};