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
        Schema::create('supplier_rating_events', function (Blueprint $table) {
            // Auto-incrementing Primary Key
            $table->id()->primary();

            // Foreign Key
            $table->unsignedBigInteger('supplier_id');

            // Event Details
            $table->text('event_type');
            $table->decimal('event_score', 5, 2);
            $table->decimal('impact_percentage', 5, 2);
            $table->decimal('previous_score', 5, 2);
            $table->decimal('new_score', 5, 2);
            $table->unsignedBigInteger('tenant_id');


            // Created At (TIMESTAMP NOT NULL)
            // We only define created_at as per the schema provided.
            $table->timestamp('created_at')->useCurrent();

            // Indexes
            // 1. Standard index for FK (Postgres creates this automatically for FKs, 
            //    but explicit declaration is safe for clarity or if you want to change index type).
            $table->index('supplier_id');

            // 2. Index for filtering by event type
            $table->index('event_type');

            // 3. Composite Index: Great for "Get all events for supplier X ordered by time"
            $table->index(['supplier_id', 'created_at', 'tenant_id']);

            // Strict Relationship
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
        Schema::dropIfExists('supplier_rating_events');
    }
};
