<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * ISO 19011:2018 – Per-variable audit scores
     * One row per audit variable per asset_items_audit_session.
     */
    public function up(): void
    {
        Schema::create('asset_items_audited_record', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('asset_item_audit_session_id')
                  ->comment('FK to asset_items_audit_sessions');
            $table->unsignedBigInteger('asset_audit_variable_id')
                  ->comment('FK to asset_audit_variable');

            $table->integer('score')
                  ->comment('Score 1-5: 1=Poor … 5=Excellent');
            $table->text('notes')->nullable()
                  ->comment('Auditor justification for this score');
            $table->jsonb('evidence')->nullable()
                  ->comment('Photos/documents for this variable');

            $table->unsignedBigInteger('scored_by')->nullable();
            $table->timestamp('scored_at')->nullable();

            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id');
            $table->timestamps();

            $table->foreign('asset_item_audit_session_id', 'fk_ar_session')
                  ->references('id')->on('asset_items_audit_sessions')->onDelete('cascade');
            $table->foreign('asset_audit_variable_id', 'fk_ar_variable')
                  ->references('id')->on('asset_audit_variable')->onDelete('restrict');
            $table->foreign('scored_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['tenant_id', 'deleted_at', 'isactive'],                               'idx_ar_tenant_active');
            $table->index(['asset_item_audit_session_id', 'tenant_id'],                          'idx_ar_session');
            $table->index(['asset_audit_variable_id', 'tenant_id'],                              'idx_ar_variable');
            $table->index(['scored_by', 'tenant_id'],                                            'idx_ar_scored_by');
            $table->index(['score', 'tenant_id'],                                                'idx_ar_score');
            $table->rawIndex('evidence', 'idx_ar_evidence_gin', 'gin');

            $table->unique(
                ['asset_item_audit_session_id', 'asset_audit_variable_id', 'deleted_at'],
                'uniq_ar_session_variable'
            );
        });

        DB::statement('
            ALTER TABLE asset_items_audited_record
            ADD CONSTRAINT chk_ar_score_range
            CHECK (score >= 1 AND score <= 5)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_items_audited_record');
    }
};
