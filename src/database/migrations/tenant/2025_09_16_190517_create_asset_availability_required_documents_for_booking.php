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
        Schema::create('asset_availability_required_documents_for_booking', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_availability_schedule_id');
            $table->unsignedBigInteger('document_category_field_id');
            $table->timestampsTz(0);

            $table->foreign('asset_availability_schedule_id')->references('id')->on('asset_availability_schedules')->onDelete('cascade');
            $table->foreign('document_category_field_id')->references('id')->on('document_category_field')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_availability_required_documents_for_booking');
    }
};
