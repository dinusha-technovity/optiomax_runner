<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Add support for direct asset transfer logs
     */
    public function up(): void
    {
        Schema::table('asset_transfer_logs', function (Blueprint $table) {
            // Add column for direct asset transfer request items
            $table->unsignedBigInteger('direct_transfer_request_item_id')->nullable()->after('transfer_request_item_id');
            
            // Make requisition-based columns nullable since they won't apply to direct transfers
            $table->unsignedBigInteger('internal_requisition_id')->nullable()->change();
            $table->unsignedBigInteger('internal_requisition_item_id')->nullable()->change();
            $table->unsignedBigInteger('transfer_request_item_id')->nullable()->change();
            
            // Add foreign key for direct transfer items
            $table->foreign('direct_transfer_request_item_id', 'fk_asset_transfer_logs_direct_item')
                  ->references('id')
                  ->on('direct_asset_transfer_request_items')
                  ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_transfer_logs', function (Blueprint $table) {
            $table->dropForeign('fk_asset_transfer_logs_direct_item');
            $table->dropColumn('direct_transfer_request_item_id');
            
            // Restore NOT NULL constraints (Laravel doesn't support this directly, use raw SQL)
        });
        
        // Restore NOT NULL constraints using raw SQL
        DB::statement('ALTER TABLE asset_transfer_logs ALTER COLUMN transfer_request_item_id SET NOT NULL');
        DB::statement('ALTER TABLE asset_transfer_logs ALTER COLUMN internal_requisition_id SET NOT NULL');
        DB::statement('ALTER TABLE asset_transfer_logs ALTER COLUMN internal_requisition_item_id SET NOT NULL');
    }
};
