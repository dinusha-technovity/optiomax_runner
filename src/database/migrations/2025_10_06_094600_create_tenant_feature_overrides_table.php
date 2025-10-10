<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_feature_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('package_feature_id');
            $table->text('override_value'); // The overridden value
            $table->decimal('override_cost_monthly', 10, 2)->default(0); // Additional monthly cost
            $table->decimal('override_cost_yearly', 10, 2)->default(0); // Additional yearly cost
            $table->text('reason')->nullable(); // Business reason for override
            $table->enum('override_type', ['increase', 'decrease', 'custom'])->default('custom');
            
            // Approval and tracking
            $table->enum('approval_status', ['pending', 'approved', 'rejected', 'auto_approved'])->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            
            // Effective dates
            $table->timestamp('effective_from')->default(now());
            $table->timestamp('effective_until')->nullable();
            $table->boolean('is_permanent')->default(false);
            
            // Compliance tracking
            $table->json('compliance_checks')->nullable(); // Record of compliance validations
            $table->boolean('requires_legal_review')->default(false);
            
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('package_feature_id')->references('id')->on('package_features')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['tenant_id', 'effective_from', 'effective_until']);
            $table->index(['approval_status', 'requires_legal_review']);
        });
        
        // Convert JSON column to JSONB
        DB::statement('ALTER TABLE tenant_feature_overrides ALTER COLUMN compliance_checks TYPE jsonb USING compliance_checks::jsonb');
        DB::statement('CREATE INDEX tenant_feature_overrides_compliance_checks_gin_index ON tenant_feature_overrides USING GIN (compliance_checks)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_feature_overrides');
    }
};
