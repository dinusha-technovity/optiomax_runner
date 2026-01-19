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
            // Account status fields
            $table->boolean('user_account_enabled')->default(true)->after('is_user_active');
            $table->boolean('employee_account_enabled')->default(false)->after('user_account_enabled');

            // Employee-specific field
            $table->string('employee_number')->nullable()->unique()->after('employee_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop columns
            $table->dropColumn([
                'user_account_enabled',
                'employee_account_enabled',
                'employee_number'
            ]);
        });
    }
};
