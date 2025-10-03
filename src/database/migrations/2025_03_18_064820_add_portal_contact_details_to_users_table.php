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
        Schema::table('users', function (Blueprint $table) {
            $table->string('portal_contact_no')->nullable();
            $table->unsignedBigInteger('portal_contact_no_code')->nullable();

            $table->string('zip_code')->nullable();
            $table->string('portal_user_zip_code')->nullable();

            $table->unsignedBigInteger('portal_user_country')->nullable();
            $table->string('portal_user_city')->nullable();

            $table->string('portal_user_address')->nullable();

            
            $table->foreign('portal_contact_no_code')->references('id')->on('countries')->onDelete('set null');
            $table->foreign('portal_user_country')->references('id')->on('countries')->onDelete('set null');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'portal_contact_no',
                'portal_contact_no_code',
                'zip_code',
                'portal_user_zip_code',
                'portal_user_country',
                'portal_user_city',
                'portal_user_address'

            ]);
        });
    }
};
