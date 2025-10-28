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
            $table->string('passport_client_id')->nullable();
            $table->string('passport_client_secret')->nullable();
            $table->string('optiomesh_public_api_key')->nullable()->unique();
            $table->jsonb('optiomesh_widget_domains')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['passport_client_id', 'passport_client_secret', 'optiomesh_public_api_key', 'optiomesh_widget_domains']);
        });
    }
};