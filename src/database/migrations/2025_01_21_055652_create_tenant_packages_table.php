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
        Schema::create('tenant_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->integer('price')->nullable();
            $table->integer('discount_price')->nullable();
            $table->text('description')->nullable();
            $table->integer('credits')->nullable();
            $table->integer('workflows')->nullable();
            $table->integer('users')->nullable();
            $table->boolean('support')->default(true);
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_packages');
    }
};
