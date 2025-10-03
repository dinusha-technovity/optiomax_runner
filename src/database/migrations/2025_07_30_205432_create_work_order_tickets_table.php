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
        Schema::create('work_order_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->unsignedBigInteger('reference_id');
            $table->string('type')->default('incident_report');
            $table->boolean('is_get_action')->default(false);
            $table->boolean('is_closed')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->foreign('asset_id')->references('id')->on('asset_items')->onDelete('restrict');
            $table->index(['asset_id', 'reference_id', 'type']);
            $table->index(['tenant_id']);
        });
    }

    /** 
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_tickets');
    }
};