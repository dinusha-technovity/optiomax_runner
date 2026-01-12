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
        Schema::create('asset_items_audit_score', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_item_id');
            $table->unsignedBigInteger('asset_items_audit_sessions_id');
            $table->decimal('final_score', 5, 2)->nullable()->comment('0-100 scale');
            $table->decimal('physical_condition_score', 5, 2)->nullable()->comment('0-100 scale');
            $table->decimal('system_or_operational_condition_score', 5, 2)->nullable()->comment('0-100 scale');
            $table->decimal('compliance_and_usage_score', 5, 2)->nullable()->comment('0-100 scale');
            $table->decimal('risk_and_replacement_need_score', 5, 2)->nullable()->comment('0-100 scale');
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id');
            $table->timestamps();

            // Foreign keys
            $table->foreign('asset_item_id', 'fk_audit_score_asset')
                  ->references('id')->on('asset_items')->onDelete('restrict');
            $table->foreign('asset_items_audit_sessions_id', 'fk_audit_score_session')
                  ->references('id')->on('asset_items_audit_sessions')->onDelete('restrict');

            // Enterprise-level indexes for analytics and reporting
            // Asset item score history
            $table->index(['asset_item_id', 'tenant_id', 'deleted_at', 'created_at'], 'idx_audit_score_asset_history');
            
            // Session lookup
            $table->index(['asset_items_audit_sessions_id', 'tenant_id'], 'idx_audit_score_session');
            
            // Multi-tenant isolation
            $table->index(['tenant_id', 'deleted_at', 'isactive'], 'idx_audit_score_tenant');
            
            // Score-based queries for reporting (find items by score range)
            $table->index(['final_score', 'tenant_id', 'deleted_at'], 'idx_audit_score_final');
            $table->index(['physical_condition_score', 'tenant_id'], 'idx_audit_score_physical');
            $table->index(['risk_and_replacement_need_score', 'tenant_id'], 'idx_audit_score_risk');
            
            // One score record per session (business rule)
            $table->unique(['asset_items_audit_sessions_id', 'deleted_at'], 'uniq_session_score');
            
            // Composite index for dashboard queries (latest scores)
            $table->index(['tenant_id', 'isactive', 'final_score', 'created_at'], 'idx_audit_score_dashboard');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_items_audit_score');
    }
};
