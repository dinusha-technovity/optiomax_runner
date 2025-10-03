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
        Schema::create('asset_item_consumables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_item_id');
            $table->unsignedBigInteger('consumable_id');
            $table->unsignedBigInteger('tenant_id');
            $table->boolean('is_active')->default(true);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->foreign('asset_item_id')->references('id')->on('asset_items')->onDelete('cascade');
            $table->foreign('consumable_id')->references('id')->on('assets')->onDelete('cascade');
        
            $table->index(['asset_item_id', 'consumable_id', 'tenant_id']);
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_item_consumables');
    }
};
