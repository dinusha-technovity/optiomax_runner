<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This table handles assignment of audit variables to either:
     * - assets (group/parent level)
     * - asset_items (individual item level)
     * 
     * Business Rule: If an audit variable is assigned at asset level,
     * it should NOT be re-assigned to child asset_items.
     */
    public function up(): void
    {
        Schema::create('asset_audit_variable_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_audit_variable_id');
            $table->unsignedBigInteger('assignable_type_id'); // FK to assignable_types table
            $table->unsignedBigInteger('assignable_id'); // ID of the asset or asset_item
            
            // Audit metadata
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            
            // Standard fields
            $table->unsignedBigInteger('tenant_id'); // Required for multi-tenant isolation
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Foreign keys
            $table->foreign('asset_audit_variable_id')
                  ->references('id')
                  ->on('asset_audit_variable')
                  ->onDelete('restrict');

            $table->foreign('assignable_type_id')
                  ->references('id')
                  ->on('assignable_types')
                  ->onDelete('restrict');
                  
            $table->foreign('assigned_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            // Unique constraint: prevent duplicate assignments
            // Same variable cannot be assigned to same entity twice
            $table->unique(
                ['asset_audit_variable_id', 'assignable_type_id', 'assignable_id', 'tenant_id'],
                'unique_audit_variable_assignment'
            );

            // Index for efficient querying
            $table->index(['assignable_type_id', 'assignable_id']);
            $table->index(['asset_audit_variable_id', 'is_active']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_audit_variable_assignments');
    }
};
