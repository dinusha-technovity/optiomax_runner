<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add database-level performance optimizations and monitoring
     */
    public function up(): void
    {
        // Create function to analyze table statistics (for query planner)
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION analyze_audit_tables()
            RETURNS TEXT AS $$
            BEGIN
                ANALYZE asset_items;
                ANALYZE asset_items_audit_sessions;
                ANALYZE asset_items_audited_record;
                ANALYZE asset_items_audit_score;
                ANALYZE asset_audit_variable;
                ANALYZE asset_audit_variable_type;
                ANALYZE asset_audit_variable_assignments;
                
                RETURN 'All audit-related tables analyzed successfully';
            END;
            $$ LANGUAGE plpgsql
SQL
        );

        DB::statement("
            COMMENT ON FUNCTION analyze_audit_tables IS 
            'Updates table statistics for query optimizer. Run after bulk imports or major data changes.'
        ");

        // Create materialized view for audit analytics dashboard
        DB::unprepared(<<<'SQL'
            CREATE MATERIALIZED VIEW IF NOT EXISTS mv_audit_summary_by_tenant AS
            SELECT 
                ai.tenant_id,
                COUNT(DISTINCT ai.id) as total_asset_items,
                COUNT(DISTINCT aias.id) as total_audit_sessions,
                COUNT(DISTINCT aiar.id) as total_audit_records,
                AVG(aias_score.final_score) as avg_final_score,
                MIN(aias.created_at) as first_audit_date,
                MAX(aias.created_at) as last_audit_date,
                COUNT(DISTINCT aias.audit_by) as unique_auditors
            FROM asset_items ai
            LEFT JOIN asset_items_audit_sessions aias ON aias.asset_item_id = ai.id 
                AND aias.deleted_at IS NULL
            LEFT JOIN asset_items_audited_record aiar ON aiar.asset_items_audit_sessions_id = aias.id 
                AND aiar.deleted_at IS NULL
            LEFT JOIN asset_items_audit_score aias_score ON aias_score.asset_items_audit_sessions_id = aias.id 
                AND aias_score.deleted_at IS NULL
            WHERE ai.deleted_at IS NULL 
                AND ai.isactive = TRUE
            GROUP BY ai.tenant_id
SQL
        );

        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS idx_mv_audit_summary_tenant ON mv_audit_summary_by_tenant (tenant_id)");

        DB::statement("
            COMMENT ON MATERIALIZED VIEW mv_audit_summary_by_tenant IS 
            'Pre-aggregated audit statistics per tenant for dashboard performance. Refresh periodically: REFRESH MATERIALIZED VIEW CONCURRENTLY mv_audit_summary_by_tenant;'
        ");

        // Create performance monitoring view
        DB::statement("
            CREATE OR REPLACE VIEW v_audit_table_performance AS
            SELECT
                schemaname,
                relname as tablename,
                pg_size_pretty(pg_total_relation_size(schemaname||'.'||relname)) as total_size,
                pg_size_pretty(pg_relation_size(schemaname||'.'||relname)) as table_size,
                pg_size_pretty(pg_total_relation_size(schemaname||'.'||relname) - pg_relation_size(schemaname||'.'||relname)) as indexes_size,
                n_tup_ins as rows_inserted,
                n_tup_upd as rows_updated,
                n_tup_del as rows_deleted,
                n_live_tup as live_rows,
                n_dead_tup as dead_rows,
                last_vacuum,
                last_autovacuum,
                last_analyze,
                last_autoanalyze
            FROM pg_stat_user_tables
            WHERE relname IN (
                'asset_items',
                'asset_items_audit_sessions',
                'asset_items_audited_record',
                'asset_items_audit_score',
                'asset_audit_variable',
                'asset_audit_variable_type',
                'asset_audit_variable_assignments'
            )
            ORDER BY pg_total_relation_size(schemaname||'.'||relname) DESC
        ");

        DB::statement("
            COMMENT ON VIEW v_audit_table_performance IS 
            'Monitor table sizes, row counts, and maintenance status for audit tables'
        ");

        // Create index usage monitoring view
        DB::statement("
            CREATE OR REPLACE VIEW v_audit_index_usage AS
            SELECT
                schemaname,
                relname as tablename,
                indexrelname as indexname,
                idx_scan as times_used,
                idx_tup_read as rows_read,
                idx_tup_fetch as rows_fetched,
                pg_size_pretty(pg_relation_size(indexrelid)) as index_size,
                CASE 
                    WHEN idx_scan = 0 THEN 'UNUSED - Consider dropping'
                    WHEN idx_scan < 100 THEN 'Low usage'
                    ELSE 'Active'
                END as usage_status
            FROM pg_stat_user_indexes
            WHERE schemaname = 'public'
                AND relname IN (
                    'asset_items',
                    'asset_items_audit_sessions',
                    'asset_items_audited_record',
                    'asset_items_audit_score',
                    'asset_audit_variable',
                    'asset_audit_variable_type',
                    'asset_audit_variable_assignments'
                )
            ORDER BY times_used ASC, pg_relation_size(indexrelid) DESC
        ");

        DB::statement("
            COMMENT ON VIEW v_audit_index_usage IS 
            'Monitor index usage patterns to identify unused or underutilized indexes'
        ");

        // Create slow query detection for audit operations
        DB::statement("
            CREATE OR REPLACE VIEW v_slow_audit_queries AS
            SELECT
                pid,
                now() - query_start as query_duration,
                state,
                query,
                application_name,
                client_addr
            FROM pg_stat_activity
            WHERE state = 'active'
                AND (query ILIKE '%asset_items%' 
                     OR query ILIKE '%audit_sessions%'
                     OR query ILIKE '%audit_variable%')
                AND now() - query_start > interval '5 seconds'
            ORDER BY query_duration DESC
        ");

        DB::statement("
            COMMENT ON VIEW v_slow_audit_queries IS 
            'Identify slow-running queries on audit tables (>5 seconds)'
        ");

        // Add table constraints documentation
        DB::statement("COMMENT ON COLUMN asset_items.tenant_id IS 'Multi-tenant isolation - REQUIRED for all queries'");
        DB::statement("COMMENT ON COLUMN asset_items_audit_sessions.tenant_id IS 'Multi-tenant isolation - REQUIRED for all queries'");
        DB::statement("COMMENT ON COLUMN asset_items_audited_record.tenant_id IS 'Multi-tenant isolation - REQUIRED for all queries'");
        DB::statement("COMMENT ON COLUMN asset_items_audit_score.tenant_id IS 'Multi-tenant isolation - REQUIRED for all queries'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_slow_audit_queries');
        DB::statement('DROP VIEW IF EXISTS v_audit_index_usage');
        DB::statement('DROP VIEW IF EXISTS v_audit_table_performance');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_audit_summary_by_tenant');
        DB::statement('DROP FUNCTION IF EXISTS analyze_audit_tables()');
    }
};