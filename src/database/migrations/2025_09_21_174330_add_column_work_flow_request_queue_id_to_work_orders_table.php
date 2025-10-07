<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('work_flow_request_queue_id')
                  ->nullable()
                  ->after('work_order_ticket_id');

            $table->foreign('work_flow_request_queue_id')
                  ->references('id')
                  ->on('workflow_request_queues')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['work_flow_request_queue_id']);
            $table->dropColumn('work_flow_request_queue_id');
        });
    }
};
