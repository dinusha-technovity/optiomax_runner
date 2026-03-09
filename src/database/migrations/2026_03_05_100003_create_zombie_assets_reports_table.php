<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Main zombie assets reports table.
     * Depends on:
     *  - zombie_asset_reporter_types  (100001)
     *  - zombie_asset_conditions      (100002)
     *  - zombie_asset_value_ranges    (100003 — sorts before this file alphabetically)
     *  - asset_categories, asset_items, users  (earlier migrations)
     */ 
    public function up(): void
    {
        // Drop legacy table if it exists (was never in production use)
        Schema::dropIfExists('audit_session_zombie_assets');

        Schema::create('zombie_assets_reports', function (Blueprint $table) {
            $table->id();

            // Reporter
            $table->unsignedBigInteger('reported_by')
                  ->comment('FK to users — who reported this');
            $table->unsignedBigInteger('reporter_type_id')
                  ->comment('FK to zombie_asset_reporter_types');

            // Asset Identification Attempt
            $table->string('asset_description', 500)
                  ->comment('Free-text description of the unidentified asset');
            $table->unsignedBigInteger('estimated_category_id')->nullable()
                  ->comment('FK to asset_categories — best guess at asset category');
            $table->string('serial_number', 100)->nullable();
            $table->string('model_number', 100)->nullable();
            $table->string('brand', 100)->nullable();

            // Location Details (ISO 55001)
            $table->string('found_location_latitude', 50)->nullable();
            $table->string('found_location_longitude', 50)->nullable();
            $table->text('location_description')->nullable();
            $table->string('area_zone', 100)->nullable();

            // Condition & Value Assessment — FK to master tables (replaced enums)
            $table->unsignedBigInteger('estimated_condition_id')->nullable()
                  ->comment('FK to zombie_asset_conditions');
            $table->unsignedBigInteger('estimated_value_range_id')->nullable()
                  ->comment('FK to zombie_asset_value_ranges');

            // Evidence
            $table->jsonb('photos')->nullable();
            $table->text('auditor_notes')->nullable();

            // Resolution Workflow
            $table->enum('resolution_status', [
                'reported',
                'under_investigation',
                'identified',
                'duplicate',
                'disposed',
                'false_report',
                'resolved',
            ])->default('reported')
              ->comment('Workflow status — only "reported" allows edit/delete by original reporter');

            $table->text('resolution_notes')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('matched_asset_item_id')->nullable()
                  ->comment('FK to asset_items if the asset was successfully identified');

            // Follow-up
            $table->boolean('requires_action')->default(true);
            $table->date('action_due_date')->nullable();

            // Standard columns (required on all zombie process tables)
            $table->unsignedBigInteger('tenant_id');
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->timestamps();

            // Foreign Keys
            $table->foreign('reported_by', 'fk_zar_reporter')
                  ->references('id')->on('users')->onDelete('restrict');
            $table->foreign('reporter_type_id', 'fk_zar_reporter_type')
                  ->references('id')->on('zombie_asset_reporter_types')->onDelete('restrict');
            $table->foreign('estimated_category_id', 'fk_zar_estimated_category')
                  ->references('id')->on('asset_categories')->onDelete('set null');
            $table->foreign('estimated_condition_id', 'fk_zar_estimated_condition')
                  ->references('id')->on('zombie_asset_conditions')->onDelete('set null');
            $table->foreign('estimated_value_range_id', 'fk_zar_estimated_value_range')
                  ->references('id')->on('zombie_asset_value_ranges')->onDelete('set null');
            $table->foreign('resolved_by', 'fk_zar_resolver')
                  ->references('id')->on('users')->onDelete('set null');
            $table->foreign('matched_asset_item_id', 'fk_zar_matched_item')
                  ->references('id')->on('asset_items')->onDelete('set null');

            // Indexes
            $table->index(['reported_by', 'tenant_id', 'deleted_at'],           'idx_zar_reporter');
            $table->index(['reporter_type_id', 'tenant_id'],                    'idx_zar_reporter_type');
            $table->index(['resolution_status', 'tenant_id', 'deleted_at'],     'idx_zar_status');
            $table->index(['tenant_id', 'deleted_at', 'isactive'],              'idx_zar_tenant');
            $table->index(['action_due_date', 'resolution_status', 'tenant_id'],'idx_zar_followup');
            $table->index(['requires_action', 'resolution_status', 'tenant_id'],'idx_zar_action');
            $table->index(['estimated_category_id', 'tenant_id'],               'idx_zar_category');
            $table->index(['estimated_condition_id'],                            'idx_zar_condition');
            $table->index(['estimated_value_range_id'],                          'idx_zar_value_range');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zombie_assets_reports');
    }
};
