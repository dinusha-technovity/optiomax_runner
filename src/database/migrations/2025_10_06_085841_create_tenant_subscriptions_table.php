<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('package_id');
            $table->string('stripe_customer_id');
            $table->string('stripe_subscription_id')->nullable();
            $table->string('stripe_payment_method_id');
            $table->enum('billing_cycle', ['monthly', 'yearly']);
            $table->enum('status', ['active', 'canceled', 'past_due', 'unpaid', 'trialing', 'incomplete'])->default('active');
            $table->decimal('amount', 10, 2);
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('trial_end')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('package_id')->references('id')->on('tenant_packages')->onDelete('cascade');
            
            $table->index(['tenant_id', 'status']);
            $table->index('stripe_customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_subscriptions');
    }
};