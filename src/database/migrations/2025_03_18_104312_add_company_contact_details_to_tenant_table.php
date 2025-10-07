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
        Schema::table('tenants', function (Blueprint $table) {

            $table->string('zip_code')->nullable();
            $table->string('city')->nullable();
            $table->unsignedBigInteger('country')->nullable();



            $table->foreign('country')->references('id')->on('countries')->onDelete('set null');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'zip_code',
                'city',
                'country',
            ]);
        });
    }
};
