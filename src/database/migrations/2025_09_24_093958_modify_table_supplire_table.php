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
        Schema::table('suppliers', function (Blueprint $table) {
           
            $table->unsignedBigInteger('workflow_queue_id')->nullable();
        
            $table->foreign('workflow_queue_id')->references('id')->on('workflow_request_queues')->onDelete('set null');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropForeign(['workflow_queue_id']);
            $table->dropColumn('workflow_queue_id');
        });
    }
};
