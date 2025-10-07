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
        Schema::table('asset_item_consumables', function (Blueprint $table) {
            // First drop the existing foreign key constraint
            $table->dropForeign(['consumable_id']);
            
            // Then add the new foreign key constraint
            $table->foreign('consumable_id')
                  ->references('id')
                  ->on('items')
                  ->onDelete('cascade');
        });  
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_item_consumables', function (Blueprint $table) {
            $table->dropForeign(['consumable_id']);
        });
    }
};
