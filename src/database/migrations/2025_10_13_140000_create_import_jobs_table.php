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
        Schema::create('import_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('type'); // 'asset_items_csv', etc.
            $table->string('status')->default('pending'); // pending, processing, completed, failed, cancelled
            $table->string('file_name');
            $table->string('file_path');
            $table->bigInteger('file_size');
            $table->integer('total_rows')->default(0);
            $table->integer('total_processed')->default(0);
            $table->integer('total_inserted')->default(0);
            $table->integer('total_updated')->default(0);
            $table->integer('total_errors')->default(0);
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('estimated_time_remaining')->nullable();
            $table->text('message')->nullable();
            $table->json('error_details')->nullable();
            $table->json('options')->nullable();
            $table->json('statistics')->nullable();
            $table->string('queue_name')->nullable();
            $table->integer('priority')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->integer('chunk_count')->default(1);
            $table->integer('chunks_completed')->default(0);
            $table->text('processing_log')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'type', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['tenant_id', 'status', 'priority']);
            $table->index(['queue_name', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_jobs');
    }
};