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
        Schema::table('work_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('asset_requisition_id')->nullable();

            $table->foreign('asset_requisition_id')
                ->references('id')
                ->on('asset_requisitions')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['asset_requisition_id']);
            $table->dropColumn('asset_requisition_id');
        });
    }
};
