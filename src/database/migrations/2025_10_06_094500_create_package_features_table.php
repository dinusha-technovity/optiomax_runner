<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_features', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('package_id');
            $table->string('feature_key'); // credits, workflows, users, storage_gb, support, etc.
            $table->string('feature_name');
            $table->text('description')->nullable();
            $table->enum('feature_type', ['limit', 'boolean', 'feature', 'service']); // Type of feature
            $table->string('value_type', 50)->default('integer'); // integer, boolean, string, json
            $table->text('default_value')->nullable(); // Default value for this feature
            $table->text('max_value')->nullable(); // Maximum allowed value
            $table->text('min_value')->nullable(); // Minimum allowed value
            
            // Pricing for this specific feature
            $table->decimal('additional_cost_monthly', 10, 2)->default(0); // Extra cost per month
            $table->decimal('additional_cost_yearly', 10, 2)->default(0); // Extra cost per year
            $table->boolean('is_billable')->default(false); // Whether this feature affects billing
            $table->boolean('is_metered')->default(false); // Whether usage is tracked and billed
            $table->decimal('metered_rate', 10, 4)->default(0); // Rate per unit for metered features
            
            // Feature behavior
            $table->boolean('is_upgradeable')->default(true); // Can be increased
            $table->boolean('is_downgradeable')->default(true); // Can be decreased
            $table->boolean('requires_approval')->default(false); // Requires manual approval for changes
            $table->boolean('is_overridable')->default(true); // Can be overridden per tenant
            
            // Compliance and restrictions
            $table->json('compliance_notes')->nullable(); // Any compliance requirements for this feature
            $table->json('regional_restrictions')->nullable(); // Geographic limitations
            
            // Stripe integration for metered features
            $table->string('stripe_meter_id')->nullable();
            $table->string('stripe_price_id')->nullable();
            
            $table->boolean('isactive')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('package_id')->references('id')->on('tenant_packages')->onDelete('cascade');
            
            $table->unique(['package_id', 'feature_key']);
            $table->index(['feature_key', 'isactive']);
            $table->index(['feature_type', 'is_billable']);
        });
        
        // Convert JSON columns to JSONB
        DB::statement('ALTER TABLE package_features ALTER COLUMN compliance_notes TYPE jsonb USING compliance_notes::jsonb');
        DB::statement('ALTER TABLE package_features ALTER COLUMN regional_restrictions TYPE jsonb USING regional_restrictions::jsonb');
        DB::statement('CREATE INDEX package_features_compliance_notes_gin_index ON package_features USING GIN (compliance_notes)');
        DB::statement('CREATE INDEX package_features_regional_restrictions_gin_index ON package_features USING GIN (regional_restrictions)');
    }

    public function down(): void
    {
        Schema::dropIfExists('package_features');
    }
};
