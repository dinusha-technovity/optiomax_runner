<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('upgrade_asset_requisitions', function (Blueprint $table) {
            $table->id();

            // Asset reference
            $table->unsignedBigInteger('asset_id');
            $table->foreign('asset_id')->references('id')->on('asset_items')->onDelete('restrict');

            // Reason & Justification
            $table->text('reason')->nullable(); // User-selected or free-text reason
            $table->text('other_reason')->nullable(); // Free-text when "other" is selected
            $table->text('justification')->nullable(); // Detailed justification

            // Upgrade details
            $table->text('upgrade_description'); // Description of the upgrade
            $table->jsonb('expected_outcomes')->nullable(); // Stores outcome IDs only
            $table->text('expected_outcome_benefits')->nullable(); // Free text outcome benefits

            // Priority
            $table->unsignedBigInteger('priority')->nullable();
            $table->foreign('priority')->references('id')->on('work_order_priority_levels')->onDelete('set null');

            // Expected date
            $table->date('expected_date');

            // File attachments (stored as jsonb for metadata / file paths)
            $table->jsonb('error_logs_performance_doc')->nullable();
            $table->jsonb('screenshots')->nullable();

            // Workflow status
            $table->string('status', 50)->default('PENDING');

            // Notified maintenance leaders (stores user IDs as jsonb array)
            $table->jsonb('notified_maintenance_leaders')->nullable();

            // Work order link (nullable)
            $table->unsignedBigInteger('work_order_id')->nullable();

            // Upgrade requisition number (sequence-generated)
            $table->string('upgrade_requisition_number', 50)->unique();

            // Multi-tenant & soft-activation
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id');

            // Created by
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');

            // Timestamps
            $table->timestamps();
            $table->softDeletes();
        });

        // Add indexes for common query patterns
        DB::unprepared(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_upgrade_asset_req_tenant_id 
                ON upgrade_asset_requisitions (tenant_id) WHERE deleted_at IS NULL;

            CREATE INDEX IF NOT EXISTS idx_upgrade_asset_req_asset_id 
                ON upgrade_asset_requisitions (asset_id) WHERE deleted_at IS NULL;

            CREATE INDEX IF NOT EXISTS idx_upgrade_asset_req_created_by 
                ON upgrade_asset_requisitions (created_by) WHERE deleted_at IS NULL;

            CREATE INDEX IF NOT EXISTS idx_upgrade_asset_req_status 
                ON upgrade_asset_requisitions (status) WHERE deleted_at IS NULL;

            CREATE INDEX IF NOT EXISTS idx_upgrade_asset_req_tenant_status 
                ON upgrade_asset_requisitions (tenant_id, status) WHERE deleted_at IS NULL;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upgrade_asset_requisitions');
    }
};
