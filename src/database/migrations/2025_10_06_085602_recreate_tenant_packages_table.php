<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {  
        // Drop the existing table if it exists
        Schema::dropIfExists('tenant_packages');

        // Create the new tenant_packages table with enhanced flexibility
        Schema::create('tenant_packages', function (Blueprint $table) {
            $table->id();
            
            // Basic package information
            $table->string('name')->nullable();
            $table->string('slug')->unique();
            $table->enum('billing_type', ['monthly', 'yearly', 'both'])->default('both');
            $table->decimal('base_price_monthly', 10, 2)->default(0);
            $table->decimal('base_price_yearly', 10, 2)->default(0);
            $table->text('description')->nullable();
            $table->text('terms_conditions')->nullable(); // Legal terms specific to package
            
            // Billing behavior flags
            $table->boolean('charge_immediately_on_signup')->default(true); // Key requirement
            $table->boolean('prorated_billing')->default(true);
            $table->boolean('allow_downgrades')->default(true);
            $table->boolean('allow_upgrades')->default(true);
            
            // Base package limits (can be overridden by features)
            $table->json('base_limits')->nullable(); // Flexible base limits
            
            // Payment retry configurations
            $table->integer('max_retry_attempts')->default(3);
            $table->integer('retry_interval_days')->default(1);
            $table->integer('grace_period_days')->default(7);
            
            // Package type restrictions and compliance
            $table->json('allowed_regions')->nullable(); // Geographic restrictions
            $table->json('allowed_package_types')->nullable(); // ['INDIVIDUAL', 'ENTERPRISE']
            $table->json('compliance_requirements')->nullable(); // GDPR, SOX, etc.
            $table->json('tax_codes')->nullable(); // For different regions
            
            // Trial and billing settings
            $table->boolean('is_recurring')->default(true);
            $table->integer('trial_days')->default(0);
            $table->boolean('trial_requires_payment_method')->default(true);
            $table->decimal('setup_fee', 10, 2)->default(0);
            $table->enum('cancellation_policy', ['immediate', 'end_of_period', 'with_penalty'])->default('end_of_period');
            
            // Stripe integration
            $table->string('stripe_price_id_monthly')->nullable();
            $table->string('stripe_price_id_yearly')->nullable();
            $table->string('stripe_product_id')->nullable();
            
            // Status and tracking
            $table->boolean('isactive')->default(true);
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_legacy')->default(false); // For deprecated packages
            $table->integer('sort_order')->default(0);
            
            // Legal and compliance timestamps
            $table->timestamp('legal_last_updated')->default(now());
            $table->string('legal_version', 50)->default('1.0');
            
            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['slug', 'isactive']);
            $table->index(['billing_type', 'isactive']);
            $table->index(['is_legacy', 'deleted_at']);
            $table->index('sort_order');
            
            // Foreign key constraints
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('restrict');
        });
        
        // Convert JSON columns to JSONB and add proper GIN indexes
        DB::statement('ALTER TABLE tenant_packages ALTER COLUMN base_limits TYPE jsonb USING base_limits::jsonb');
        DB::statement('ALTER TABLE tenant_packages ALTER COLUMN allowed_regions TYPE jsonb USING allowed_regions::jsonb');
        DB::statement('ALTER TABLE tenant_packages ALTER COLUMN allowed_package_types TYPE jsonb USING allowed_package_types::jsonb');
        DB::statement('ALTER TABLE tenant_packages ALTER COLUMN compliance_requirements TYPE jsonb USING compliance_requirements::jsonb');
        DB::statement('ALTER TABLE tenant_packages ALTER COLUMN tax_codes TYPE jsonb USING tax_codes::jsonb');
        
        // Add GIN indexes for JSONB columns
        DB::statement('CREATE INDEX tenant_packages_base_limits_gin_index ON tenant_packages USING GIN (base_limits)');
        DB::statement('CREATE INDEX tenant_packages_allowed_regions_gin_index ON tenant_packages USING GIN (allowed_regions)');
        DB::statement('CREATE INDEX tenant_packages_allowed_package_types_gin_index ON tenant_packages USING GIN (allowed_package_types)');
        DB::statement('CREATE INDEX tenant_packages_compliance_requirements_gin_index ON tenant_packages USING GIN (compliance_requirements)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_packages');
    }
};