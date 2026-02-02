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
        Schema::create('internal_asset_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('requisition_id', 255);
            $table->unsignedBigInteger('targeted_responsible_person')->nullable();
            $table->unsignedBigInteger('requisition_by')->nullable();
            $table->timestamp('requested_date');
            $table->string('requisition_status', 255)->nullable();
            $table->unsignedBigInteger('work_flow_request')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->foreign('targeted_responsible_person')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('requisition_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('work_flow_request')->references('id')->on('workflow_request_queues')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internal_asset_requisitions');
    }
};
