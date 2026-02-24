<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asset_requisition_type_transition', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('asset_requisition_id');
            $table->unsignedBigInteger('from_type');
            $table->unsignedBigInteger('to_type');
            $table->unsignedBigInteger('asset_requisition_action_id')->nullable();
            $table->unsignedBigInteger('transitioned_by')->nullable();
            $table->unsignedBigInteger('transitioned_initiated_by')->nullable();
            $table->string('status', 50)->default('PENDING');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('asset_requisition_id', 'artt_asset_req_fk')->references('id')->on('asset_requisitions')->onDelete('cascade');
            $table->foreign('from_type', 'artt_from_type_fk')->references('id')->on('asset_requisition_types')->onDelete('restrict');
            $table->foreign('to_type', 'artt_to_type_fk')->references('id')->on('asset_requisition_types')->onDelete('restrict');
            $table->foreign('asset_requisition_action_id', 'artt_action_fk')->references('id')->on('asset_requisition_actions')->onDelete('set null');
            $table->foreign('transitioned_by', 'artt_user_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('transitioned_initiated_by', 'artt_initiated_user_fk')->references('id')->on('users')->onDelete('set null');
        });

        DB::statement('CREATE INDEX idx_artt_tenant_req ON asset_requisition_type_transition (tenant_id, asset_requisition_id)');
        DB::statement('CREATE INDEX idx_artt_from_to ON asset_requisition_type_transition (tenant_id, from_type, to_type)');

        DB::unprepared(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_artt_tenant_active
                ON asset_requisition_type_transition (tenant_id)
                WHERE deleted_at IS NULL AND is_active = true;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_requisition_type_transition');
    }
};
