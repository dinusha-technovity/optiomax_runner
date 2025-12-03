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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Role name (e.g. Admin, Editor)
            $table->text('description')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // $table->unique(['name', 'tenant_id']); // Ensure role name uniqueness per tenant
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
