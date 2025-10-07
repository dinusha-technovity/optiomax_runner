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
        Schema::table('suppliers_for_item', function (Blueprint $table) {
            // Drop the old foreign key
            $table->dropForeign(['supplier_id']);
            // Add the new foreign key referencing 
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');
        });
    } 

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers_for_item', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);

            // Restore old foreign key if needed
            $table->foreign('supplier_id')->references('id')->on('item_suppliers')->onDelete('restrict');
        });
    }
};
