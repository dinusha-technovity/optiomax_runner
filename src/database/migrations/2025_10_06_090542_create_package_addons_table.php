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
            $table->enum('type', ['credits', 'workflows', 'users', 'storage', 'feature']);
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_yearly', 10, 2)->default(0);
            $table->integer('quantity')->default(1);
            $table->json('applicable_packages')->nullable();
            $table->boolean('is_stackable')->default(true);
            $table->integer('max_quantity')->nullable();
            $table->string('stripe_price_id_monthly')->nullable();
            $table->string('stripe_price_id_yearly')->nullable();
            $table->boolean('isactive')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['type', 'isactive']);
        });
        
        // Convert JSON column to JSONB and add GIN index
        DB::statement('ALTER TABLE package_addons ALTER COLUMN applicable_packages TYPE jsonb USING applicable_packages::jsonb');
        DB::statement('CREATE INDEX package_addons_applicable_packages_gin_index ON package_addons USING GIN (applicable_packages)');
    }

    public function down(): void
    {
        Schema::dropIfExists('package_addons');
    }
};