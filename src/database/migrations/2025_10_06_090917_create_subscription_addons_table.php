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
            
            // Quantity and pricing
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price_monthly', 10, 2);
            $table->decimal('unit_price_yearly', 10, 2);
            $table->decimal('total_price_monthly', 10, 2);
            $table->decimal('total_price_yearly', 10, 2);
            
            // Applied values from the addon
            $table->json('applied_boost_values')->nullable(); // What values were actually applied
            $table->json('original_limits')->nullable(); // Original limits before addon
            $table->json('new_limits')->nullable(); // New limits after addon
            
            // Billing and status
            $table->enum('billing_cycle', ['monthly', 'yearly']);
            $table->enum('status', ['active', 'canceled', 'paused', 'pending_approval'])->default('active');
            $table->boolean('is_prorated')->default(true);
            $table->decimal('prorated_amount', 10, 2)->default(0);
            
            // Metering (for metered addons)
            $table->boolean('is_metered')->default(false);
            $table->decimal('current_usage', 10, 4)->default(0);
            $table->decimal('metered_charges', 10, 2)->default(0);
            $table->timestamp('usage_reset_date')->nullable();
            
            // Approval workflow
            $table->boolean('requires_approval')->default(false);
            $table->enum('approval_status', ['pending', 'approved', 'rejected', 'auto_approved'])->default('auto_approved');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Stripe integration
            $table->string('stripe_subscription_item_id')->nullable();
            $table->string('stripe_meter_event_id')->nullable(); // For metered billing
            $table->json('stripe_metadata')->nullable();
            
            // Lifecycle timestamps
            $table->timestamp('activated_at')->default(now());
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('scheduled_change_date')->nullable(); // For scheduled changes
            
            // Audit and compliance
            $table->json('compliance_checks')->nullable(); // Record of compliance validations
            $table->string('legal_version')->nullable(); // Version of terms when added
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('subscription_id')->references('id')->on('tenant_subscriptions')->onDelete('cascade');
            $table->foreign('addon_id')->references('id')->on('package_addons')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index(['subscription_id', 'status']);
            $table->index(['addon_id', 'status']);
            $table->index(['approval_status', 'requires_approval']);
            $table->index(['billing_cycle', 'is_metered']);
            $table->index(['activated_at', 'canceled_at']);
            
            // Unique constraint to prevent duplicate active addons (for non-stackable addons)
            $table->unique(['subscription_id', 'addon_id', 'status'], 'unique_active_subscription_addon');
        });
        
        // Convert JSON columns to JSONB and add GIN indexes
        DB::statement('ALTER TABLE subscription_addons ALTER COLUMN applied_boost_values TYPE jsonb USING applied_boost_values::jsonb');
        DB::statement('ALTER TABLE subscription_addons ALTER COLUMN original_limits TYPE jsonb USING original_limits::jsonb');
        DB::statement('ALTER TABLE subscription_addons ALTER COLUMN new_limits TYPE jsonb USING new_limits::jsonb');
        DB::statement('ALTER TABLE subscription_addons ALTER COLUMN stripe_metadata TYPE jsonb USING stripe_metadata::jsonb');
        DB::statement('ALTER TABLE subscription_addons ALTER COLUMN compliance_checks TYPE jsonb USING compliance_checks::jsonb');
        
        // Add GIN indexes for JSONB columns
        DB::statement('CREATE INDEX subscription_addons_applied_boost_values_gin_index ON subscription_addons USING GIN (applied_boost_values)');
        DB::statement('CREATE INDEX subscription_addons_compliance_checks_gin_index ON subscription_addons USING GIN (compliance_checks)');
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_addons');
    }
};