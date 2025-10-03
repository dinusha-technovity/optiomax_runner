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
        Schema::create('procurements_quotation_request_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('procurement_id');
            $table->unsignedBigInteger('attemp_number');
            $table->unsignedBigInteger('attempted_by')->nullable();
            $table->date('closing_date')->nullable();
            $table->string('request_attempts_status')->default('pending');
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->foreign('procurement_id')->references('id')->on('procurements')->onDelete('restrict');
            $table->foreign('attempted_by')->references('id')->on('users')->onDelete('restrict');
            $table->index(['procurement_id', 'attempted_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurements_quotation_request_attempts');
    }
};