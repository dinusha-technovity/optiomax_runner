<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop foreign key if exists
        if (Schema::hasColumn('items', 'category_id')) {
            try {
                Schema::table('items', function (Blueprint $table) {
                    $table->dropForeign(['category_id']);
                });
            } catch (\Throwable $e) {
                // Foreign key may not exist
            }

            // Now drop the column
            Schema::table('items', function (Blueprint $table) {
                $table->dropColumn('category_id');
            });
        }

        // Add the new category_id referencing asset_categories
        Schema::table('items', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('tenant_id');
            $table->foreign('category_id')
                ->references('id')
                ->on('asset_categories')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        // Reverse: drop new foreign key and column
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });

        // Restore original category_id referencing item_categories
        Schema::table('items', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('tenant_id');
            $table->foreign('category_id')
                ->references('id')
                ->on('item_categories')
                ->onDelete('restrict');
        });
    }
};
