<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master table for zombie asset reporter types.
     * Seeded with: asset_auditor, maintenance_supervisor
     */
    public function up(): void
    {
        Schema::create('zombie_asset_reporter_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('Internal code e.g. asset_auditor');
            $table->string('label', 200)->comment('Human-readable label');
            $table->text('description')->nullable();
            $table->boolean('isactive')->default(true);
            $table->timestamps();

            $table->unique('name', 'uniq_zombie_reporter_type_name');
            $table->index(['isactive'], 'idx_zombie_reporter_type_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zombie_asset_reporter_types');
    }
};
