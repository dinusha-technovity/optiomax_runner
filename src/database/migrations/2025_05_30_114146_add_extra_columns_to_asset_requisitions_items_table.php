<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('asset_requisitions_items', function (Blueprint $table) {
            $table->unsignedBigInteger('asset_item_id')->nullable()->after('item_name');
            $table->unsignedBigInteger('asset_category')->nullable()->after('asset_item_id');
            $table->unsignedBigInteger('asset_sub_category')->nullable()->after('asset_category');
            $table->text('description')->nullable()->after('asset_sub_category');
            $table->string('kpi_type', 255)->nullable()->after('description');
            $table->string('new_detail_type', 255)->nullable()->after('kpi_type');
            $table->text('new_details')->nullable()->after('new_detail_type');
            $table->text('new_kpi_details')->nullable()->after('new_details');

            // Optional foreign key constraints
            // $table->foreign('asset_item_id')->references('id')->on('asset_items')->onDelete('set null');
            // $table->foreign('asset_category')->references('id')->on('asset_categories')->onDelete('set null');
            // $table->foreign('asset_sub_category')->references('id')->on('asset_sub_categories')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('asset_requisitions_items', function (Blueprint $table) {
            $table->dropColumn([
                'asset_item_id',
                'asset_category',
                'asset_sub_category',
                'description',
                'kpi_type',
                'new_detail_type',
                'new_details',
                'new_kpi_details'
            ]);
        });
    }
};
