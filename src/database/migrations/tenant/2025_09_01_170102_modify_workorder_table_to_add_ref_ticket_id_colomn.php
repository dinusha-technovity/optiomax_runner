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
            $table->bigInteger('work_order_ticket_id')->nullable();
            $table->foreign('work_order_ticket_id')->references('id')->on('work_order_tickets')->onDelete('set null');
            $table->index(['work_order_ticket_id']);
        });

        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['work_order_ticket_id']);
            $table->dropIndex(['work_order_ticket_id']);
            $table->dropColumn('work_order_ticket_id');
        });
    }
};
