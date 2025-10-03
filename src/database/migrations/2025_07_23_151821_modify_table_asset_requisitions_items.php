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
        Schema::table('asset_requisitions_items', function (Blueprint $table) {

            $table->foreign(['asset_category'])
                ->references('id')
                ->on('asset_categories')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign(['asset_sub_category'])
                ->references('id')
                ->on('asset_sub_categories')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign(['asset_item_id'])
                ->references('id')
                ->on('asset_items')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            
            $table->dropColumn(['asset_type']);

            $table->index(['asset_requisition_id', 'asset_item_id', 'asset_category', 'asset_sub_category', 'budget_currency']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('asset_requisitions_items', function (Blueprint $table) {
        $table->dropForeign(['asset_category']);
        $table->dropForeign(['asset_sub_category']);
        $table->dropForeign(['asset_item_id']);
        
        $table->bigInteger('asset_type')->nullable();

        $table->dropIndex(['asset_requisition_id', 'asset_item_id', 'asset_category', 'asset_sub_category', 'budget_currency']);
    });
    }
};
