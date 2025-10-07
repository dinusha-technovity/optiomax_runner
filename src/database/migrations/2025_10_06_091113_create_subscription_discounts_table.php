<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_discounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('discount_id');
            $table->decimal('discount_amount', 10, 2);
            $table->decimal('original_amount', 10, 2);
            $table->decimal('final_amount', 10, 2);
            $table->string('stripe_discount_id')->nullable();
            $table->timestamp('applied_at')->default(now());
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('subscription_id')->references('id')->on('tenant_subscriptions')->onDelete('cascade');
            $table->foreign('discount_id')->references('id')->on('package_discounts')->onDelete('cascade');
            
            $table->index(['subscription_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_discounts');
    }
};