<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Audit Sessions Groups Pivot Table - Many-to-Many relationship
     * Tracks multiple audit groups assigned to each audit session
     */
    public function up(): void
    {
        Schema::create('audit_sessions_groups', function (Blueprint $table) {
            $table->id();
            
            // Relationship Keys
            $table->unsignedBigInteger('audit_session_id'); // FK to audit_sessions
            $table->unsignedBigInteger('audit_group_id'); // FK to audit_groups
            
            // Assignment Details
            $table->timestamp('assigned_at')->nullable(); // When group was assigned to session
            $table->unsignedBigInteger('assigned_by')->nullable(); // User who made the assignment
            $table->text('assignment_notes')->nullable(); // Why this group was selected, specific focus
            
            // Audit Progress Tracking for this Group
            $table->enum('audit_status', [
                'pending',           // Not yet started
                'in-progress',       // Currently under audit
                'completed',         // Audit completed for this group
                'on-hold',          // Temporarily paused
                'deferred'          // Moved to another session
            ])->default('pending');
            
            // Group-specific Findings
            $table->integer('findings_count')->default(0); // Findings specific to this group
            $table->integer('critical_findings')->default(0);
            $table->integer('major_findings')->default(0);
            $table->integer('minor_findings')->default(0);
            $table->integer('observations')->default(0);
            
            // Audit Coverage (Percentage of assets audited within this group)
            $table->decimal('coverage_percentage', 5, 2)->default(0.00); // 0.00 to 100.00
            $table->integer('total_assets_in_group')->default(0); // Snapshot at assignment
            $table->integer('assets_audited')->default(0); // Number of assets actually audited
            
            // Audit Timeline for this Group
            $table->date('scheduled_audit_date')->nullable(); // Planned audit date for this group
            $table->date('actual_audit_date')->nullable(); // When audit was actually performed
            $table->text('audit_notes')->nullable(); // Group-specific audit notes
            
            // Priority & Sequencing
            $table->integer('audit_sequence')->nullable(); // Order in which groups should be audited (1, 2, 3...)
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            
            // Multi-tenant Support
            $table->unsignedBigInteger('tenant_id'); // Required for data isolation
            
            // Soft Delete (maintain audit trail)
            $table->timestamp('deleted_at')->nullable();
            
            $table->timestamps();
            
            // Foreign Keys
            $table->foreign('audit_session_id', 'fk_audit_sessions_groups_session')
                  ->references('id')
                  ->on('audit_sessions')
                  ->onDelete('cascade'); // Remove assignments if session is deleted
            
            $table->foreign('audit_group_id', 'fk_audit_sessions_groups_group')
                  ->references('id')
                  ->on('audit_groups')
                  ->onDelete('restrict'); // Prevent group deletion if assigned to active sessions
            
            $table->foreign('assigned_by', 'fk_audit_sessions_groups_assigned_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
            
            // Performance Optimization Indexes
            $table->index(['audit_session_id', 'deleted_at'], 'idx_audit_sessions_groups_session');
            $table->index(['audit_group_id', 'audit_status', 'deleted_at'], 'idx_audit_sessions_groups_group');
            $table->index(['tenant_id', 'deleted_at'], 'idx_audit_sessions_groups_tenant');
            $table->index(['audit_status', 'priority'], 'idx_audit_sessions_groups_status_priority');
            $table->index(['audit_session_id', 'audit_sequence'], 'idx_audit_sessions_groups_sequence');
            $table->index(['scheduled_audit_date', 'audit_status'], 'idx_audit_sessions_groups_schedule');
            
            // Unique Constraints: Each group can only be assigned once per session
            $table->unique(
                ['audit_session_id', 'audit_group_id', 'deleted_at'], 
                'uq_audit_sessions_groups_session_group'
            );
        });
        
        // PostgreSQL-specific: Check constraints and comments
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            // Findings counts must be non-negative
            DB::statement('
                ALTER TABLE audit_sessions_groups 
                ADD CONSTRAINT chk_audit_sessions_groups_findings_positive 
                CHECK (
                    findings_count >= 0 AND 
                    critical_findings >= 0 AND 
                    major_findings >= 0 AND 
                    minor_findings >= 0 AND 
                    observations >= 0
                )
            ');
            
            // Coverage percentage must be between 0 and 100
            DB::statement('
                ALTER TABLE audit_sessions_groups 
                ADD CONSTRAINT chk_audit_sessions_groups_coverage_range 
                CHECK (coverage_percentage >= 0 AND coverage_percentage <= 100)
            ');
            
            // Assets audited cannot exceed total assets in group
            DB::statement('
                ALTER TABLE audit_sessions_groups 
                ADD CONSTRAINT chk_audit_sessions_groups_assets_count 
                CHECK (assets_audited <= total_assets_in_group)
            ');
            
            // Actual audit date must be on or after scheduled date
            DB::statement('
                ALTER TABLE audit_sessions_groups 
                ADD CONSTRAINT chk_audit_sessions_groups_date_logic 
                CHECK (
                    actual_audit_date IS NULL OR 
                    scheduled_audit_date IS NULL OR 
                    actual_audit_date >= scheduled_audit_date
                )
            ');
            
            // Table documentation
            DB::statement("
                COMMENT ON TABLE audit_sessions_groups IS 'N:M pivot table for audit sessions and audit groups with progress tracking and findings per group'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_sessions_groups.audit_session_id IS 'Foreign key to audit_sessions table'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_sessions_groups.audit_group_id IS 'Foreign key to audit_groups table'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_sessions_groups.audit_status IS 'Status of audit for this specific group (pending, in-progress, completed, on-hold, deferred)'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_sessions_groups.coverage_percentage IS 'Percentage of assets audited within this group (0.00 to 100.00)'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_sessions_groups.audit_sequence IS 'Order in which groups should be audited within the session (for planning)'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_sessions_groups.priority IS 'Audit priority for this group (low, medium, high, critical)'
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_sessions_groups');
    }
};
