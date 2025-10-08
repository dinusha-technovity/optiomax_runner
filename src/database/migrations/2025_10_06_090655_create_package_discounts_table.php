<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_discounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // Discount code
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed_amount', 'trial_extension', 'setup_fee_waiver']);
            $table->decimal('value', 10, 2); // Percentage or fixed amount
            
            // Package targeting - now uses slugs
            $table->json('applicable_package_slugs')->nullable(); // Which package slugs this applies to
            $table->json('excluded_package_slugs')->nullable(); // Which package slugs this excludes
            $table->json('applicable_package_types')->nullable(); // INDIVIDUAL, ENTERPRISE
            $table->json('applicable_regions')->nullable(); // Geographic targeting
            
            // Billing and usage restrictions
            $table->enum('applicable_billing_cycles', ['both', 'monthly', 'yearly'])->default('both');
            $table->boolean('apply_to_addons')->default(false); // Whether discount applies to addons too
            $table->boolean('apply_to_setup_fees')->default(false);
            $table->boolean('first_payment_only')->default(false); // Only first payment vs recurring
            
            // Customer restrictions
            $table->boolean('is_first_time_only')->default(false); // Only for new customers
            $table->boolean('requires_existing_subscription')->default(false); // For upgrades only
            $table->json('customer_segments')->nullable(); // Target specific customer segments
            
            // Usage limits and tracking
            $table->integer('usage_limit')->nullable(); // Total times it can be used
            $table->integer('usage_count')->default(0); // Times it has been used
            $table->integer('usage_limit_per_customer')->default(1); // Times per customer
            $table->decimal('minimum_amount', 10, 2)->nullable(); // Minimum order amount
            $table->decimal('maximum_discount_amount', 10, 2)->nullable(); // Cap on discount amount
            
            // Validity and scheduling
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->json('valid_days_of_week')->nullable(); // Specific days when valid
            $table->json('valid_hours')->nullable(); // Time-based restrictions
            
            // Stackability and combinations
            $table->boolean('is_stackable')->default(false); // Can be combined with other discounts
            $table->json('stackable_with')->nullable(); // Specific discount codes it can be combined with
            $table->json('incompatible_with')->nullable(); // Codes it cannot be combined with
            
            // Compliance and legal
            $table->json('compliance_requirements')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->string('legal_version', 50)->default('1.0');
            
            // Integration and tracking
            $table->string('stripe_coupon_id')->nullable();
            $table->string('campaign_id')->nullable(); // Marketing campaign tracking
            $table->string('source')->nullable(); // Where the discount came from
            $table->json('metadata')->nullable(); // Additional tracking data
            
            // Status and features
            $table->boolean('isactive')->default(true);
            $table->boolean('is_public')->default(true); // Whether it appears in public lists
            $table->boolean('requires_approval')->default(false); // Manual approval required
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('approved');
            
            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();
            
            $table->index(['code', 'isactive']);
            $table->index(['valid_from', 'valid_until']);
            $table->index(['type', 'approval_status']);
            $table->index(['campaign_id', 'source']);
            
            // Foreign key constraints
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });
        
        // Convert JSON columns to JSONB and add GIN indexes
        DB::statement('ALTER TABLE package_discounts ALTER COLUMN applicable_package_slugs TYPE jsonb USING applicable_package_slugs::jsonb');
        DB::statement('ALTER TABLE package_discounts ALTER COLUMN excluded_package_slugs TYPE jsonb USING excluded_package_slugs::jsonb');
        DB::statement('ALTER TABLE package_discounts ALTER COLUMN applicable_package_types TYPE jsonb USING applicable_package_types::jsonb');
        DB::statement('ALTER TABLE package_discounts ALTER COLUMN applicable_regions TYPE jsonb USING applicable_regions::jsonb');
        DB::statement('ALTER TABLE package_discounts ALTER COLUMN customer_segments TYPE jsonb USING customer_segments::jsonb');
        DB::statement('ALTER TABLE package_discounts ALTER COLUMN stackable_with TYPE jsonb USING stackable_with::jsonb');
        DB::statement('ALTER TABLE package_discounts ALTER COLUMN incompatible_with TYPE jsonb USING incompatible_with::jsonb');
        DB::statement('ALTER TABLE package_discounts ALTER COLUMN compliance_requirements TYPE jsonb USING compliance_requirements::jsonb');
        DB::statement('ALTER TABLE package_discounts ALTER COLUMN metadata TYPE jsonb USING metadata::jsonb');
        
        // Add GIN indexes for JSONB columns
        DB::statement('CREATE INDEX package_discounts_applicable_package_slugs_gin_index ON package_discounts USING GIN (applicable_package_slugs)');
        DB::statement('CREATE INDEX package_discounts_applicable_package_types_gin_index ON package_discounts USING GIN (applicable_package_types)');
        DB::statement('CREATE INDEX package_discounts_customer_segments_gin_index ON package_discounts USING GIN (customer_segments)');
        DB::statement('CREATE INDEX package_discounts_metadata_gin_index ON package_discounts USING GIN (metadata)');
    }

    public function down(): void
    {
        Schema::dropIfExists('package_discounts');
    }
};