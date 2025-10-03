<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_availability_schedules', function (Blueprint $table) {
            $table->dropForeign(['term_type_id']);
            $table->dropColumn('term_type_id');
            $table->boolean('cancellation_enabled')->default(false);
            $table->unsignedInteger('cancellation_notice_period')->nullable();
            $table->unsignedBigInteger('cancellation_notice_period_type')->nullable();
            $table->boolean('cancellation_fee_enabled')->default(false);
            $table->unsignedBigInteger('cancellation_fee_type')->nullable();
            $table->decimal('cancellation_fee_amount', 15, 2)->nullable();
            $table->decimal('cancellation_fee_percentage', 5, 2)->default(0);
            $table->unsignedBigInteger('asset_booking_cancellation_refund_policy_type')->nullable();

            $table->foreign('cancellation_notice_period_type')->references('id')->on('time_period_entries')->onDelete('restrict');
            $table->foreign('cancellation_fee_type')->references('id')->on('asset_booking_cancelling_fee_types')->onDelete('restrict');
            $table->foreign('asset_booking_cancellation_refund_policy_type')->references('id')->on('asset_booking_cancellation_refund_policy_type')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('asset_availability_schedules', function (Blueprint $table) {
            // Drop new foreign keys and columns
            $table->dropForeign(['cancellation_notice_period_type']);
            $table->dropForeign(['cancellation_fee_type']);
            $table->dropForeign(['asset_booking_cancellation_refund_policy_type']);

            $table->dropColumn([
                'cancellation_enabled',
                'cancellation_notice_period',  // Added this missing column
                'cancellation_notice_period_type',
                'cancellation_fee_enabled',
                'cancellation_fee_type',
                'cancellation_fee_amount',
                'cancellation_fee_percentage',
                'asset_booking_cancellation_refund_policy_type',
            ]);

            // Restore dropped column
            $table->unsignedBigInteger('term_type_id')->nullable();
            $table->foreign('term_type_id')->references('id')->on('asset_availability_term_types')->onDelete('restrict');
        });
    }
};