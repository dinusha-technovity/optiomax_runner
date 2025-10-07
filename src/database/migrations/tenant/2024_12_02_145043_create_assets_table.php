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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->jsonb('thumbnail_image')->nullable();
            $table->unsignedBigInteger('assets_type');
            $table->unsignedBigInteger('category');
            $table->unsignedBigInteger('sub_category');
            $table->text('asset_description')->nullable();
            $table->jsonb('asset_details')->nullable();
            $table->jsonb('asset_classification')->nullable();
            $table->jsonb('reading_parameters')->nullable();
            $table->unsignedBigInteger('registered_by');
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps(); 

            $table->foreign('assets_type')->references('id')->on('assets_types')->onDelete('restrict');
            $table->foreign('category')->references('id')->on('asset_categories')->onDelete('restrict');
            $table->foreign('sub_category')->references('id')->on('asset_sub_categories')->onDelete('restrict');
            $table->foreign('registered_by')->references('id')->on('users')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};