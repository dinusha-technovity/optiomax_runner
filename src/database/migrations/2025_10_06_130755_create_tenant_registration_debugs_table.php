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
        Schema::create('tenant_registration_debugs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->unsignedBigInteger('selected_package_id')->nullable();
            $table->string('package_type')->nullable();
            $table->jsonb('invited_users')->nullable();
            $table->jsonb('validated_user')->nullable();
            $table->string('status')->default('pending'); // new
            $table->text('error_message')->nullable(); // new
            $table->timestamps();

            $table->foreign('owner_user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('selected_package_id')->references('id')->on('tenant_packages')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_registration_debugs');
    }
};
