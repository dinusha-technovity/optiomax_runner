<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('payment_status', ['active', 'past_due', 'failed', 'suspended'])->default('active')->after('is_tenant_blocked');
            $table->timestamp('payment_failed_at')->nullable();
            $table->timestamp('last_payment_reminder_sent')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->text('blocking_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'payment_status',
                'payment_failed_at',
                'last_payment_reminder_sent',
                'blocked_at',
                'blocking_reason'
            ]);
        });
    }
};