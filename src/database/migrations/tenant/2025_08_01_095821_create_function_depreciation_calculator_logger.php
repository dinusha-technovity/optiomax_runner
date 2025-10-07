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
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION log_depreciation_batch(
                p_start_time TIMESTAMP,
                p_end_time TIMESTAMP,
                p_total_assets INTEGER,
                p_success_count INTEGER,
                p_failed_count INTEGER,
                p_tenant_id BIGINT DEFAULT NULL,
                p_failed_assets JSONB DEFAULT NULL,
                p_message TEXT DEFAULT NULL,
                p_system_errors JSONB DEFAULT NULL,
                p_executed_by VARCHAR DEFAULT 'system'
            ) RETURNS BIGINT AS $$
            DECLARE
                v_batch_id BIGINT;
            BEGIN
                INSERT INTO depreciation_log (
                    start_time,
                    end_time,
                    total_assets,
                    success_count,
                    failed_count,
                    failed_assets,
                    system_errors,
                    message,
                    executed_by,
                    tenant_id,
                    created_at,
                    updated_at
                ) VALUES (
                    p_start_time,
                    p_end_time,
                    p_total_assets,
                    p_success_count,
                    p_failed_count,
                    p_failed_assets,
                    p_system_errors,
                    p_message,
                    p_executed_by,
                    p_tenant_id,
                    NOW(),
                    NOW()
                )
                RETURNING id INTO v_batch_id;

                RETURN v_batch_id;
            END;
            $$ LANGUAGE plpgsql;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS log_depreciation_batch( TIMESTAMP, TIMESTAMP, INTEGER, INTEGER, INTEGER, BIGINT, JSONB, TEXT, VARCHAR);');

    }
};
