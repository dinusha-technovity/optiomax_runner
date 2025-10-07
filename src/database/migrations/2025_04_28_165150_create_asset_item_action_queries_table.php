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
        Schema::create('asset_item_action_queries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_item');
            $table->unsignedBigInteger('reading_id');
            $table->unsignedBigInteger('recommendation_id');
            $table->string('source')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('notification_notified_at')->nullable();
            $table->boolean('is_get_action')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_item_action_queries');
    }
};