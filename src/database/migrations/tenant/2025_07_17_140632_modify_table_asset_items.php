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
        Schema::table('asset_items', function (Blueprint $table){
            $table->bigInteger('asset_requisition_item_id')->nullable();
            $table->bigInteger('asset_requisition_id')->nullable();
            $table->bigInteger('procurement_id')->nullable();



            //foreign key
            $table->foreign('asset_requisition_item_id')
                ->references('id')
                ->on('asset_requisitions_items')
                ->onDelete('set null')
                ->onUpdate('cascade');

            $table->foreign('asset_requisition_id')
                ->references('id')
                ->on('asset_requisitions')
                ->onDelete('set null')
                ->onUpdate('cascade');

            $table->foreign('procurement_id')
                ->references('id')
                ->on('procurements')
                ->onDelete('set null')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_items', function (Blueprint $table) {
            $table->dropColumn('asset_requisition_item_id');
            $table->dropForeign(['asset_requisition_item_id']);

            $table->dropColumn('asset_requisition_id');
            $table->dropForeign(['asset_requisition_id']);

            $table->dropColumn('procurement_id');
            $table->dropForeign(['procurement_id']);
        });
    }
};
