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
        Schema::create('purchasing_order_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('po_id');
            $table->integer('attempt_no')->default(1);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('initiated_by')->nullable();
            $table->timestamp('initiated_at')->useCurrent();
            $table->text('remarks')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->foreign('po_id')->references('id')->on('purchasing_orders')->onDelete('restrict');
            $table->foreign('initiated_by')->references('id')->on('users')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchasing_order_attempts');
    }
};
