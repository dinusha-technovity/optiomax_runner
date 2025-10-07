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
        CREATE OR REPLACE FUNCTION get_active_work_order_types(
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
                'Active work order types fetched successfully'::TEXT AS message,
                wot.id,
                wot.name::VARCHAR,
                wot.description,
                wot.deleted_at,
                wot.isactive,
                wot.tenant_id,
                wot.created_at,
                wot.updated_at
            FROM work_order_types wot
            WHERE (p_id IS NULL OR wot.id = p_id)
            AND (_tenant_id IS NULL OR wot.tenant_id = _tenant_id)
            AND wot.isactive = true
            AND wot.deleted_at IS NULL
            ORDER BY wot.name;  -- Added alphabetical ordering by name
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_active_work_order_types');
    }
};
