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
        Schema::create('asset_usage_based_maintain_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset');
            $table->unsignedBigInteger('maintain_schedule_parameters');
            $table->string('limit_or_value');
            $table->string('operator');
            $table->string('reading_parameters');
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->foreign('asset')->references('id')->on('assets')->onDelete('restrict');
            $table->foreign('maintain_schedule_parameters')->references('id')->on('asset_maintain_schedule_parameters')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_usage_based_maintain_schedules');
    }
};
