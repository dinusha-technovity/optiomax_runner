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
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('menulink')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('isconfiguration')->default(true);
            $table->boolean('ismenu_list')->default(true);
            $table->unsignedBigInteger('menu_order')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->boolean('isactive')->default(true);
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            Schema::dropIfExists('permissions');
        });
    }
};