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
            // Add the column
            $table->unsignedBigInteger('work_flow_request_queue_id')->nullable()->after('tenant_id');

            // Add the foreign key constraint
            $table->foreign('work_flow_request_queue_id')
                  ->references('id')
                  ->on('workflow_request_queues')
                  ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_requisitions', function (Blueprint $table) {
            // Drop foreign key and column
            $table->dropForeign(['work_flow_request_queue_id']);
            $table->dropColumn('work_flow_request_queue_id');
        });
    }
};
