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
        Schema::table('asset_requisitions_items', function (Blueprint $table) {
            $table->unsignedBigInteger('budget_currency')->nullable();
            $table->foreign('budget_currency')->references('id')->on('currencies')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_requisitions_items', function (Blueprint $table) {
            $table->dropForeign(['budget_currency']);
            $table->dropColumn('budget_currency');
        });
    }
};
