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
        Schema::create('asset_availability_schedule_occurrences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_id');
            $table->unsignedBigInteger('asset_id')->after('id');
            $table->timestampTz('occurrence_start');
            $table->timestampTz('occurrence_end');
            $table->boolean('is_cancelled')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestampsTz(0);

            $table->foreign('schedule_id')->references('id')->on('asset_availability_schedules')->onDelete('restrict');
            $table->foreign('asset_id')->references('id')->on('asset_items')->onDelete('restrict');
            $table->index(['asset_id', 'tenant_id', 'occurrence_start', 'occurrence_end']);
            $table->index(['schedule_id', 'occurrence_start', 'occurrence_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_availability_schedule_occurrences');
    }
};