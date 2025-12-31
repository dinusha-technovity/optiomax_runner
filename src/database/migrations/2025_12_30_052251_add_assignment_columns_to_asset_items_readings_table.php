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
        Schema::table('asset_items_readings', function (Blueprint $table) {
            $table->boolean('is_group')->default(false)->after('isactive');
            $table->boolean('is_single')->default(false)->after('is_group');
            $table->unsignedBigInteger('employee')->nullable()->after('is_single');

            $table->foreign('employee')
                ->references('id')
                ->on('employees')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_items_readings', function (Blueprint $table) {
            $table->dropForeign(['employee']);
            $table->dropColumn(['is_group', 'is_single', 'employee']);
        });
    }
};
