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
        if (Schema::hasColumn('suppliers', 'supplier_br_attachment')) {
            // Drop old column
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropColumn('supplier_br_attachment');
                $table->dropColumn('asset_categories');

            });
        }

        // Add new JSON column if it doesn't exist
        if (!Schema::hasColumn('suppliers', 'supplier_br_attachment')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->jsonb('supplier_br_attachment')->nullable();
                $table->jsonb('asset_categories')->nullable();

            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // Drop the JSON column if it exists

            $table->dropColumn('supplier_br_attachment');
            $table->dropColumn('asset_categories');


            // Add back the old column (assuming it was a string, adjust type if needed)
            $table->string('supplier_br_attachment')->nullable();
            $table->json('asset_categories')->nullable();
        });
    }
};
