<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Financial Year Management for Reports and Audits
     */
    public function up(): void
    {
        Schema::create('financial_years', function (Blueprint $table) {
            $table->id();
            
            // Financial Year Details
            $table->string('year_name', 50); // e.g., FY2024, 2024-Q3
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_running_year')->default(false); // Only one can be true per tenant
            $table->enum('status', ['active', 'archived'])->default('active');
            
            // Audit Trail
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            
            // Multi-tenant & Soft Delete
            $table->unsignedBigInteger('tenant_id');
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            
            $table->timestamps();
            
            // Foreign Keys
            $table->foreign('created_by', 'fk_financial_years_created_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
            
            $table->foreign('updated_by', 'fk_financial_years_updated_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
            
            // Indexes for Performance Optimization
            $table->index(['tenant_id', 'isactive', 'deleted_at'], 'idx_financial_years_tenant_active');
            $table->index(['tenant_id', 'is_running_year', 'deleted_at'], 'idx_financial_years_running');
            $table->index(['tenant_id', 'status', 'deleted_at'], 'idx_financial_years_status');
            $table->index(['start_date', 'end_date'], 'idx_financial_years_period');
            $table->index(['deleted_at', 'isactive'], 'idx_financial_years_deleted_active');
            
            // Unique Constraints
            $table->unique(['year_name', 'tenant_id', 'deleted_at'], 'uq_financial_years_name_tenant');
            
            // Ensure only one running year per tenant (enforced at application level)
            // Note: PostgreSQL partial unique index can be added via raw SQL if needed:
            // CREATE UNIQUE INDEX uq_financial_years_running_tenant 
            // ON financial_years (tenant_id) 
            // WHERE is_running_year = true AND deleted_at IS NULL;
        });
        
        // Add partial unique index for running year (PostgreSQL specific)
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('
                CREATE UNIQUE INDEX uq_financial_years_running_tenant 
                ON financial_years (tenant_id) 
                WHERE is_running_year = true AND deleted_at IS NULL AND isactive = true
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop partial unique index if exists
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS uq_financial_years_running_tenant');
        }
        
        Schema::dropIfExists('financial_years');
    }
};
