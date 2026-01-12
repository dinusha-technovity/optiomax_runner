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
     * Enterprise-level optimization for assets table (parent/group level)
     * This table is critical as it's the parent for all asset_items
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // ============================================
            // CRITICAL: Multi-tenant Isolation Indexes
            // ============================================
            // Most important index - used in 90%+ of queries
            $table->index(['tenant_id', 'deleted_at', 'isactive'], 'idx_assets_tenant_active');
            
            // ============================================
            // Asset Classification Indexes
            // ============================================
            // Category-based queries (reports by category)
            $table->index(['category', 'tenant_id', 'deleted_at'], 'idx_assets_category_tenant');
            
            // Sub-category queries (detailed filtering)
            $table->index(['sub_category', 'tenant_id', 'deleted_at'], 'idx_assets_subcategory_tenant');
            
            // Hierarchical queries (category -> sub-category)
            $table->index(['category', 'sub_category', 'tenant_id'], 'idx_assets_hierarchy');
            
            // ============================================
            // Search & Lookup Indexes
            // ============================================
            // Asset name search (autocomplete, search functionality)
            // Using text pattern ops for LIKE queries
            $table->index(['name', 'tenant_id', 'deleted_at'], 'idx_assets_name_search');
            
            // ============================================
            // User & Audit Trail Indexes
            // ============================================
            // Registered by queries (who created what assets)
            $table->index(['registered_by', 'tenant_id', 'deleted_at'], 'idx_assets_registered_by');
            
            // Registration timeline (chronological reports)
            $table->index(['registered_by', 'created_at', 'tenant_id'], 'idx_assets_registration_timeline');
            
            // ============================================
            // Status & Activity Indexes
            // ============================================
            // Active assets only (most common filter)
            $table->index(['isactive', 'tenant_id', 'deleted_at'], 'idx_assets_active_status');
            
            // Recently created assets (dashboard widgets)
            $table->index(['created_at', 'tenant_id', 'isactive'], 'idx_assets_recent');
            
            // Recently updated assets (change tracking)
            $table->index(['updated_at', 'tenant_id', 'isactive'], 'idx_assets_modified');
            
            // ============================================
            // Composite Indexes for Complex Queries
            // ============================================
            // Dashboard query: active assets by category with counts
            $table->index(['tenant_id', 'isactive', 'category', 'deleted_at'], 'idx_assets_dashboard_summary');
            
            // Reporting query: assets by category and registration date
            $table->index(['category', 'created_at', 'tenant_id', 'deleted_at'], 'idx_assets_category_timeline');
        });

        // ============================================
        // GIN Indexes for JSONB Columns
        // ============================================
        // Enable fast searches within JSON documents
        DB::statement('CREATE INDEX IF NOT EXISTS idx_assets_thumbnail_gin ON assets USING gin(thumbnail_image)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_assets_details_gin ON assets USING gin(asset_details)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_assets_classification_gin ON assets USING gin(asset_classification)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_assets_reading_params_gin ON assets USING gin(reading_parameters)');

        // ============================================
        // Full-Text Search Support (Optional but Recommended)
        // ============================================
        // Add tsvector column for full-text search on name and description
        DB::statement("
            ALTER TABLE assets 
            ADD COLUMN IF NOT EXISTS search_vector tsvector 
            GENERATED ALWAYS AS (
                setweight(to_tsvector('english', COALESCE(name, '')), 'A') ||
                setweight(to_tsvector('english', COALESCE(asset_description, '')), 'B')
            ) STORED;
        ");
        
        DB::statement('CREATE INDEX IF NOT EXISTS idx_assets_search_vector ON assets USING gin(search_vector)');

        // ============================================
        // Partial Indexes for Performance
        // ============================================
        // Index only active, non-deleted records (most common queries)
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_assets_active_only 
            ON assets (tenant_id, category, sub_category)
            WHERE deleted_at IS NULL AND isactive = true;
        ");

        // Index for soft-deleted records (cleanup/restore operations)
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_assets_deleted_only 
            ON assets (tenant_id, deleted_at)
            WHERE deleted_at IS NOT NULL;
        ");

        // ============================================
        // Statistics & Cardinality
        // ============================================
        // Update table statistics for query optimizer
        DB::statement('ANALYZE assets');

        // ============================================
        // Table Documentation
        // ============================================
        DB::statement("
            COMMENT ON TABLE assets IS 
            'Parent/group level asset definitions. Each asset can have multiple asset_items. 
             Optimized for multi-tenant queries with comprehensive indexing.';
        ");
        
        DB::statement("COMMENT ON COLUMN assets.tenant_id IS 'Multi-tenant isolation - CRITICAL: Always include in WHERE clauses'");
        DB::statement("COMMENT ON COLUMN assets.search_vector IS 'Auto-generated full-text search vector for name and description'");
        DB::statement("COMMENT ON COLUMN assets.asset_details IS 'Flexible JSON storage - indexed with GIN for fast searches'");
        DB::statement("COMMENT ON COLUMN assets.asset_classification IS 'Classification metadata - indexed for filtering'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop partial indexes first
        DB::statement('DROP INDEX IF EXISTS idx_assets_deleted_only');
        DB::statement('DROP INDEX IF EXISTS idx_assets_active_only');
        
        // Drop full-text search
        DB::statement('DROP INDEX IF EXISTS idx_assets_search_vector');
        DB::statement('ALTER TABLE assets DROP COLUMN IF EXISTS search_vector');
        
        // Drop GIN indexes
        DB::statement('DROP INDEX IF EXISTS idx_assets_reading_params_gin');
        DB::statement('DROP INDEX IF EXISTS idx_assets_classification_gin');
        DB::statement('DROP INDEX IF EXISTS idx_assets_details_gin');
        DB::statement('DROP INDEX IF EXISTS idx_assets_thumbnail_gin');

        // Drop regular indexes
        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex('idx_assets_category_timeline');
            $table->dropIndex('idx_assets_dashboard_summary');
            $table->dropIndex('idx_assets_modified');
            $table->dropIndex('idx_assets_recent');
            $table->dropIndex('idx_assets_active_status');
            $table->dropIndex('idx_assets_registration_timeline');
            $table->dropIndex('idx_assets_registered_by');
            $table->dropIndex('idx_assets_name_search');
            $table->dropIndex('idx_assets_hierarchy');
            $table->dropIndex('idx_assets_subcategory_tenant');
            $table->dropIndex('idx_assets_category_tenant');
            $table->dropIndex('idx_assets_tenant_active');
        });
    }
};