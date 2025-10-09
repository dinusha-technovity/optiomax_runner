<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_invoice_id')->nullable();
            $table->string('stripe_charge_id')->nullable();
            $table->enum('type', [
                'subscription', 
                'subscription_with_addons', 
                'setup_fee', 
                'addon', 
                'one_time', 
                'refund',
                'discount_adjustment'
            ]);
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('usd');
            $table->enum('status', [
                'pending', 
                'processing', 
                'succeeded', 
                'failed', 
                'canceled', 
                'refunded',
                'partially_refunded'
            ])->default('pending');
            $table->text('description')->nullable();
            $table->json('stripe_response')->nullable();
            $table->json('metadata')->nullable();
            $table->decimal('fee_amount', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2)->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('subscription_id')->references('id')->on('tenant_subscriptions')->onDelete('set null');
            
            $table->index(['tenant_id', 'type', 'status']);
            $table->index(['stripe_payment_intent_id']);
            $table->index(['processed_at', 'status']);
            $table->index(['type', 'created_at']);
        });
        
        // Convert JSON columns to JSONB for better performance
        DB::statement('ALTER TABLE payment_transactions ALTER COLUMN stripe_response TYPE jsonb USING stripe_response::jsonb');
        DB::statement('ALTER TABLE payment_transactions ALTER COLUMN metadata TYPE jsonb USING metadata::jsonb');
        
        // Add GIN indexes for JSONB columns
        DB::statement('CREATE INDEX payment_transactions_stripe_response_gin_index ON payment_transactions USING GIN (stripe_response)');
        DB::statement('CREATE INDEX payment_transactions_metadata_gin_index ON payment_transactions USING GIN (metadata)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    } 
};