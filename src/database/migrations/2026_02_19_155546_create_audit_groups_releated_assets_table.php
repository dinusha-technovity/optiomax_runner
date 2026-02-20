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
        Schema::create('audit_groups_releated_assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('audit_group_id');
            $table->unsignedBigInteger('asset_id');
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('audit_group_id', 'fk_audit_group_assets_group')->references('id')->on('audit_groups')->onDelete('restrict');
            $table->foreign('asset_id', 'fk_audit_group_assets_asset')->references('id')->on('asset_items')->onDelete('restrict');
            
            // Indexes for performance optimization
            $table->index(['audit_group_id', 'tenant_id', 'deleted_at'], 'idx_audit_group_assets_group_tenant');
            $table->index(['asset_id', 'tenant_id', 'deleted_at'], 'idx_audit_group_assets_asset_tenant');
            $table->index(['tenant_id', 'isactive', 'deleted_at'], 'idx_audit_group_assets_tenant_active');
            
            // Unique constraint - one asset can only belong to one audit group at a time per tenant
            $table->unique(['audit_group_id', 'asset_id', 'tenant_id', 'deleted_at'], 'uq_audit_group_asset_tenant');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_groups_releated_assets');
    }
};
