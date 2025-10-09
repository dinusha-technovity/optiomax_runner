<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_addons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->enum('addon_type', ['feature_boost', 'limit_increase', 'service', 'integration']); // More specific types
            $table->string('target_feature')->nullable(); // What feature this addon affects (credits, users, storage, etc.)
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_yearly', 10, 2)->default(0);
            $table->json('boost_values')->nullable(); // What values this addon provides
            
            // Package compatibility - now uses slugs for better maintainability
            $table->json('applicable_package_slugs')->nullable(); // Package slugs this applies to
            $table->json('excluded_package_slugs')->nullable(); // Package slugs this doesn't apply to
            $table->json('applicable_package_types')->nullable(); // INDIVIDUAL, ENTERPRISE
            $table->json('regional_restrictions')->nullable(); // Geographic limitations
            
            // Addon behavior
            $table->boolean('is_stackable')->default(true);
            $table->integer('max_quantity')->nullable();
            $table->integer('min_quantity')->default(1);
            $table->boolean('requires_approval')->default(false);
            $table->boolean('auto_scale')->default(false); // Automatically scale based on usage
            
            // Billing and metering
            $table->boolean('is_metered')->default(false);
            $table->decimal('metered_rate', 10, 4)->default(0);
            $table->enum('metered_unit', ['per_credit', 'per_user', 'per_gb', 'per_api_call', 'per_workflow'])->nullable();
            $table->boolean('prorated_billing')->default(true);
            
            // Compliance and legal
            $table->json('compliance_requirements')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->string('legal_version', 50)->default('1.0');
            
            // Stripe integration
            $table->string('stripe_price_id_monthly')->nullable();
            $table->string('stripe_price_id_yearly')->nullable();
            $table->string('stripe_product_id')->nullable();
            $table->string('stripe_meter_id')->nullable(); // For metered addons
            
            // Status and ordering
            $table->boolean('isactive')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            
            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            
            $table->index(['addon_type', 'isactive']);
            $table->index(['target_feature', 'isactive']);
            $table->index(['is_featured', 'sort_order']);
            $table->index(['available_from', 'available_until']);
            
            // Foreign key constraints
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
        
        // Convert JSON columns to JSONB and add GIN indexes
        DB::statement('ALTER TABLE package_addons ALTER COLUMN boost_values TYPE jsonb USING boost_values::jsonb');
        DB::statement('ALTER TABLE package_addons ALTER COLUMN applicable_package_slugs TYPE jsonb USING applicable_package_slugs::jsonb');
        DB::statement('ALTER TABLE package_addons ALTER COLUMN excluded_package_slugs TYPE jsonb USING excluded_package_slugs::jsonb');
        DB::statement('ALTER TABLE package_addons ALTER COLUMN applicable_package_types TYPE jsonb USING applicable_package_types::jsonb');
        DB::statement('ALTER TABLE package_addons ALTER COLUMN regional_restrictions TYPE jsonb USING regional_restrictions::jsonb');
        DB::statement('ALTER TABLE package_addons ALTER COLUMN compliance_requirements TYPE jsonb USING compliance_requirements::jsonb');
        
        // Add GIN indexes for JSONB columns
        DB::statement('CREATE INDEX package_addons_boost_values_gin_index ON package_addons USING GIN (boost_values)');
        DB::statement('CREATE INDEX package_addons_applicable_package_slugs_gin_index ON package_addons USING GIN (applicable_package_slugs)');
        DB::statement('CREATE INDEX package_addons_applicable_package_types_gin_index ON package_addons USING GIN (applicable_package_types)');
    }

    public function down(): void
    {
        Schema::dropIfExists('package_addons');
    }
};