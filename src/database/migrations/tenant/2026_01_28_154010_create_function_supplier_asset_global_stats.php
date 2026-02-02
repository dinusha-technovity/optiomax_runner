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
        Schema::create('supplier_asset_global_stats', function (Blueprint $table) {
            // Standard Auto-Increment ID
            // We removed 'DEFAULT 1' and the CHECK constraint because 
            // this table now holds multiple rows (one per tenant).
            $table->id();

            // Tenant ID: Groups the stats to a specific tenant
            $table->unsignedBigInteger('tenant_id');

            // Stats Columns
            $table->integer('highest_asset_count')->default(0);
            
            // The supplier holding the highest count for this tenant
            $table->unsignedBigInteger('highest_supplier_id')->nullable();

            // Timestamp
            $table->timestamp('updated_at')->useCurrent();

            // CONSTRAINTS & INDEXES

            // 1. Unique Constraint: Ensures strictly ONE stats row per tenant
            $table->unique('tenant_id');
    
            // 3. Foreign Key: Supplier Relationship
            // If the top supplier is deleted, we set the reference to null.
            $table->foreign('highest_supplier_id')
                  ->references('id')
                  ->on('suppliers')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_asset_global_stats');
    }
};