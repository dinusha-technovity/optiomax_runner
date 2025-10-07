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
        Schema::create('goods_received_note', function (Blueprint $table) {
            $table->id();
            $table->string('grn_number');
            $table->string('purchasing_number')->nullable();
            $table->unsignedBigInteger('supplier_id');
            $table->date('receipt_date');
            $table->enum('status', ['draft','posted','cancelled'])->default('draft');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');

            $table->index(['grn_number', 'tenant_id']);
            $table->index(['tenant_id']);
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_received_note');
    }
};