<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master table for zombie asset estimated value ranges.
     * Replaces the estimated_value_range ENUM column.
     * Seed data is loaded via ZombieAssetValueRangesSeeder.
     */
    public function up(): void
    {
        Schema::create('zombie_asset_value_ranges', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique()->comment('Machine-readable key, e.g. <1000');
            $table->string('label', 100)->comment('Human-readable label, e.g. Under 1,000');
            $table->text('description')->nullable();
            $table->decimal('min_value', 15, 2)->nullable()
                  ->comment('Inclusive lower bound (null = no lower limit)');
            $table->decimal('max_value', 15, 2)->nullable()
                  ->comment('Inclusive upper bound (null = no upper limit)');
            $table->unsignedTinyInteger('display_order')->default(0);
            $table->unsignedBigInteger('tenant_id')->nullable()
                  ->comment('NULL = global / platform-wide record');
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->timestamps();

            $table->index(['isactive', 'display_order'],          'idx_zavr_active_order');
            $table->index(['tenant_id', 'isactive', 'deleted_at'], 'idx_zavr_tenant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zombie_asset_value_ranges');
    }
};
