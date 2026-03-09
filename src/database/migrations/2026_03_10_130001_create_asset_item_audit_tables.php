<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ISO 19011:2018 – Asset Item Audit Sessions table
     * One row per asset per ISO audit session.
     */
    public function up(): void
    {
        Schema::create('asset_items_audit_sessions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('audit_period_id')
                  ->comment('FK to audit_periods – the audit period this record belongs to');
            $table->unsignedBigInteger('audit_session_id')
                  ->comment('FK to audit_sessions – ISO audit session this record belongs to');
            $table->unsignedBigInteger('asset_item_id')
                  ->comment('FK to asset_items – asset being audited');

            $table->boolean('asset_available')->nullable()
                  ->comment('NULL=not checked, true=available, false=not available');
            $table->text('availability_notes')->nullable();
            $table->timestamp('availability_checked_at')->nullable();

            $table->enum('audit_status', ['start', 'in_progress', 'cancel', 'complete'])
                  ->default('start')
                  ->comment('start -> in_progress -> complete / cancel');

            $table->jsonb('document')->nullable();
            $table->unsignedBigInteger('audit_by')->nullable();

            $table->string('auditing_location_latitude', 50)->nullable();
            $table->string('auditing_location_longitude', 50)->nullable();
            $table->text('location_description')->nullable();

            $table->text('remarks')->nullable();
            $table->boolean('follow_up_required')->default(false);
            $table->text('follow_up_notes')->nullable();
            $table->date('follow_up_due_date')->nullable();

            $table->timestamp('audit_started_at')->nullable();
            $table->timestamp('audit_completed_at')->nullable();
            $table->integer('audit_duration_minutes')->nullable();

            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id');
            $table->timestamps();

            $table->foreign('audit_period_id', 'fk_ais_audit_period')
                  ->references('id')->on('audit_periods')->onDelete('restrict');
            $table->foreign('audit_session_id', 'fk_ais_audit_session')
                  ->references('id')->on('audit_sessions')->onDelete('restrict');
            $table->foreign('asset_item_id', 'fk_ais_asset_item')
                  ->references('id')->on('asset_items')->onDelete('restrict');
            $table->foreign('audit_by', 'fk_ais_audit_by')
                  ->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_by', 'fk_ais_approved_by')
                  ->references('id')->on('users')->onDelete('set null');

            $table->index(['tenant_id', 'deleted_at', 'isactive'],                   'idx_ais_tenant_active');
            $table->index(['asset_item_id', 'tenant_id', 'deleted_at'],              'idx_ais_asset_tenant');
            $table->index(['audit_session_id', 'tenant_id', 'deleted_at'],           'idx_ais_session');
            $table->index(['audit_period_id', 'tenant_id', 'deleted_at'],            'idx_ais_period');
            $table->index(['audit_by', 'tenant_id', 'deleted_at'],                   'idx_ais_audit_by');
            $table->index(['audit_status', 'tenant_id', 'deleted_at'],               'idx_ais_status');
            $table->index(['asset_available', 'audit_session_id'],                   'idx_ais_availability');
            $table->index(['follow_up_required', 'follow_up_due_date', 'tenant_id'], 'idx_ais_followup');
            $table->index(['approved_by', 'approved_at', 'tenant_id'],               'idx_ais_approval');
            $table->index(['created_at', 'tenant_id'],                               'idx_ais_created');
            $table->index(['audit_completed_at', 'tenant_id'],                       'idx_ais_completed');
            $table->rawIndex('document', 'idx_ais_document_gin', 'gin');

            $table->unique(['audit_session_id', 'asset_item_id', 'deleted_at'], 'uniq_ais_session_asset');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_items_audit_sessions');
    }
};
