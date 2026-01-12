<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Enterprise-level optimization for asset_items table
     * This table will contain millions of records in a worldwide deployment
     */
    public function up(): void
    {
        Schema::table('asset_items', function (Blueprint $table) {
            // Multi-tenant isolation - CRITICAL for worldwide SaaS
            $table->index(['tenant_id', 'deleted_at', 'isactive'], 'idx_asset_items_tenant_active');
            
            // Parent asset lookup with tenant isolation
            $table->index(['asset_id', 'tenant_id', 'deleted_at'], 'idx_asset_items_asset_tenant');
            
            // Serial number search (unique identifier lookups)
            $table->index(['serial_number', 'tenant_id', 'deleted_at'], 'idx_asset_items_serial');
            
            // Model number search (find all items of same model)
            $table->index(['model_number', 'tenant_id', 'deleted_at'], 'idx_asset_items_model');
            
            // QR code scanning lookups - high frequency operation
            $table->index(['qr_code', 'tenant_id'], 'idx_asset_items_qr');
            
            // Supplier-based queries (procurement reports)
            $table->index(['supplier', 'tenant_id', 'deleted_at'], 'idx_asset_items_supplier');
            
            // Department-based queries (asset allocation reports)
            $table->index(['department', 'tenant_id', 'deleted_at'], 'idx_asset_items_department');
            
            // Responsible person queries (user asset assignments)
            $table->index(['responsible_person', 'tenant_id', 'deleted_at'], 'idx_asset_items_responsible');
            
            // Purchase type analytics
            $table->index(['purchase_type', 'tenant_id', 'deleted_at'], 'idx_asset_items_purchase_type');
            
            // Warranty expiration alerts
            $table->index(['warranty_exparing_at', 'tenant_id', 'isactive'], 'idx_asset_items_warranty_expiry');
            
            // Insurance expiration alerts
            $table->index(['insurance_exparing_at', 'tenant_id', 'isactive'], 'idx_asset_items_insurance_expiry');
            
            // Registered by tracking (audit trail)
            $table->index(['registered_by', 'created_at', 'tenant_id'], 'idx_asset_items_registered');
            
            // GIN indexes for JSONB columns (document search)
            // These are critical for enterprise search functionality
        });

        // Add GIN indexes for JSONB columns using raw SQL
        DB::statement('CREATE INDEX IF NOT EXISTS idx_asset_items_thumbnail_gin ON asset_items USING gin(thumbnail_image)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_asset_items_documents_gin ON asset_items USING gin(item_documents)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_asset_items_purchase_doc_gin ON asset_items USING gin(purchase_document)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_asset_items_insurance_doc_gin ON asset_items USING gin(insurance_document)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_asset_items_classification_gin ON asset_items USING gin(asset_classification)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_asset_items_reading_params_gin ON asset_items USING gin(reading_parameters)');

        // Add comment for database documentation
        DB::statement("COMMENT ON TABLE asset_items IS 'Stores individual asset items with comprehensive tracking and multi-tenant isolation'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_items', function (Blueprint $table) {
            $table->dropIndex('idx_asset_items_tenant_active');
            $table->dropIndex('idx_asset_items_asset_tenant');
            $table->dropIndex('idx_asset_items_serial');
            $table->dropIndex('idx_asset_items_model');
            $table->dropIndex('idx_asset_items_qr');
            $table->dropIndex('idx_asset_items_supplier');
            $table->dropIndex('idx_asset_items_department');
            $table->dropIndex('idx_asset_items_responsible');
            $table->dropIndex('idx_asset_items_purchase_type');
            $table->dropIndex('idx_asset_items_warranty_expiry');
            $table->dropIndex('idx_asset_items_insurance_expiry');
            $table->dropIndex('idx_asset_items_registered');
        });

        DB::statement('DROP INDEX IF EXISTS idx_asset_items_thumbnail_gin');
        DB::statement('DROP INDEX IF EXISTS idx_asset_items_documents_gin');
        DB::statement('DROP INDEX IF EXISTS idx_asset_items_purchase_doc_gin');
        DB::statement('DROP INDEX IF EXISTS idx_asset_items_insurance_doc_gin');
        DB::statement('DROP INDEX IF EXISTS idx_asset_items_classification_gin');
        DB::statement('DROP INDEX IF EXISTS idx_asset_items_reading_params_gin');
    }
};
