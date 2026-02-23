<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * ISO 19011:2018 Compliant Audit Staff to Audit Group Assignments
     * Many-to-many relationship: Auditors can be assigned to multiple audit groups
     */
    public function up(): void
    {
        Schema::create('audit_staff_group_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('audit_staff_id');
            $table->unsignedBigInteger('audit_group_id');
            $table->unsignedBigInteger('tenant_id');
            
            // Multi-tenant & Soft Delete
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            
            $table->timestamps();
            
            // Foreign Keys
            $table->foreign('audit_staff_id', 'fk_asga_audit_staff')
                  ->references('id')
                  ->on('audit_staff')
                  ->onDelete('restrict');
            
            $table->foreign('audit_group_id', 'fk_asga_audit_group')
                  ->references('id')
                  ->on('audit_groups')
                  ->onDelete('restrict');
            
            // Indexes for Performance Optimization
            $table->index(['tenant_id', 'isactive', 'deleted_at'], 'idx_asga_tenant_active');
            $table->index(['audit_staff_id', 'tenant_id', 'deleted_at'], 'idx_asga_staff');
            $table->index(['audit_group_id', 'tenant_id', 'deleted_at'], 'idx_asga_group');
            $table->index(['audit_staff_id', 'audit_group_id'], 'idx_asga_staff_group');
            $table->index(['deleted_at', 'isactive'], 'idx_asga_deleted_active');
            
            // Unique Constraints - One auditor can only be assigned once per group
            $table->unique(
                ['audit_staff_id', 'audit_group_id', 'tenant_id', 'deleted_at'], 
                'uq_asga_staff_group_tenant'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_staff_group_assignments');
    }
};
