<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Add the new column
            $table->unsignedBigInteger('item_category_type_id')->nullable()->after('category_id');

            // Add foreign key constraint
            $table->foreign('item_category_type_id')
                  ->references('id')
                  ->on('item_category_type')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['item_category_type_id']);

            // Drop column
            $table->dropColumn('item_category_type_id');
        });
    }
};
