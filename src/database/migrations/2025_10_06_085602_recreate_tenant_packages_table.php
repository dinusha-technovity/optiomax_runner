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

        // Create the new tenant_packages table with all enhanced fields
        Schema::create('tenant_packages', function (Blueprint $table) {
            $table->id();
            
            // Basic package information
            $table->string('name')->nullable();
            $table->enum('type', ['month', 'year'])->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->text('description')->nullable();
            
            // Package limits
            $table->integer('credits')->default(0);
            $table->integer('workflows')->default(0);
            $table->integer('users')->default(1);
            $table->integer('max_storage_gb')->nullable();
            $table->boolean('support')->default(false);
            
            // Payment retry configurations
            $table->integer('max_retry_attempts')->default(3);
            $table->integer('retry_interval_days')->default(1);
            $table->integer('grace_period_days')->default(7);
            
            // Package type restrictions and features
            $table->json('allowed_package_types')->nullable(); // ['INDIVIDUAL', 'ENTERPRISE'] or restricted
            $table->json('features')->nullable(); // Additional features as JSON
            
            // Billing and trial settings
            $table->boolean('is_recurring')->default(true);
            $table->integer('trial_days')->default(0);
            $table->decimal('setup_fee', 10, 2)->default(0);
            
            // Stripe integration
            $table->string('stripe_price_id_monthly')->nullable();
            $table->string('stripe_price_id_yearly')->nullable();
            $table->string('stripe_product_id')->nullable();
            
            // Status and tracking
            $table->boolean('isactive')->default(true);
            $table->boolean('is_popular')->default(false); // For highlighting popular plans
            $table->integer('sort_order')->default(0); // For custom ordering
            
            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['name', 'type']);
            $table->index(['isactive', 'deleted_at']);
            $table->index('sort_order');
            
            // Foreign key constraints
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('restrict');
        });
        
        // Convert JSON columns to JSONB and add proper GIN indexes
        DB::statement('ALTER TABLE tenant_packages ALTER COLUMN allowed_package_types TYPE jsonb USING allowed_package_types::jsonb');
        DB::statement('ALTER TABLE tenant_packages ALTER COLUMN features TYPE jsonb USING features::jsonb');
        
        // Add GIN indexes for JSONB columns
        DB::statement('CREATE INDEX tenant_packages_allowed_package_types_gin_index ON tenant_packages USING GIN (allowed_package_types)');
        DB::statement('CREATE INDEX tenant_packages_features_gin_index ON tenant_packages USING GIN (features)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_packages');
    }
};