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
        Schema::create('asset_item_critically_based_maintain_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_item');
            $table->text('assessment_description')->nullable();
            $table->string('schedule');
            $table->string('expected_results');
            $table->string('comments');
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->foreign('asset_item')->references('id')->on('asset_items')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_item_critically_based_maintain_schedules');
    }
};
