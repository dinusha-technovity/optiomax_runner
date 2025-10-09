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
            
            // Applied discount details
            $table->string('discount_code');
            $table->enum('discount_type', ['percentage', 'fixed_amount', 'trial_extension', 'setup_fee_waiver']);
            $table->decimal('discount_value', 10, 2);
            $table->decimal('applied_amount', 10, 2); // Actual discount amount applied
            
            // Application scope
            $table->boolean('applied_to_base_price')->default(true);
            $table->boolean('applied_to_addons')->default(false);
            $table->boolean('applied_to_setup_fee')->default(false);
            $table->json('affected_items')->nullable(); // Which items were discounted
            
            // Validity and duration
            $table->timestamp('applied_at')->default(now());
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_recurring')->default(false); // Whether discount applies to future payments
            $table->integer('remaining_applications')->nullable(); // For limited-time discounts
            
            // Status and tracking
            $table->enum('status', ['active', 'expired', 'canceled', 'used_up'])->default('active');
            $table->decimal('total_savings', 10, 2)->default(0); // Total amount saved
            $table->integer('times_applied')->default(0); // How many times this discount was used
            
            // Stripe integration
            $table->string('stripe_coupon_id')->nullable();
            $table->string('stripe_discount_id')->nullable();
            $table->json('stripe_metadata')->nullable();
            
            // Compliance and audit
            $table->json('application_context')->nullable(); // Context when discount was applied
            $table->string('legal_version')->nullable(); // Terms version when applied
            $table->unsignedBigInteger('applied_by')->nullable(); // Who applied the discount
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('subscription_id')->references('id')->on('tenant_subscriptions')->onDelete('cascade');
            $table->foreign('discount_id')->references('id')->on('package_discounts')->onDelete('cascade');
            $table->foreign('applied_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index(['subscription_id', 'status']);
            $table->index(['discount_code', 'applied_at']);
            $table->index(['valid_from', 'valid_until']);
            $table->index(['is_recurring', 'status']);
        });
        
        // Convert JSON columns to JSONB
        DB::statement('ALTER TABLE subscription_discounts ALTER COLUMN affected_items TYPE jsonb USING affected_items::jsonb');
        DB::statement('ALTER TABLE subscription_discounts ALTER COLUMN application_context TYPE jsonb USING application_context::jsonb');
        DB::statement('ALTER TABLE subscription_discounts ALTER COLUMN stripe_metadata TYPE jsonb USING stripe_metadata::jsonb');
        
        // Add GIN indexes for JSONB columns
        DB::statement('CREATE INDEX subscription_discounts_affected_items_gin_index ON subscription_discounts USING GIN (affected_items)');
        DB::statement('CREATE INDEX subscription_discounts_application_context_gin_index ON subscription_discounts USING GIN (application_context)');
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_discounts');
    }
};
