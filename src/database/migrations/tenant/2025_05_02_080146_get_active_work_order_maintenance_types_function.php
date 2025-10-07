<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
           DROP FUNCTION IF EXISTS get_active_work_order_maintenance_types(bigint,bigint);
            CREATE OR REPLACE FUNCTION get_active_work_order_maintenance_types(
                _tenant_id BIGINT DEFAULT NULL,
                p_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                name VARCHAR,
                description TEXT,
                deleted_at TIMESTAMP,
                isactive BOOLEAN,
                tenant_id BIGINT,
                created_at TIMESTAMP,
                updated_at TIMESTAMP
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Return all active records matching the criteria
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Active maintenance types fetched successfully'::TEXT AS message,
                    womt.id,
                    womt.name,
                    womt.description,
                    womt.deleted_at,
                    womt.isactive,
                    womt.tenant_id,
                    womt.created_at,
                    womt.updated_at
                FROM work_order_maintenance_types womt
                WHERE (p_id IS NULL OR womt.id = p_id)
                AND (_tenant_id IS NULL OR womt.tenant_id = _tenant_id)
                AND womt.isactive = true
                AND womt.deleted_at IS NULL;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_active_work_order_maintenance_types');
    }
};