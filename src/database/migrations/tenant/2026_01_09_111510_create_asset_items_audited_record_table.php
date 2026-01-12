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
        Schema::create('asset_items_audited_record', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_items_audit_sessions_id');
            $table->unsignedBigInteger('asset_audit_variable_id');
            $table->string('score', 50)->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id');
            $table->timestamps();

            // Foreign keys
            $table->foreign('asset_items_audit_sessions_id', 'fk_audited_record_session')
                  ->references('id')->on('asset_items_audit_sessions')->onDelete('restrict');
            $table->foreign('asset_audit_variable_id', 'fk_audited_record_variable')
                  ->references('id')->on('asset_audit_variable')->onDelete('restrict');

            // Enterprise-level indexes
            // Primary query pattern: get all records for a session
            $table->index(['asset_items_audit_sessions_id', 'tenant_id', 'deleted_at'], 'idx_audited_record_session');
            
            // Variable-based queries (reports by variable type)
            $table->index(['asset_audit_variable_id', 'tenant_id', 'deleted_at'], 'idx_audited_record_variable');
            
            // Multi-tenant isolation
            $table->index(['tenant_id', 'deleted_at', 'isactive'], 'idx_audited_record_tenant');
            
            // Prevent duplicate records for same session+variable combination
            $table->unique(
                ['asset_items_audit_sessions_id', 'asset_audit_variable_id', 'deleted_at'], 
                'uniq_session_variable_record'
            );
            
            // Score-based filtering (for analytics)
            $table->index(['score', 'tenant_id'], 'idx_audited_record_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_items_audited_record');
    }
};
