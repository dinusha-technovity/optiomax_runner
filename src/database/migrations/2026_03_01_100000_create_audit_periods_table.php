<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Audit Period Management - Tracks audit cycles with financial year association
     */
    public function up(): void
    {
        Schema::create('audit_periods', function (Blueprint $table) {
            $table->id();
            
            // Basic Information
            $table->string('period_name', 100); // e.g., "2024-Q3 Inventory Audit"
            $table->text('description')->nullable(); // Scope and objectives
            
            // Period Duration
            $table->unsignedBigInteger('financial_year_id'); // Required: Link to financial year
            $table->date('start_date'); // ISO 8601: YYYY-MM-DD
            $table->date('end_date'); // ISO 8601: YYYY-MM-DD
            
            // Period Leader (Responsible Person)
            $table->unsignedBigInteger('period_leader_id'); // Required: Audit oversight
            
            // Status Management
            $table->enum('status', ['active', 'in-progress', 'completed', 'archived'])->default('active');
            
            // Audit Trail
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            
            // Multi-tenant & Soft Delete (Required)
            $table->unsignedBigInteger('tenant_id'); // Required
            $table->timestamp('deleted_at')->nullable(); // Required: Soft delete
            $table->boolean('isactive')->default(true); // Required: Active flag
            
            $table->timestamps();
            
            // Foreign Keys
            $table->foreign('financial_year_id', 'fk_audit_periods_financial_year')
                  ->references('id')
                  ->on('financial_years')
                  ->onDelete('restrict'); // Prevent deletion of financial year if audit periods exist
            
            $table->foreign('period_leader_id', 'fk_audit_periods_leader')
                  ->references('id')
                  ->on('users')
                  ->onDelete('restrict'); // Require leader reassignment before deletion
            
            $table->foreign('created_by', 'fk_audit_periods_created_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
            
            $table->foreign('updated_by', 'fk_audit_periods_updated_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
            
            // Performance Optimization Indexes
            $table->index(['tenant_id', 'isactive', 'deleted_at'], 'idx_audit_periods_tenant_active');
            $table->index(['tenant_id', 'financial_year_id', 'deleted_at'], 'idx_audit_periods_tenant_year');
            $table->index(['tenant_id', 'status', 'deleted_at'], 'idx_audit_periods_status');
            $table->index(['period_leader_id', 'status', 'deleted_at'], 'idx_audit_periods_leader');
            $table->index(['start_date', 'end_date'], 'idx_audit_periods_date_range');
            $table->index(['financial_year_id', 'status'], 'idx_audit_periods_year_status');
            $table->index(['deleted_at', 'isactive'], 'idx_audit_periods_deleted_active');
            
            // Unique Constraints
            $table->unique(['period_name', 'tenant_id', 'deleted_at'], 'uq_audit_periods_name_tenant');
        });
        
        // PostgreSQL-specific: Check constraint for date validity
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('
                ALTER TABLE audit_periods 
                ADD CONSTRAINT chk_audit_periods_date_range 
                CHECK (end_date >= start_date)
            ');
            
            // Add comment for table documentation
            DB::statement("
                COMMENT ON TABLE audit_periods IS 'Audit period management with financial year association and period leader assignment'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_periods.period_name IS 'Unique audit period identifier (e.g., 2024-Q3 Inventory Audit)'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_periods.description IS 'Audit scope and objectives (max 500 characters recommended)'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_periods.start_date IS 'Audit period start date (ISO 8601: YYYY-MM-DD)'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_periods.end_date IS 'Audit period end date (ISO 8601: YYYY-MM-DD)'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_periods.financial_year_id IS 'Foreign key to financial_years table (required)'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_periods.period_leader_id IS 'Foreign key to users table - responsible person (required)'
            ");
            
            DB::statement("
                COMMENT ON COLUMN audit_periods.status IS 'Audit period status: active (planned), in-progress (ongoing), completed (finished), archived (historical)'
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop check constraint if exists
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE audit_periods DROP CONSTRAINT IF EXISTS chk_audit_periods_date_range');
        }
        
        Schema::dropIfExists('audit_periods');
    }
};
