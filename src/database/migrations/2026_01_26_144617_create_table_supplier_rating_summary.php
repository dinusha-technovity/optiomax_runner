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
        Schema::create('supplier_rating_summary', function (Blueprint $table) {
            // Using unsignedBigInteger assuming suppliers.id is a big integer
            $table->unsignedBigInteger('supplier_id')->primary();
            
            // Score Columns mapping to NUMERIC(5,2)
            $table->decimal('asset_score', 5, 2)->nullable();
            $table->decimal('quality_score', 5, 2)->nullable();
            $table->decimal('response_score', 5, 2)->nullable();
            $table->decimal('fulfillment_score', 5, 2)->nullable();
            $table->decimal('cost_score', 5, 2)->nullable();

            // Calculated Totals
            $table->decimal('final_score', 5, 2)->nullable();
            $table->integer('star_rating')->nullable();
            $table->unsignedBigInteger('tenant_id');


            // Timestamps
            // useCurrent() satisfies the TIMESTAMP NOT NULL requirement in Postgres
            $table->timestamp('last_calculated_at')->useCurrent();

            // Strict Relationship: Cascade delete ensures if supplier is deleted, 
            // their summary is also removed to maintain integrity.
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
        Schema::dropIfExists('supplier_rating_summary');
    }
};