<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('role_widget', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('widget_id');
            $table->json('settings')->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->timestamp('deleted_at')->nullable()->index();

            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('widget_id')->references('id')->on('app_widgets')->onDelete('cascade');
        });

        // Add partial unique index (only when active and not deleted)
        DB::statement("
            CREATE UNIQUE INDEX role_widget_unique_active
            ON role_widget (role_id, widget_id, tenant_id)
            WHERE is_active = true AND deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('role_widget');
    }
};