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
        Schema::create('supplier_email_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reference_id');
            $table->string('email');
            $table->string('type')->default('quotation_expiry');
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true); 
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->index(['reference_id', 'type']);
            $table->index(['tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_email_logs');
    }
};
