<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master table for zombie asset estimated conditions.
     * Replaces the estimated_condition ENUM column.
     * Seed data is loaded via ZombieAssetConditionsSeeder.
     */
    public function up(): void
    {
        Schema::create('zombie_asset_conditions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique()->comment('Machine-readable key, e.g. excellent');
            $table->string('label', 100)->comment('Human-readable label, e.g. Excellent');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('display_order')->default(0);
            $table->unsignedBigInteger('tenant_id')->nullable()
                  ->comment('NULL = global / platform-wide record');
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->timestamps();

            $table->index(['isactive', 'display_order'],         'idx_zac_active_order');
            $table->index(['tenant_id', 'isactive', 'deleted_at'],'idx_zac_tenant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zombie_asset_conditions');
    }
};
