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
        Schema::create('mobile_app_layout_widgets', function (Blueprint $table) {
            $table->id();
            $table->double('x');
            $table->double('y');
            $table->double('w');
            $table->double('h'); 
            $table->text('style');
            $table->boolean('status')->default(true);
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('widget_id')->nullable();
            $table->string('widget_type')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('widget_id')->references('id')->on('app_widgets')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mobile_app_layout_widgets');
    }
};
