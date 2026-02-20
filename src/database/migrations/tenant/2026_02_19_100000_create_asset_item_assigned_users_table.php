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
        Schema::create('asset_item_assigned_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_item_id');
            $table->unsignedBigInteger('user_id');
            $table->string('assign_status')->default('assigned');
            $table->unsignedBigInteger('tenant_id');
            $table->boolean('is_active')->default(true);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('asset_item_id')->references('id')->on('asset_items')->onDelete('restrict');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            
            // Indexes
            $table->index('asset_item_id');
            $table->index('user_id');
            $table->index('tenant_id');
            $table->index(['asset_item_id', 'user_id', 'tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_item_assigned_users');
    }
};
