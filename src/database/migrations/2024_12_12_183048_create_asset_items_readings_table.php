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
        Schema::create('asset_items_readings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_item');
            $table->string('parameter')->nullable();
            $table->string('value')->nullable();
            $table->unsignedBigInteger('record_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->foreign('asset_item')->references('id')->on('asset_items')->onDelete('restrict');
            $table->foreign('record_by')->references('id')->on('users')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_items_readings');
    }
};