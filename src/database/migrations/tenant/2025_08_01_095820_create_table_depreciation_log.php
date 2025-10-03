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
        Schema::create('depreciation_log', function (Blueprint $table) {
            $table->id();

            $table->timestamp('start_time');
            $table->timestamp('end_time');

            $table->unsignedInteger('total_assets');
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('tenant_id')->nullable();

            $table->jsonb('failed_assets')->nullable(); // JSON field for failed assets (IDs or with error messages)
            $table->jsonb('system_errors')->nullable(); // JSON field for system errors encountered during the batch
            $table->text('message')->nullable();       // General message or summary of run
            $table->string('executed_by', 100)->nullable(); // Who executed the batch (cron, user, etc.)

            $table->timestamps(); // created_at & updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('depreciation_log');
    }
};
