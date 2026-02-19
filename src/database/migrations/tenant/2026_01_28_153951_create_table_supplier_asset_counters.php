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
        Schema::create('supplier_asset_counters', function (Blueprint $table) {
            // supplier_id is both the PK and the FK
            $table->id();
            $table->unsignedBigInteger('supplier_id');

            // asset_count mapping to INT NOT NULL DEFAULT 0
            $table->integer('asset_count')->default(0);

            $table->unsignedBigInteger('tenant_id');

            // Only updated_at is required (no created_at)
            // useCurrent() translates to DEFAULT now() in Postgres
            $table->timestamp('updated_at')->useCurrent();

            // Strict Relationship
            // If a supplier is deleted, their counter row should be removed.
            $table->foreign('supplier_id')
                  ->references('id')
                  ->on('suppliers')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_asset_counters');
    }
};