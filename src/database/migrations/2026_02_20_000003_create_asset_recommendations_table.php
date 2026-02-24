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
        Schema::create('asset_maintenance_recommendations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');

            $table->unsignedBigInteger('asset_id');
            $table->unsignedBigInteger('recommendation_type_id');
            $table->unsignedBigInteger('recommend_user_type_id');

            $table->unsignedBigInteger('recommended_by_user_id');
            $table->text('message');
            $table->string('priority', 20)->nullable(); // LOW, MEDIUM, HIGH
            $table->date('recommendation_date')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('asset_id');
            $table->index('recommendation_type_id');
            $table->index('recommend_user_type_id');
            $table->index('recommended_by_user_id');

            $table->foreign('asset_id')
                ->references('id')->on('asset_items')
                ->onDelete('cascade');

            $table->foreign('recommendation_type_id')
                ->references('id')->on('asset_maintenance_recommendation_types')
                ->onDelete('restrict');

            $table->foreign('recommend_user_type_id')
                ->references('id')->on('asset_maintenance_recommend_user_types')
                ->onDelete('restrict');

            $table->foreign('recommended_by_user_id')
                ->references('id')->on('users')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_maintenance_recommendations');
    }
};
