<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('asset_responsible_person')->nullable()->after('technician_id');
            $table->text('asset_responsible_person_note')->nullable();
            $table->boolean('is_deliverd')->default(false);

            $table->foreign('asset_responsible_person')
                  ->references('id')->on('users')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['asset_responsible_person']);
            $table->dropColumn([
                'asset_responsible_person',
                'asset_responsible_person_note',
                'is_deliverd'
            ]);
        });
    }
};