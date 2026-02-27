<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asset_recommendations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('asset_id');
            $table->unsignedBigInteger('asset_requisition_id')->nullable();
            $table->unsignedBigInteger('recommendation_type_id');
            $table->unsignedBigInteger('recommend_user_type_id');
            $table->unsignedBigInteger('recommended_by_user_id');
            $table->unsignedBigInteger('asset_requisition_action_id')->nullable();
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->text('message');
            $table->string('priority', 20)->nullable();
            $table->date('recommendation_date')->nullable();
            $table->boolean('is_get_action')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('asset_id')->references('id')->on('asset_items')->onDelete('cascade');
            $table->foreign('asset_requisition_id')->references('id')->on('asset_requisitions')->onDelete('set null');
            $table->foreign('recommendation_type_id')->references('id')->on('asset_recommendation_types')->onDelete('restrict');
            $table->foreign('recommend_user_type_id')->references('id')->on('asset_recommend_user_types')->onDelete('restrict');
            $table->foreign('recommended_by_user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('asset_requisition_action_id')->references('id')->on('asset_requisition_actions')->onDelete('set null');
            $table->foreign('work_order_id')->references('id')->on('work_orders')->onDelete('set null');
        });

        DB::statement('CREATE INDEX idx_ar_tenant_asset ON asset_recommendations (tenant_id, asset_id)');
        DB::statement('CREATE INDEX idx_ar_tenant_type ON asset_recommendations (tenant_id, recommendation_type_id)');
        DB::statement('CREATE INDEX idx_ar_tenant_date ON asset_recommendations (tenant_id, recommendation_date)');

        DB::unprepared(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_ar_tenant_upgrade_active
                ON asset_recommendations (tenant_id)
                WHERE deleted_at IS NULL AND is_active = true AND asset_requisition_id IS NOT NULL;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_recommendations');
    }
};
