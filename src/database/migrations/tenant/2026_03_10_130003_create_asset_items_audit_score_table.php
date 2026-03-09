<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * ISO 19011:2018 – Calculated aggregate audit score per session.
     * One row per asset_items_audit_session.
     * Formula: Physical(30%) + System(30%) + Compliance(20%) + Risk(20%)
     */
    public function up(): void
    {
        Schema::create('asset_items_audit_score', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('asset_item_audit_session_id')
                  ->comment('FK to asset_items_audit_sessions');
            $table->unsignedBigInteger('asset_item_id')
                  ->comment('FK to asset_items – for quick asset-based queries');

            // Category scores (0-100)
            $table->decimal('physical_score',   5, 2)->nullable()->comment('Physical condition (weight 30%)');
            $table->decimal('system_score',     5, 2)->nullable()->comment('System/functional (weight 30%)');
            $table->decimal('compliance_score', 5, 2)->nullable()->comment('Compliance/regulatory (weight 20%)');
            $table->decimal('risk_score',       5, 2)->nullable()->comment('Risk management (weight 20%)');

            $table->decimal('final_score', 5, 2)->nullable()
                  ->comment('Final weighted score 0-100');
            $table->enum('grade', ['A', 'B', 'C', 'D', 'F'])->nullable()
                  ->comment('A>=90, B>=80, C>=70, D>=60, F<60');

            $table->integer('total_variables_scored')->default(0);
            $table->integer('total_possible_variables')->default(0);
            $table->decimal('completion_percentage', 5, 2)->default(0);

            $table->unsignedBigInteger('calculated_by')->nullable();
            $table->timestamp('calculated_at')->nullable();

            $table->decimal('previous_score', 5, 2)->nullable();
            $table->decimal('score_change',   5, 2)->nullable();
            $table->boolean('is_passing')->nullable();
            $table->decimal('passing_threshold', 5, 2)->default(60.00);

            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id');
            $table->timestamps();

            $table->foreign('asset_item_audit_session_id', 'fk_as_session')
                  ->references('id')->on('asset_items_audit_sessions')->onDelete('cascade');
            $table->foreign('asset_item_id', 'fk_as_asset_item')
                  ->references('id')->on('asset_items')->onDelete('restrict');
            $table->foreign('calculated_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['tenant_id', 'deleted_at', 'isactive'],      'idx_as_tenant_active');
            $table->index(['asset_item_audit_session_id', 'tenant_id'], 'idx_as_session');
            $table->index(['asset_item_id', 'tenant_id'],               'idx_as_asset');
            $table->index(['final_score', 'tenant_id'],                 'idx_as_final');
            $table->index(['grade', 'tenant_id'],                       'idx_as_grade');
            $table->index(['is_passing', 'tenant_id'],                  'idx_as_passing');
            $table->index(['calculated_at', 'tenant_id'],               'idx_as_calculated');

            $table->unique(['asset_item_audit_session_id', 'deleted_at'], 'uniq_as_per_session');
        });

        DB::statement('ALTER TABLE asset_items_audit_score ADD CONSTRAINT chk_as_physical_range   CHECK (physical_score   IS NULL OR (physical_score   >= 0 AND physical_score   <= 100))');
        DB::statement('ALTER TABLE asset_items_audit_score ADD CONSTRAINT chk_as_system_range     CHECK (system_score     IS NULL OR (system_score     >= 0 AND system_score     <= 100))');
        DB::statement('ALTER TABLE asset_items_audit_score ADD CONSTRAINT chk_as_compliance_range CHECK (compliance_score IS NULL OR (compliance_score >= 0 AND compliance_score <= 100))');
        DB::statement('ALTER TABLE asset_items_audit_score ADD CONSTRAINT chk_as_risk_range       CHECK (risk_score       IS NULL OR (risk_score       >= 0 AND risk_score       <= 100))');
        DB::statement('ALTER TABLE asset_items_audit_score ADD CONSTRAINT chk_as_final_range      CHECK (final_score      IS NULL OR (final_score      >= 0 AND final_score      <= 100))');
        DB::statement('ALTER TABLE asset_items_audit_score ADD CONSTRAINT chk_as_completion_range CHECK (completion_percentage >= 0 AND completion_percentage <= 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_items_audit_score');
    }
};
