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
            $table->enum('type', ['percentage', 'fixed_amount']);
            $table->decimal('value', 10, 2); // Percentage or fixed amount
            $table->json('applicable_packages')->nullable(); // Which packages this applies to
            $table->json('applicable_package_types')->nullable(); // INDIVIDUAL, ENTERPRISE
            $table->enum('billing_cycles', ['both', 'monthly', 'yearly'])->default('both');
            $table->boolean('is_first_time_only')->default(false); // Only for new customers
            $table->integer('usage_limit')->nullable(); // Total times it can be used
            $table->integer('usage_count')->default(0); // Times it has been used
            $table->integer('usage_limit_per_customer')->default(1); // Times per customer
            $table->decimal('minimum_amount', 10, 2)->nullable(); // Minimum order amount
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->string('stripe_coupon_id')->nullable();
            $table->boolean('isactive')->default(true);
            $table->timestamps();
            
            $table->index(['code', 'isactive']);
            $table->index(['valid_from', 'valid_until']);
        });
        
        // Convert JSON columns to JSONB and add GIN indexes
        DB::statement('ALTER TABLE package_discounts ALTER COLUMN applicable_packages TYPE jsonb USING applicable_packages::jsonb');
        DB::statement('ALTER TABLE package_discounts ALTER COLUMN applicable_package_types TYPE jsonb USING applicable_package_types::jsonb');
        DB::statement('CREATE INDEX package_discounts_applicable_packages_gin_index ON package_discounts USING GIN (applicable_packages)');
        DB::statement('CREATE INDEX package_discounts_applicable_package_types_gin_index ON package_discounts USING GIN (applicable_package_types)');
    }

    public function down(): void
    {
        Schema::dropIfExists('package_discounts');
    }
};