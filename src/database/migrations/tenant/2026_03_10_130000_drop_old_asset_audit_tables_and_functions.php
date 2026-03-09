<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Drop old asset audit tables and functions to recreate with ISO integration
     */
    public function up(): void
    {
        // Drop functions first (they depend on tables)
        DB::unprepared('DROP FUNCTION IF EXISTS submit_single_audit_score CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS batch_submit_audit_records CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS calculate_and_store_audit_scores CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS approve_or_reject_audit CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS complete_audit_session CASCADE');
        
        // Drop dependent views/materialized views first
        DB::unprepared('DROP MATERIALIZED VIEW IF EXISTS mv_audit_summary_by_tenant CASCADE');

        // Drop dependent tables in correct order (child first, parent last)
        // CASCADE handles any remaining dependent objects (indexes, FKs, views)
        DB::unprepared('DROP TABLE IF EXISTS asset_items_audit_score CASCADE');
        DB::unprepared('DROP TABLE IF EXISTS asset_items_audited_record CASCADE');
        DB::unprepared('DROP TABLE IF EXISTS asset_items_audit_sessions CASCADE');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reverse - old structure incompatible with new
        throw new \Exception('Cannot rollback this migration. Old structure incompatible with ISO audit sessions.');
    }
};