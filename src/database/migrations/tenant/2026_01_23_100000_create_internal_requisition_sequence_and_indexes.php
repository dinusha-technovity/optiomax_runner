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
        -- Create sequence for internal asset requisition IDs
        CREATE SEQUENCE IF NOT EXISTS internal_asset_requisition_id_seq START 1;

        -- Add indexes to internal_asset_requisitions table
        CREATE INDEX IF NOT EXISTS idx_internal_requisitions_tenant_id ON internal_asset_requisitions(tenant_id) WHERE deleted_at IS NULL;
        CREATE INDEX IF NOT EXISTS idx_internal_requisitions_requisition_by ON internal_asset_requisitions(requisition_by) WHERE deleted_at IS NULL;
        CREATE INDEX IF NOT EXISTS idx_internal_requisitions_targeted_person ON internal_asset_requisitions(targeted_responsible_person) WHERE deleted_at IS NULL;
        CREATE INDEX IF NOT EXISTS idx_internal_requisitions_status ON internal_asset_requisitions(requisition_status) WHERE deleted_at IS NULL;
        CREATE INDEX IF NOT EXISTS idx_internal_requisitions_workflow ON internal_asset_requisitions(work_flow_request) WHERE deleted_at IS NULL;
        CREATE INDEX IF NOT EXISTS idx_internal_requisitions_requested_date ON internal_asset_requisitions(requested_date) WHERE deleted_at IS NULL;
        CREATE INDEX IF NOT EXISTS idx_internal_requisitions_composite ON internal_asset_requisitions(tenant_id, requisition_by, isactive) WHERE deleted_at IS NULL;

        -- Add indexes to internal_asset_requisitions_items table
        CREATE INDEX IF NOT EXISTS idx_internal_req_items_requisition_id ON internal_asset_requisitions_items(internal_asset_requisition_id) WHERE deleted_at IS NULL;
        CREATE INDEX IF NOT EXISTS idx_internal_req_items_asset_item_id ON internal_asset_requisitions_items(asset_item_id) WHERE deleted_at IS NULL;
        CREATE INDEX IF NOT EXISTS idx_internal_req_items_selection_type ON internal_asset_requisitions_items(internal_asset_requisitions_item_selection_types_id) WHERE deleted_at IS NULL;
        CREATE INDEX IF NOT EXISTS idx_internal_req_items_priority ON internal_asset_requisitions_items(priority) WHERE deleted_at IS NULL;
        CREATE INDEX IF NOT EXISTS idx_internal_req_items_department ON internal_asset_requisitions_items(department) WHERE deleted_at IS NULL;
        CREATE INDEX IF NOT EXISTS idx_internal_req_items_required_date ON internal_asset_requisitions_items(required_date) WHERE deleted_at IS NULL;
        CREATE INDEX IF NOT EXISTS idx_internal_req_items_tenant_id ON internal_asset_requisitions_items(tenant_id) WHERE deleted_at IS NULL;

        -- Add GIN indexes for JSONB columns
        CREATE INDEX IF NOT EXISTS idx_internal_req_items_other_details ON internal_asset_requisitions_items USING GIN (other_details) WHERE deleted_at IS NULL;
        CREATE INDEX IF NOT EXISTS idx_internal_req_items_related_documents ON internal_asset_requisitions_items USING GIN (related_documents) WHERE deleted_at IS NULL;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        -- Drop indexes
        DROP INDEX IF EXISTS idx_internal_requisitions_tenant_id;
        DROP INDEX IF EXISTS idx_internal_requisitions_requisition_by;
        DROP INDEX IF EXISTS idx_internal_requisitions_targeted_person;
        DROP INDEX IF EXISTS idx_internal_requisitions_status;
        DROP INDEX IF EXISTS idx_internal_requisitions_workflow;
        DROP INDEX IF EXISTS idx_internal_requisitions_requested_date;
        DROP INDEX IF EXISTS idx_internal_requisitions_composite;
        DROP INDEX IF EXISTS idx_internal_req_items_requisition_id;
        DROP INDEX IF EXISTS idx_internal_req_items_asset_item_id;
        DROP INDEX IF EXISTS idx_internal_req_items_selection_type;
        DROP INDEX IF EXISTS idx_internal_req_items_priority;
        DROP INDEX IF EXISTS idx_internal_req_items_department;
        DROP INDEX IF EXISTS idx_internal_req_items_required_date;
        DROP INDEX IF EXISTS idx_internal_req_items_tenant_id;
        DROP INDEX IF EXISTS idx_internal_req_items_other_details;
        DROP INDEX IF EXISTS idx_internal_req_items_related_documents;

        -- Drop sequence
        DROP SEQUENCE IF EXISTS internal_asset_requisition_id_seq;
        SQL);
    }
};
