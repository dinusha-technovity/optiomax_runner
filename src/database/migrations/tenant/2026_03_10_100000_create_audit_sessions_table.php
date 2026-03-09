<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Audit Session Management - Tracks individual audit sessions within audit periods
     * Implements ISO 19011:2018 standards for audit management
     */
    public function up(): void
    {
        // Drop existing objects with CASCADE to remove dependent FK constraints and indexes
        DB::statement('DROP TABLE IF EXISTS audit_sessions CASCADE');
        DB::statement('DROP SEQUENCE IF EXISTS audit_session_code_seq CASCADE');

        Schema::create('audit_sessions', function (Blueprint $table) {
            $table->id();
            
            // Basic Information
            $table->string('session_code', 50)->unique(); // e.g., "AS-2024-001", unique globally
            $table->string('session_name', 200); // e.g., "Warehouse A Inventory Audit"
            $table->text('description')->nullable(); // Audit scope, objectives, and criteria
            
            // Period Association (Required)
            $table->unsignedBigInteger('audit_period_id'); // FK to audit_periods
            
            // Session Scheduling & Timeline
            $table->date('scheduled_date')->nullable(); // Planned audit date (ISO 8601)
            $table->date('actual_start_date')->nullable(); // Actual start (ISO 8601)
            $table->date('actual_end_date')->nullable(); // Actual completion (ISO 8601)
            
            // Lead Auditor (Required)
            $table->unsignedBigInteger('lead_auditor_id'); // Primary responsible auditor
            
            // Session Status Management (ISO 19011:2018 Workflow)
            $table->enum('status', [
                'draft',           // Initial planning stage
                'scheduled',       // Date confirmed, auditors assigned
                'in-progress',     // Audit actively underway
                'under-review',    // Audit completed, findings under review
                'completed',       // All findings documented and approved
                'cancelled'        // Session cancelled (maintain record)
            ])->default('draft');
            
            // ISO 19011:2018 Audit Planning Fields
            $table->jsonb('audit_objectives')->nullable(); // Array of objectives
            $table->jsonb('audit_scope')->nullable(); // Scope definition (areas, processes)
            $table->jsonb('audit_criteria')->nullable(); // Standards and requirements
            $table->text('audit_methodology')->nullable(); // Audit approach and methods
            
            // Risk Assessment (ISO 19011:2018 Section 5.4)
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->nullable();
            $table->jsonb('risk_factors')->nullable(); // Identified risks
            
            // Audit Findings & Results
            $table->integer('findings_count')->default(0); // Total findings
            $table->integer('critical_findings')->default(0); // Critical severity
            $table->integer('major_findings')->default(0); // Major severity
            $table->integer('minor_findings')->default(0); // Minor severity
            $table->integer('observations')->default(0); // Non-conformities
            
            // Zombie/Unidentified Asset Tracking (ISO 55001 Asset Verification)
            $table->integer('reported_zombie_assets_count')->default(0)
                  ->comment('Count of unidentified/unexpected assets found during audit');
            $table->jsonb('zombie_assets_summary')->nullable()
                  ->comment('Summary of zombie assets by location/category');
            
            // Audit Report & Documentation
            $table->date('report_issued_at')->nullable(); // Report finalization date
            $table->text('audit_conclusion')->nullable(); // Overall audit conclusion
            $table->jsonb('recommendations')->nullable(); // Corrective actions
            
            // Follow-up Management
            $table->boolean('requires_followup')->default(false);
            $table->date('followup_due_date')->nullable();
            
            // Meeting Records (ISO 19011:2018 Section 6.4)
            $table->timestamp('opening_meeting_at')->nullable();
            $table->timestamp('closing_meeting_at')->nullable();
            $table->text('meeting_notes')->nullable();
            
            // Audit Trail
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            
            // Multi-tenant & Soft Delete (Required)
            $table->unsignedBigInteger('tenant_id'); // Required
            $table->timestamp('deleted_at')->nullable(); // Required: Soft delete
            $table->boolean('isactive')->default(true); // Required: Active flag
            
            $table->timestamps();
            
            // Foreign Keys
            $table->foreign('audit_period_id', 'fk_audit_sessions_period')
                  ->references('id')
                  ->on('audit_periods')
                  ->onDelete('restrict');
            
            $table->foreign('lead_auditor_id', 'fk_audit_sessions_lead_auditor')
                  ->references('id')
                  ->on('users')
                  ->onDelete('restrict');
            
            $table->foreign('created_by', 'fk_audit_sessions_created_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
            
            $table->foreign('updated_by', 'fk_audit_sessions_updated_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            // Unique Constraints (inside create — always safe on a fresh table)
            $table->unique(['session_name', 'audit_period_id', 'tenant_id', 'deleted_at'], 'uq_audit_sessions_name_period_tenant');
        });

        // -----------------------------------------------------------------------
        // Indexes — created with IF NOT EXISTS outside Schema::create so they
        // are safe to re-run even if orphaned names exist in the schema.
        // -----------------------------------------------------------------------
        DB::statement('CREATE INDEX IF NOT EXISTS idx_audit_sessions_tenant_active   ON audit_sessions (tenant_id, isactive, deleted_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_audit_sessions_tenant_period   ON audit_sessions (tenant_id, audit_period_id, deleted_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_audit_sessions_status          ON audit_sessions (tenant_id, status, deleted_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_audit_sessions_lead_auditor    ON audit_sessions (lead_auditor_id, status, deleted_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_audit_sessions_schedule        ON audit_sessions (scheduled_date, status)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_audit_sessions_period_status   ON audit_sessions (audit_period_id, status, deleted_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_audit_sessions_deleted_active  ON audit_sessions (deleted_at, isactive)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_audit_sessions_code            ON audit_sessions (session_code)');
        
        // PostgreSQL-specific: Check constraints and comments
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            // Date validation: actual_end_date must be >= actual_start_date
            DB::statement('
                ALTER TABLE audit_sessions 
                ADD CONSTRAINT chk_audit_sessions_date_range 
                CHECK (
                    actual_start_date IS NULL OR 
                    actual_end_date IS NULL OR 
                    actual_end_date >= actual_start_date
                )
            ');
            
            // Note: Scheduled date validation against audit period dates 
            // is handled at the application/function level
            
            // Findings counts must be non-negative
            DB::statement('
                ALTER TABLE audit_sessions 
                ADD CONSTRAINT chk_audit_sessions_findings_positive 
                CHECK (
                    findings_count >= 0 AND 
                    critical_findings >= 0 AND 
                    major_findings >= 0 AND 
                    minor_findings >= 0 AND 
                    observations >= 0
                )
            ');
            
            // Table documentation
            DB::statement("
                COMMENT ON TABLE audit_sessions IS 'ISO 19011:2018 compliant audit session management with period association and multi-auditor support'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_sessions.session_code IS 'Globally unique audit session identifier (e.g., AS-2024-001)'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_sessions.session_name IS 'Descriptive audit session name (max 200 characters)'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_sessions.audit_period_id IS 'Foreign key reference to parent audit period (required)'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_sessions.status IS 'ISO 19011:2018 audit workflow status (draft → scheduled → in-progress → under-review → completed/cancelled)'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_sessions.audit_objectives IS 'JSONB array of audit objectives per ISO 19011:2018 Section 5.5.2'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_sessions.audit_scope IS 'JSONB definition of audit scope (areas, processes, standards)'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_sessions.audit_criteria IS 'JSONB audit criteria (policies, procedures, standards)'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_sessions.risk_level IS 'Risk-based audit planning per ISO 19011:2018 Section 5.4'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_sessions.findings_count IS 'Total number of audit findings (sum of all severity levels)'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_sessions.opening_meeting_at IS 'ISO 19011:2018 Section 6.4.7 - Opening meeting timestamp'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_sessions.closing_meeting_at IS 'ISO 19011:2018 Section 6.4.13 - Closing meeting timestamp'
            ");
        }
        
        // Create sequence for auto-generating session codes
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('
                CREATE SEQUENCE IF NOT EXISTS audit_session_code_seq
                START WITH 1
                INCREMENT BY 1
                NO MINVALUE
                NO MAXVALUE
                CACHE 1
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop sequence first
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP SEQUENCE IF EXISTS audit_session_code_seq CASCADE');
        }
        
        Schema::dropIfExists('audit_sessions');
    }
};