<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_addons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('addon_id');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->string('stripe_subscription_item_id')->nullable();
            $table->enum('status', ['active', 'canceled', 'paused'])->default('active');
            $table->timestamp('activated_at')->default(now());
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->foreign('subscription_id')->references('id')->on('tenant_subscriptions')->onDelete('cascade');
            $table->foreign('addon_id')->references('id')->on('package_addons')->onDelete('cascade');
            
            $table->index(['subscription_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_addons');
    }
};