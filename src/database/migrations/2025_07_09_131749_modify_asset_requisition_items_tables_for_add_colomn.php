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
            $table->bigInteger('acquisition_type')->nullable()->after('item_id');
            $table->integer('expected_depreciation_value')->nullable();

            //set relasionship with asset_requisition_acquisition_types table
            $table->foreign('acquisition_type')
                ->references('id')
                ->on('asset_requisition_acquisition_types')
                ->onDelete('set null')
                ->onUpdate('cascade');

                
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_requisitions_items', function (Blueprint $table) {
            $table->dropColumn('acquisition_type');
            $table->dropColumn('expected_depreciation_value');

            $table->dropForeign(['acquisition_type']);
        });
    }
};
