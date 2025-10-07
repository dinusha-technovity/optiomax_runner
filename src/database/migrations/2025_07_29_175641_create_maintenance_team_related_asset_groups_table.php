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
        Schema::create('maintenance_team_related_asset_groups', function (Blueprint $table) {
            $table->id(); // Auto-incrementing primary key
            $table->unsignedBigInteger('asset_group_id')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('team_id')->references('id')->on('maintenance_teams')->onDelete('restrict');
            $table->foreign('asset_group_id')->references('id')->on('assets')->onDelete('restrict');

            $table->index(['team_id', 'asset_group_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_team_related_asset_groups');
    }
};
