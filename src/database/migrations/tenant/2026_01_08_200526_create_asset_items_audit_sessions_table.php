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
        Schema::create('asset_items_audit_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_item_id');
            $table->string('audit_sessions_number', 100)->nullable(); 
            
            // ISO-compliant audit session fields
            $table->enum('audit_status', ['draft', 'in_progress', 'completed', 'approved', 'rejected'])
                  ->default('draft')
                  ->comment('Audit session workflow status');
            
            $table->jsonb('document')->nullable()->comment('Evidence documents, photos, attachments');
            $table->unsignedBigInteger('audit_by')->nullable()->comment('Primary auditor user ID');
            $table->string('auditor_name', 255)->nullable()->comment('Auditor name captured at audit time');
            
            // Location tracking (ISO 55001 requirement for asset location verification)
            $table->string('auditing_location_latitude', 50)->nullable();
            $table->string('auditing_location_longitude', 50)->nullable();
            $table->text('location_description')->nullable()->comment('Physical location description');
            
            // ISO 19011 audit documentation requirements
            $table->text('remarks')->nullable()->comment('Auditor remarks and observations');
            $table->boolean('follow_up_required')->default(false)->comment('Indicates if follow-up action needed');
            $table->text('follow_up_notes')->nullable()->comment('Details about required follow-up actions');
            $table->date('follow_up_due_date')->nullable()->comment('Target date for follow-up completion');
            
            // Audit timing (ISO requirement for audit duration tracking)
            $table->timestamp('audit_started_at')->nullable()->comment('When auditor started the audit');
            $table->timestamp('audit_completed_at')->nullable()->comment('When auditor completed the audit');
            $table->integer('audit_duration_minutes')->nullable()->comment('Calculated audit duration');
            
            // Approval workflow (ISO 19011 audit review requirement)
            $table->unsignedBigInteger('approved_by')->nullable()->comment('User who approved the audit');
            $table->timestamp('approved_at')->nullable()->comment('Approval timestamp');
            $table->text('approval_notes')->nullable()->comment('Approval or rejection notes');
            
            // Standard fields
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id');
            $table->timestamps();

            // Foreign keys
            $table->foreign('asset_item_id')->references('id')->on('asset_items')->onDelete('restrict');
            $table->foreign('audit_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');

            // Enterprise-level indexes for performance
            // Multi-tenant isolation - most critical for worldwide use
            $table->index(['tenant_id', 'deleted_at', 'isactive'], 'idx_audit_sessions_tenant_active');
            
            // Asset item lookups with tenant isolation
            $table->index(['asset_item_id', 'tenant_id', 'deleted_at'], 'idx_audit_sessions_asset_tenant');
            
            // Audit session number lookups (for search/retrieval)
            $table->index(['audit_sessions_number', 'tenant_id'], 'idx_audit_sessions_number');
            
            // Auditor queries
            $table->index(['audit_by', 'tenant_id', 'deleted_at'], 'idx_audit_sessions_auditor');
            
            // Status-based queries (workflow management)
            $table->index(['audit_status', 'tenant_id', 'deleted_at'], 'idx_audit_sessions_status');
            
            // Follow-up tracking
            $table->index(['follow_up_required', 'follow_up_due_date', 'tenant_id'], 'idx_audit_sessions_followup');
            
            // Approval workflow
            $table->index(['approved_by', 'approved_at', 'tenant_id'], 'idx_audit_sessions_approval');
            
            // Time-based queries (reporting, analytics)
            $table->index(['created_at', 'tenant_id'], 'idx_audit_sessions_created');
            $table->index(['audit_completed_at', 'tenant_id'], 'idx_audit_sessions_completed');
            
            // GIN index for JSONB document search
            $table->rawIndex('document', 'idx_audit_sessions_document_gin', 'gin');
            
            // Unique constraint for audit session numbers per tenant
            $table->unique(['audit_sessions_number', 'tenant_id', 'deleted_at'], 'uniq_audit_session_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_items_audit_sessions');
    }
};
