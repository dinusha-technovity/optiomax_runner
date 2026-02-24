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
                    WHERE proname = 'get_asset_upgrade_outcomes'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION get_asset_upgrade_outcomes(
                p_tenant_id BIGINT
            )
            RETURNS TABLE (
                id BIGINT,
                title TEXT,
                description TEXT
            ) AS $$
            BEGIN
                RETURN QUERY
                SELECT 
                    auo.id,
                    auo.outcome_text AS title,
                    COALESCE(auo.description, ''::TEXT) AS description
                FROM asset_upgrade_outcomes auo
                WHERE (auo.tenant_id = p_tenant_id OR auo.tenant_id IS NULL)
                  AND auo.is_active = true
                ORDER BY auo.id ASC;
            END;
            $$ LANGUAGE plpgsql;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(<<<SQL
            DO $$
            DECLARE
                r RECORD;
            BEGIN 
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_asset_upgrade_outcomes'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
        SQL);
    }
};
