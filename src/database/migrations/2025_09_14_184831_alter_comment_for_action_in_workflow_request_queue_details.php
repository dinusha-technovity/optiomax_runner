<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_request_queue_details', function (Blueprint $table) {
            // Change column length from 50 -> 2500
            $table->string('comment_for_action', 2500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_request_queue_details', function (Blueprint $table) {
            // Revert back to 50 if rolled back
            $table->string('comment_for_action', 50)->nullable()->change();
        });
    }
};
