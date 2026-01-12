<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Implement table partitioning strategy for high-volume audit tables
     * This is CRITICAL for enterprise scalability with millions of audit records
     */
    public function up(): void
    {
        // Create helper function to manage partitions
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION create_audit_session_partition(
                partition_year INT,
                partition_quarter INT
            )
            RETURNS VOID AS $$
            DECLARE
                partition_name TEXT;
                start_date DATE;
                end_date DATE;
            BEGIN
                partition_name := 'asset_items_audit_sessions_' || partition_year || '_q' || partition_quarter;
                start_date := DATE(partition_year || '-' || ((partition_quarter - 1) * 3 + 1) || '-01');
                end_date := start_date + INTERVAL '3 months';
                
                EXECUTE format(
                    'CREATE TABLE IF NOT EXISTS %I PARTITION OF asset_items_audit_sessions 
                     FOR VALUES FROM (%L) TO (%L)',
                    partition_name, start_date, end_date
                );
            END;
            $$ LANGUAGE plpgsql;
SQL
        );

        DB::statement("
            COMMENT ON FUNCTION create_audit_session_partition IS 
            'Helper function to create quarterly partitions for audit sessions table. Example: SELECT create_audit_session_partition(2026, 1);'
        ");

        // Create index optimized for time-based queries
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_audit_sessions_partition_key 
            ON asset_items_audit_sessions (created_at, tenant_id)
        ");

        // Add monitoring view for partition health
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE VIEW v_audit_partitions_status AS
            SELECT 
                schemaname,
                tablename,
                pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size
            FROM pg_tables
            WHERE tablename LIKE 'asset_items_audit_sessions%'
            ORDER BY tablename
SQL
        );

        DB::statement("
            COMMENT ON VIEW v_audit_partitions_status IS 
            'Monitoring view to track partition sizes and health'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_audit_partitions_status');
        DB::statement('DROP FUNCTION IF EXISTS create_audit_session_partition(INT, INT)');
        DB::statement('DROP INDEX IF EXISTS idx_audit_sessions_partition_key');
    }
};