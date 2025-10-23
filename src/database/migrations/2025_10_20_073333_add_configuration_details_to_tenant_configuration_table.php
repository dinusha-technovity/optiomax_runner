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
        Schema::table('tenant_configuration', function (Blueprint $table) {
            $table->jsonb('configuration_details')->nullable()->after('system_user_password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_configuration', function (Blueprint $table) {
            $table->dropColumn('configuration_details');
        });
    }
};
