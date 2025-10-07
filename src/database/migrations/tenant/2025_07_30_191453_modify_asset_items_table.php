<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('asset_items', function (Blueprint $table) {
            $table->decimal('salvage_value', 14, 2)->nullable();
            $table->decimal('total_estimated_units', 14, 2)->nullable();
            $table->date('depreciation_start_date')->nullable();
            $table->string('partial_year_rule')->default('yearly'); //yearly, monthly, half_yearly, full_monthly, actual_days
            $table->boolean('switch_to_straight_line')->default(false);
            $table->unsignedBigInteger('expected_life_time_unit')->nullable();
            $table->decimal('decline_rate', 14, 2)->nullable();

            $table->foreign('expected_life_time_unit')
                ->references('id')
                ->on('time_period_entries')
                ->onDelete('set null');

             $table->index(['expected_life_time_unit', 'depreciation_method']);

        });

        DB::statement('ALTER TABLE asset_items ALTER COLUMN depreciation_method TYPE bigint USING depreciation_method::bigint;');

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_items', function (Blueprint $table) {
            $table->dropForeign(['expected_life_time_unit']);
            $table->dropColumn([
                'salvage_value',
                'total_estimated_units',
                'depreciation_start_date',
                'partial_year_rule',
                'switch_to_straight_line',
                'expected_life_time_unit',
                'decline_rate'
            ]);
            $table->dropIndex(['expected_life_time_unit', 'depreciation_method']);
        });
        DB::statement('ALTER TABLE asset_items ALTER COLUMN depreciation_method TYPE varchar USING depreciation_method::varchar;');
    }
};
