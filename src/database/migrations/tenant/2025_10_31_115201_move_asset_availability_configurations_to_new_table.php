<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop columns from asset_availability_schedules
        Schema::table('asset_availability_schedules', function (Blueprint $table) {
            $table->dropForeign(['visibility_id']);
            $table->dropForeign(['approval_type_id']);
            $table->dropForeign(['rate_currency_type_id']);
            $table->dropForeign(['rate_period_type_id']);
            $table->dropForeign(['cancellation_notice_period_type']);
            $table->dropForeign(['cancellation_fee_type']);
            $table->dropForeign(['asset_booking_cancellation_refund_policy_type']);
            $table->dropColumn([
                'visibility_id',
                'approval_type_id',
                'rate',
                'rate_currency_type_id',
                'rate_period_type_id',
                'deposit_required',
                'deposit_amount',
                'attachment',
                'cancellation_enabled',
                'cancellation_notice_period',
                'cancellation_notice_period_type',
                'cancellation_fee_enabled',
                'cancellation_fee_type',
                'cancellation_fee_amount',
                'cancellation_fee_percentage',
                'asset_booking_cancellation_refund_policy_type',
            ]);
        });

        // Create asset_availability_configurations table
        Schema::create('asset_availability_configurations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_items_id');
            $table->unsignedBigInteger('visibility_id')->nullable();
            $table->unsignedBigInteger('approval_type_id')->nullable();
            $table->decimal('rate', 15, 2)->nullable();
            $table->unsignedBigInteger('rate_currency_type_id')->nullable();
            $table->unsignedBigInteger('rate_period_type_id')->nullable();
            $table->boolean('deposit_required')->default(false);
            $table->decimal('deposit_amount', 15, 2)->nullable();
            $table->jsonb('attachment')->nullable();
            $table->boolean('cancellation_enabled')->default(false);
            $table->unsignedInteger('cancellation_notice_period')->nullable();
            $table->unsignedBigInteger('cancellation_notice_period_type')->nullable();
            $table->boolean('cancellation_fee_enabled')->default(false);
            $table->unsignedBigInteger('cancellation_fee_type')->nullable();
            $table->decimal('cancellation_fee_amount', 15, 2)->nullable();
            $table->decimal('cancellation_fee_percentage', 5, 2)->default(0);
            $table->unsignedBigInteger('asset_booking_cancellation_refund_policy_type')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestampsTz(0);

            $table->foreign('asset_items_id')->references('id')->on('asset_items')->onDelete('restrict');
            $table->foreign('visibility_id')->references('id')->on('asset_availability_visibility_types')->onDelete('restrict');
            $table->foreign('approval_type_id')->references('id')->on('asset_booking_approval_types')->onDelete('restrict');
            $table->foreign('rate_currency_type_id')->references('id')->on('currencies')->onDelete('restrict');
            $table->foreign('rate_period_type_id')->references('id')->on('time_period_entries')->onDelete('restrict');
            $table->foreign('cancellation_notice_period_type')->references('id')->on('time_period_entries')->onDelete('restrict');
            $table->foreign('cancellation_fee_type')->references('id')->on('asset_booking_cancelling_fee_types')->onDelete('restrict');
            $table->foreign('asset_booking_cancellation_refund_policy_type')->references('id')->on('asset_booking_cancellation_refund_policy_type')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        // Drop asset_availability_configurations table
        Schema::dropIfExists('asset_availability_configurations');

        // Add columns back to asset_availability_schedules
        Schema::table('asset_availability_schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('visibility_id')->nullable();
            $table->unsignedBigInteger('approval_type_id')->nullable();
            $table->decimal('rate', 15, 2)->nullable();
            $table->unsignedBigInteger('rate_currency_type_id')->nullable();
            $table->unsignedBigInteger('rate_period_type_id')->nullable();
            $table->boolean('deposit_required')->default(false);
            $table->decimal('deposit_amount', 15, 2)->nullable();
            $table->jsonb('attachment')->nullable();
            $table->boolean('cancellation_enabled')->default(false);
            $table->unsignedInteger('cancellation_notice_period')->nullable();
            $table->unsignedBigInteger('cancellation_notice_period_type')->nullable();
            $table->boolean('cancellation_fee_enabled')->default(false);
            $table->unsignedBigInteger('cancellation_fee_type')->nullable();
            $table->decimal('cancellation_fee_amount', 15, 2)->nullable();
            $table->decimal('cancellation_fee_percentage', 5, 2)->default(0);
            $table->unsignedBigInteger('asset_booking_cancellation_refund_policy_type')->nullable();

            $table->foreign('visibility_id')->references('id')->on('asset_availability_visibility_types')->onDelete('restrict');
            $table->foreign('approval_type_id')->references('id')->on('asset_booking_approval_types')->onDelete('restrict');
            $table->foreign('rate_currency_type_id')->references('id')->on('currencies')->onDelete('restrict');
            $table->foreign('rate_period_type_id')->references('id')->on('time_period_entries')->onDelete('restrict');
            $table->foreign('cancellation_notice_period_type')->references('id')->on('time_period_entries')->onDelete('restrict');
            $table->foreign('cancellation_fee_type')->references('id')->on('asset_booking_cancelling_fee_types')->onDelete('restrict');
            $table->foreign('asset_booking_cancellation_refund_policy_type')->references('id')->on('asset_booking_cancellation_refund_policy_type')->onDelete('restrict');
        });
    }
};