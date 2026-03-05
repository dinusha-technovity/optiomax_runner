<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Audit Sessions Auditors Pivot Table - Many-to-Many relationship
     * Tracks multiple auditors assigned to each audit session
     */
    public function up(): void
    {
        Schema::create('audit_sessions_auditors', function (Blueprint $table) {
            $table->id();
            
            // Relationship Keys
            $table->unsignedBigInteger('audit_session_id'); // FK to audit_sessions
            $table->unsignedBigInteger('user_id'); // FK to users (auditor)
            
            // Auditor Role within Session (ISO 19011:2018 Section 5.3)
            $table->enum('role', [
                'lead-auditor',      // Lead auditor (primary responsible)
                'auditor',           // Team member auditor
                'technical-expert',  // Subject matter expert
                'observer',          // Observer (training/verification)
                'audit-trainee'      // Auditor in training
            ])->default('auditor');
            
            // Assignment Details
            $table->timestamp('assigned_at')->nullable(); // When auditor was assigned
            $table->text('assignment_notes')->nullable(); // Special instructions or focus areas
            $table->unsignedBigInteger('assigned_by')->nullable(); // User who made the assignment
            
            // Auditor Availability & Status
            $table->enum('availability_status', [
                'assigned',          // Assigned and confirmed
                'tentative',         // Tentatively assigned, awaiting confirmation
                'declined',          // Auditor declined assignment
                'removed'            // Removed from session (maintain record)
            ])->default('assigned');
            
            // Competency & Qualification (ISO 19011:2018 Section 7)
            $table->jsonb('competencies')->nullable(); // Relevant competencies/certifications
            $table->text('qualification_notes')->nullable(); // Why this auditor was selected
            
            // Audit Contribution Tracking
            $table->integer('findings_contributed')->default(0); // Number of findings logged by this auditor
            $table->boolean('attended_opening_meeting')->default(false);
            $table->boolean('attended_closing_meeting')->default(false);
            
            // Multi-tenant Support
            $table->unsignedBigInteger('tenant_id'); // Required for data isolation
            
            // Soft Delete (maintain audit trail)
            $table->timestamp('deleted_at')->nullable();
            
            $table->timestamps();
            
            // Foreign Keys
            $table->foreign('audit_session_id', 'fk_audit_sessions_auditors_session')
                  ->references('id')
                  ->on('audit_sessions')
                  ->onDelete('cascade'); // Remove assignments if session is deleted
            
            $table->foreign('user_id', 'fk_audit_sessions_auditors_user')
                  ->references('id')
                  ->on('users')
                  ->onDelete('restrict'); // Prevent user deletion if assigned to sessions
            
            $table->foreign('assigned_by', 'fk_audit_sessions_auditors_assigned_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
            
            // Performance Optimization Indexes
            $table->index(['audit_session_id', 'deleted_at'], 'idx_audit_sessions_auditors_session');
            $table->index(['user_id', 'availability_status', 'deleted_at'], 'idx_audit_sessions_auditors_user');
            $table->index(['tenant_id', 'deleted_at'], 'idx_audit_sessions_auditors_tenant');
            $table->index(['role', 'audit_session_id'], 'idx_audit_sessions_auditors_role');
            $table->index(['availability_status', 'assigned_at'], 'idx_audit_sessions_auditors_status');
            
            // Unique Constraints: One auditor cannot have same role multiple times in same session
            $table->unique(
                ['audit_session_id', 'user_id', 'role', 'deleted_at'], 
                'uq_audit_sessions_auditors_session_user_role'
            );
        });
        
        // PostgreSQL-specific: Comments for documentation
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("
                COMMENT ON TABLE audit_sessions_auditors IS 'N:M pivot table for audit sessions and auditors with role and competency tracking per ISO 19011:2018'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_sessions_auditors.audit_session_id IS 'Foreign key to audit_sessions table'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_sessions_auditors.user_id IS 'Foreign key to users table (auditor)'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_sessions_auditors.role IS 'Auditor role per ISO 19011:2018 Section 5.3 (lead-auditor, auditor, technical-expert, observer, audit-trainee)'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_sessions_auditors.availability_status IS 'Auditor assignment status (assigned, tentative, declined, removed)'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_sessions_auditors.competencies IS 'JSONB array of auditor competencies and certifications per ISO 19011:2018 Section 7'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_sessions_auditors.findings_contributed IS 'Count of audit findings logged by this auditor'
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_sessions_auditors');
    }
};
