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
        Schema::table('asset_requisitions', function (Blueprint $table) {
            $table->unsignedBigInteger('asset_requisition_type_id')->nullable()->after('tenant_id');
            $table->boolean('is_transitioned')->default(false)->after('asset_requisition_type_id');

            $table->foreign('asset_requisition_type_id')
                ->references('id')
                ->on('asset_requisition_types')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_requisitions', function (Blueprint $table) {
            $table->dropForeign(['asset_requisition_type_id']);
            $table->dropColumn(['asset_requisition_type_id', 'is_transitioned']);
        });
    }
};
