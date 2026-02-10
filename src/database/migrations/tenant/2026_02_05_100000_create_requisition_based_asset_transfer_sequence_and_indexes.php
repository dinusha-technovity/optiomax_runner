<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- Create sequence for requisition based asset transfer request IDs
        CREATE SEQUENCE IF NOT EXISTS requisition_based_asset_transfer_id_seq START 1;

        -- Add indexes to requisition_based_asset_transfer_requwest table
        CREATE INDEX IF NOT EXISTS idx_req_asset_transfer_tenant_id ON requisition_based_asset_transfer_requwest(tenant_id) WHERE deleted_at IS NULL;
        CREATE INDEX IF NOT EXISTS idx_req_asset_transfer_requisition_by ON requisition_based_asset_transfer_requwest(requisition_by) WHERE deleted_at IS NULL;
        CREATE INDEX IF NOT EXISTS idx_req_asset_transfer_based_requisition ON requisition_based_asset_transfer_requwest(based_asset_requisition) WHERE deleted_at IS NULL;
        CREATE INDEX IF NOT EXISTS idx_req_asset_transfer_status ON requisition_based_asset_transfer_requwest(requisition_status) WHERE deleted_at IS NULL;
        CREATE INDEX IF NOT EXISTS idx_req_asset_transfer_requested_date ON requisition_based_asset_transfer_requwest(requested_date) WHERE deleted_at IS NULL;
        CREATE INDEX IF NOT EXISTS idx_req_asset_transfer_is_cancelled ON requisition_based_asset_transfer_requwest(is_cancelled) WHERE deleted_at IS NULL;
        CREATE INDEX IF NOT EXISTS idx_req_asset_transfer_composite ON requisition_based_asset_transfer_requwest(tenant_id, requisition_by, requisition_status, isactive) WHERE deleted_at IS NULL;
        CREATE INDEX IF NOT EXISTS idx_req_asset_transfer_requisition_id ON requisition_based_asset_transfer_requwest(requisition_id) WHERE deleted_at IS NULL;

        -- Add indexes to requisition_based_asset_transfer_requwest_items table
        CREATE INDEX IF NOT EXISTS idx_req_asset_transfer_items_transfer_id ON requisition_based_asset_transfer_requwest_items(requisition_based_asset_transfer_requwest) WHERE deleted_at IS NULL;
        CREATE INDEX IF NOT EXISTS idx_req_asset_transfer_items_asset_item_id ON requisition_based_asset_transfer_requwest_items(asset_item_id) WHERE deleted_at IS NULL;
        CREATE INDEX IF NOT EXISTS idx_req_asset_transfer_items_based_req_items ON requisition_based_asset_transfer_requwest_items(based_internal_asset_requisitions_items) WHERE deleted_at IS NULL;
        CREATE INDEX IF NOT EXISTS idx_req_asset_transfer_items_approval_status ON requisition_based_asset_transfer_requwest_items(asset_requester_approval_status) WHERE deleted_at IS NULL;
        CREATE INDEX IF NOT EXISTS idx_req_asset_transfer_items_tenant_id ON requisition_based_asset_transfer_requwest_items(tenant_id) WHERE deleted_at IS NULL;
        CREATE INDEX IF NOT EXISTS idx_req_asset_transfer_items_composite ON requisition_based_asset_transfer_requwest_items(requisition_based_asset_transfer_requwest, asset_item_id, isactive) WHERE deleted_at IS NULL;

        -- Add check constraints for status values
        ALTER TABLE requisition_based_asset_transfer_requwest 
        DROP CONSTRAINT IF EXISTS chk_requisition_status,
        ADD CONSTRAINT chk_requisition_status 
        CHECK (requisition_status IN ('PENDING', 'APPROVED', 'REJECTED'));

        ALTER TABLE requisition_based_asset_transfer_requwest_items 
        DROP CONSTRAINT IF EXISTS chk_asset_requester_approval_status,
        ADD CONSTRAINT chk_asset_requester_approval_status 
        CHECK (asset_requester_approval_status IN ('PENDING', 'APPROVED', 'REJECTED'));
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        -- Drop constraints
        ALTER TABLE requisition_based_asset_transfer_requwest DROP CONSTRAINT IF EXISTS chk_requisition_status;
        ALTER TABLE requisition_based_asset_transfer_requwest_items DROP CONSTRAINT IF EXISTS chk_asset_requester_approval_status;

        -- Drop indexes
        DROP INDEX IF EXISTS idx_req_asset_transfer_tenant_id;
        DROP INDEX IF EXISTS idx_req_asset_transfer_requisition_by;
        DROP INDEX IF EXISTS idx_req_asset_transfer_based_requisition;
        DROP INDEX IF EXISTS idx_req_asset_transfer_status;
        DROP INDEX IF EXISTS idx_req_asset_transfer_requested_date;
        DROP INDEX IF EXISTS idx_req_asset_transfer_is_cancelled;
        DROP INDEX IF EXISTS idx_req_asset_transfer_composite;
        DROP INDEX IF EXISTS idx_req_asset_transfer_requisition_id;
        DROP INDEX IF EXISTS idx_req_asset_transfer_items_transfer_id;
        DROP INDEX IF EXISTS idx_req_asset_transfer_items_asset_item_id;
        DROP INDEX IF EXISTS idx_req_asset_transfer_items_based_req_items;
        DROP INDEX IF EXISTS idx_req_asset_transfer_items_approval_status;
        DROP INDEX IF EXISTS idx_req_asset_transfer_items_tenant_id;
        DROP INDEX IF EXISTS idx_req_asset_transfer_items_composite;

        -- Drop sequence
        DROP SEQUENCE IF EXISTS requisition_based_asset_transfer_id_seq;
        SQL);
    }
};
