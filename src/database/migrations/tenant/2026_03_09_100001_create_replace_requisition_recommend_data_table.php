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
        Schema::create('replace_requisition_recommend_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('asset_requisition_id');
            $table->unsignedBigInteger('decision_id');
            $table->unsignedBigInteger('asset_id');
            $table->text('recommendation_reason');
            $table->unsignedBigInteger('priority_id')->nullable();
            $table->decimal('estimated_cost', 15, 2)->nullable();
            $table->jsonb('suppliers')->nullable();
            $table->jsonb('specifications')->nullable();
            $table->unsignedBigInteger('recomend_user_type_id')->nullable()->after('recommended_by');
            $table->boolean('is_disposal_recommended')->default(false);
            $table->unsignedBigInteger('disposal_recommendation_id')->nullable();
            $table->unsignedBigInteger('mode_of_acquisition_id')->nullable();
            $table->unsignedBigInteger('recommended_by');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('asset_requisition_id')->references('id')->on('asset_requisitions')->onDelete('cascade');
            $table->foreign('decision_id')->references('id')->on('asset_requisition_decision')->onDelete('cascade');
            $table->foreign('asset_id')->references('id')->on('asset_items')->onDelete('restrict');
            $table->foreign('priority_id')->references('id')->on('asset_requisition_priority_types')->onDelete('set null');
            $table->foreign('disposal_recommendation_id')->references('id')->on('disposal_recommendations')->onDelete('set null');
            $table->foreign('mode_of_acquisition_id')->references('id')->on('asset_requisition_availability_types')->onDelete('set null');
            $table->foreign('recommended_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('recomend_user_type_id')->references('id')->on('asset_recommend_user_types')->onDelete('set null');

            $table->index(['tenant_id', 'asset_requisition_id'], 'idx_rrrd_tenant_req');
            $table->index(['tenant_id', 'decision_id'], 'idx_rrrd_tenant_decision');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('replace_requisition_recommend_data');
    }
};
