<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * ISO 19011:2018 Compliant Audit Staff Management
     */
    public function up(): void
    {
        Schema::create('audit_staff', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('tenant_id');
            
            // ISO 19011 Compliance Fields
            $table->string('auditor_code', 50);
            
            // Administrative
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            
            // Multi-tenant & Soft Delete
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            
            $table->timestamps();
            
            // Foreign Keys
            $table->foreign('user_id', 'fk_audit_staff_user')
                  ->references('id')
                  ->on('users')
                  ->onDelete('restrict');
            
            $table->foreign('assigned_by', 'fk_audit_staff_assigned_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
            
            // Indexes for Performance Optimization
            $table->index(['tenant_id', 'isactive', 'deleted_at'], 'idx_audit_staff_tenant_active');
            $table->index(['user_id', 'tenant_id', 'deleted_at'], 'idx_audit_staff_user_tenant');
            $table->index('auditor_code', 'idx_audit_staff_code');
            $table->index(['deleted_at', 'isactive'], 'idx_audit_staff_deleted_active');
            
            // Unique Constraints
            $table->unique(['user_id', 'tenant_id', 'deleted_at'], 'uq_audit_staff_user_tenant');
            $table->unique(['auditor_code', 'tenant_id', 'deleted_at'], 'uq_audit_staff_code_tenant');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_staff');
    }
};
