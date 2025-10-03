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
        Schema::table('suppliers', function (Blueprint $table) {
            $table->unsignedBigInteger('contact_no_code')->after('data')->nullable();
            $table->unsignedBigInteger('mobile_no_code')->after('contact_no_code')->nullable();
            $table->unsignedBigInteger('country')->after('mobile_no_code')->nullable();
            $table->string('city')->after('country')->nullable();

            $table->foreign('contact_no_code')->references('id')->on('countries')->onDelete('set null');
            $table->foreign('mobile_no_code')->references('id')->on('countries')->onDelete('set null');
            $table->foreign('country')->references('id')->on('countries')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // $table->dropForeign('contact_no_code');
            $table->dropColumn('contact_no_code');
            $table->dropColumn('mobile_no_code');

            // $table->dropForeign('country');
            $table->dropColumn('country');
        });
    }
};
