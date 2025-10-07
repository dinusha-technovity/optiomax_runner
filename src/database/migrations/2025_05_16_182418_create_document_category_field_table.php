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
        Schema::create('document_category_field', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_category_id')->nullable();
            $table->string("document_field_name");
            $table->text("description");
            $table->string("file_path");
            $table->boolean("isactive");
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string("document_formats");
            $table->integer("max_upload_count");
            $table->timestamps();

            // $table->foreign('document_category_id')->references('id')->on('document_category')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_category_field');
    }
};
