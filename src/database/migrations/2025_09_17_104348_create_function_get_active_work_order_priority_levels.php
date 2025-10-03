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
        DB::unprepared(<<<SQL
        DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_active_work_order_priority_levels'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
            
        CREATE OR REPLACE FUNCTION get_active_work_order_priority_levels(
            _tenant_id BIGINT DEFAULT NULL,
            p_id BIGINT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            id BIGINT,
            name VARCHAR,
            level INT,
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
                'Active work order priority levels fetched successfully'::TEXT AS message,
                wopl.id,
                wopl.name::VARCHAR,
                wopl.level,
                wopl.isactive,
                wopl.tenant_id,
                wopl.created_at,
                wopl.updated_at
            FROM work_order_priority_levels wopl
            WHERE (p_id IS NULL OR wopl.id = p_id)
              AND (_tenant_id IS NULL OR wopl.tenant_id = _tenant_id)
              AND wopl.isactive = true
            ORDER BY wopl.level;  -- Ordered by priority level
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_active_work_order_priority_levels');
    }
};
