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
        Schema::table('asset_requisitions', function (Blueprint $table) {
            $table->unsignedBigInteger('transitioned_requisition_id')->nullable()->after('requisition_status');
            $table->foreign('transitioned_requisition_id')
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
        Schema::table('asset_requisitions', function (Blueprint $table) {
            $table->dropForeign(['transitioned_requisition_id']);
            $table->dropColumn('transitioned_requisition_id');
        });
    }
};
