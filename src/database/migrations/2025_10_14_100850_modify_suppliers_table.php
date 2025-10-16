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
        Schema::table('suppliers', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('is_created_from_imported_csv')->default(false);
            $table->unsignedBigInteger('if_imported_jobs_id')->nullable();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('if_imported_jobs_id')->references('id')->on('import_jobs')->onDelete('restrict');

            // Indexes
            $table->index(['tenant_id', 'supplier_type', 'supplier_reg_status', 'is_created_from_imported_csv']);
            $table->index(['created_by', 'supplier_reg_status']);
            $table->index('if_imported_jobs_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
            $table->dropColumn('is_created_from_imported_csv');
            $table->dropForeign(['if_imported_jobs_id']);
            $table->dropColumn('if_imported_jobs_id');
        });
    }
};
