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
        Schema::table('procurement_finalize_items', function (Blueprint $table) {
            // $table->boolean('is_po_submit')->default(false);
            $table->integer('pending_purchasing_qty')->nullable();
            $table->integer('finalized_qty')->nullable();
            $table->boolean('is_po_completed')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurement_finalize_items', function (Blueprint $table) {
            $table->dropColumn('pending_purchasing_qty');
            $table->dropColumn('finalized_qty');
            $table->dropColumn('is_po_completed');
        });
    }
};
