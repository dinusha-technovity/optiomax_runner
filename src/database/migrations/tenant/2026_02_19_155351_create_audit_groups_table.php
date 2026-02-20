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
        Schema::create('audit_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id');
            $table->timestamps();

            // Indexes for performance optimization
            $table->index(['tenant_id', 'isactive', 'deleted_at'], 'idx_audit_groups_tenant_active');
            $table->index(['name', 'tenant_id'], 'idx_audit_groups_name_tenant');
            $table->index('deleted_at', 'idx_audit_groups_deleted');
            
            // Unique constraint for name per tenant (excluding soft deletes)
            $table->unique(['name', 'tenant_id', 'deleted_at'], 'uq_audit_groups_name_tenant');
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_groups');
    }
};
