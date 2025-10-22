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
        Schema::table('assets', function (Blueprint $table) {
            $table->boolean('is_created_from_imported_csv')->default(false);
            $table->unsignedBigInteger('if_imported_jobs_id')->nullable();

            $table->foreign('if_imported_jobs_id')->references('id')->on('import_jobs')->onDelete('restrict');

            // Indexes
            $table->index(['tenant_id', 'is_created_from_imported_csv', 'registered_by', 'isactive']);
            $table->index(['registered_by']);
            $table->index('if_imported_jobs_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('is_created_from_imported_csv');
            $table->dropForeign(['if_imported_jobs_id']);
            $table->dropColumn('if_imported_jobs_id');
        });
    }
};