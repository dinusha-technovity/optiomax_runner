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
        Schema::create('document_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_category_id')->nullable();
            $table->unsignedBigInteger('document_field_id')->nullable();
            $table->string("original_file_name");
            $table->string("stored_file_name");
            $table->string("file_size");
            $table->string("mime_type");
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->boolean("isactive");
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('modified_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            // $table->foreign('document_category_id')->references('id')->on('document_category')->onDelete('set null');
            // $table->foreign('document_field_id')->references('id')->on('document_category_field')->onDelete('set null');
            // $table->foreign('document_category_id')->references('id')->on('document_category');
            // $table->foreign('document_field_id')->references('id')->on('document_category_field');

            //why i removed this because When deleting or truncating document_category or document_category_field, the document_media table should be completely unaffected — no deletion, no NULL, no change.
            //That is not possible with a foreign key constraint
            //Foreign keys enforce referential integrity: if a parent row is deleted, the child must either:

            // Be deleted (CASCADE)

            // Be nullified (SET NULL)

            // Block the delete (RESTRICT, default)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_media');
    }
};
