<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Enterprise optimization for audit variable tables
     */
    public function up(): void
    {
        // Optimize asset_audit_variable_type table
        Schema::table('asset_audit_variable_type', function (Blueprint $table) {
            // Multi-tenant queries
            $table->index(['tenant_id', 'deleted_at', 'is_active'], 'idx_audit_var_type_tenant');
            
            // Name-based searches
            $table->index(['name', 'tenant_id', 'deleted_at'], 'idx_audit_var_type_name');
            
            // Active types only queries (most common)
            $table->index(['is_active', 'deleted_at', 'tenant_id'], 'idx_audit_var_type_active');
        });

        // Optimize asset_audit_variable table
        Schema::table('asset_audit_variable', function (Blueprint $table) {
            // Multi-tenant isolation
            $table->index(['tenant_id', 'deleted_at', 'is_active'], 'idx_audit_var_tenant');
            
            // Type-based queries (get all variables of a type)
            $table->index(['asset_audit_variable_type_id', 'tenant_id', 'deleted_at'], 'idx_audit_var_type');
            
            // Name searches
            $table->index(['name', 'tenant_id', 'deleted_at'], 'idx_audit_var_name');
            
            // Active variables (filtering)
            $table->index(['is_active', 'tenant_id', 'deleted_at'], 'idx_audit_var_active');
            
            // Created date for sorting/filtering
            $table->index(['created_at', 'tenant_id'], 'idx_audit_var_created');
        });

        // Optimize asset_audit_variable_assignments table (already has indexes but add more)
        Schema::table('asset_audit_variable_assignments', function (Blueprint $table) {
            // Time-based queries for audit trail
            $table->index(['assigned_at', 'tenant_id'], 'idx_audit_assignment_time');
            
            // Assigned by user queries
            $table->index(['assigned_by', 'tenant_id', 'deleted_at'], 'idx_audit_assignment_user');
            
            // Active assignments only
            $table->index(['is_active', 'tenant_id', 'deleted_at'], 'idx_audit_assignment_active');
        });

        // Add table comments for documentation
        DB::statement("COMMENT ON TABLE asset_audit_variable_type IS 'Defines types/categories of audit variables (e.g., Physical Condition, Safety Compliance)'");
        DB::statement("COMMENT ON TABLE asset_audit_variable IS 'Individual audit variables/checkpoints that can be assigned to assets'");
        DB::statement("COMMENT ON TABLE asset_audit_variable_assignments IS 'Links audit variables to specific assets or asset items with inheritance support'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_audit_variable_type', function (Blueprint $table) {
            $table->dropIndex('idx_audit_var_type_tenant');
            $table->dropIndex('idx_audit_var_type_name');
            $table->dropIndex('idx_audit_var_type_active');
        });

        Schema::table('asset_audit_variable', function (Blueprint $table) {
            $table->dropIndex('idx_audit_var_tenant');
            $table->dropIndex('idx_audit_var_type');
            $table->dropIndex('idx_audit_var_name');
            $table->dropIndex('idx_audit_var_active');
            $table->dropIndex('idx_audit_var_created');
        });

        Schema::table('asset_audit_variable_assignments', function (Blueprint $table) {
            $table->dropIndex('idx_audit_assignment_time');
            $table->dropIndex('idx_audit_assignment_user');
            $table->dropIndex('idx_audit_assignment_active');
        });
    }
};
