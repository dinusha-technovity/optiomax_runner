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
        CREATE OR REPLACE FUNCTION get_active_work_order_budget_codes(
            _tenant_id BIGINT DEFAULT NULL,
            p_id BIGINT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            id BIGINT,
            code VARCHAR,
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
                'Active budget codes fetched successfully'::TEXT AS message,
                wobc.id,
                wobc.code::VARCHAR,
                wobc.name::VARCHAR,
                wobc.description,
                wobc.deleted_at,
                wobc.isactive,
                wobc.tenant_id,
                wobc.created_at,
                wobc.updated_at
            FROM work_order_budget_codes wobc
            WHERE (p_id IS NULL OR wobc.id = p_id)
            AND (_tenant_id IS NULL OR wobc.tenant_id = _tenant_id)
            AND wobc.isactive = true
            AND wobc.deleted_at IS NULL;
        END;
        $$;

        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_active_work_order_budget_codes');
    }
};
