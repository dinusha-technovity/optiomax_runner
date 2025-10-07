<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_attempt_request_items_related_tax_rate', function (Blueprint $table) {
            $table->id();
            
            // Foreign key column - better to name it with `_id` suffix
            $table->unsignedBigInteger('procurement_attempt_request_item_id');
            
            $table->string('tax_type')->nullable();
            $table->decimal('tax_rate', 10, 2)->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('procurement_attempt_request_item_id')
                ->references('id')
                ->on('procurement_attempt_request_items')
                ->onDelete('restrict');

            $table->index('procurement_attempt_request_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_attempt_request_items_related_tax_rate');
    }
};