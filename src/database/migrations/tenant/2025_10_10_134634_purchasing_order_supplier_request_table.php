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
          Schema::create('purchasing_order_supplier_request', function (Blueprint $table) {
            $table->id();
            $table->text('token')->unique();
            $table->string('email');
            $table->string('purchasing_order_number');
            $table->unsignedBigInteger('suppliers_id');
            $table->timestamp('expires_at');
            $table->enum('request_status', ['pending', 'accepted'])->default('pending');
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->foreign('purchasing_order_number')->references('po_number')->on('purchasing_orders')->onDelete('restrict');
            $table->foreign('suppliers_id')->references('id')->on('suppliers')->onDelete('restrict');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchasing_order_supplier_request');
    }
};
