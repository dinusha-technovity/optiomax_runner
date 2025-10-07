<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['technician_id']);
            $table->dropColumn('technician_id');
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('technician_id')->nullable()->after('asset_item_id');
            $table->foreign('technician_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['technician_id']);
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['technician_id']);
            $table->dropColumn('technician_id');
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('technician_id')->nullable()->after('asset_item_id');
            $table->foreign('technician_id')->references('id')->on('work_order_technicians')->onDelete('set null');
        });
    }
};