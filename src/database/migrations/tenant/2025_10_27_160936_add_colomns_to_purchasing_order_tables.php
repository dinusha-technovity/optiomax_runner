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
        Schema::table('purchasing_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('overall_tax_id')->nullable();
            $table->decimal('overall_tax_rate', 10, 4)->default(0);
            $table->decimal('overall_discount_percentage', 5, 2)->default(0);

            $table->foreign('overall_tax_id')->references('id')->on('tax_master')->onDelete('restrict');
        });

        Schema::table('purchasing_order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('tax_id')->nullable();
            $table->decimal('tax_rate', 10, 4)->default(0);
            $table->decimal('discount_percentage', 5, 2)->default(0);

            $table->foreign('tax_id')->references('id')->on('tax_master')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchasing_orders', function (Blueprint $table) {
            $table->dropColumn('overall_tax_rate');
            $table->dropColumn('overall_tax_id');
            $table->dropColumn('overall_discount_percentage');

            $table->dropForeign(['overall_tax_id']);
        });

        Schema::table('purchasing_order_items', function (Blueprint $table) {
            $table->dropColumn('tax_id');
            $table->dropColumn('tax_rate');
            $table->dropColumn('discount_percentage');

            $table->dropForeign(['tax_id']);
        });
    }
};
