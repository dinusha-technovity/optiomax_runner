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
                    WHERE proname = 'get_asset_upgrade_reasons'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION get_asset_upgrade_reasons(
                p_tenant_id BIGINT,
                p_category_id BIGINT DEFAULT NULL,
                p_reason_ids BIGINT[] DEFAULT NULL
            )
            RETURNS TABLE (
                id BIGINT,
                label VARCHAR,
                description TEXT
            ) AS $$
            BEGIN
                RETURN QUERY
                SELECT 
                    aur.id,
                    aur.title AS label,
                    aur.description
                FROM asset_upgrade_reasons aur
                WHERE (aur.tenant_id = p_tenant_id OR aur.tenant_id IS NULL)
                  AND aur.is_active = true
                  AND (p_reason_ids IS NULL OR aur.id = ANY(p_reason_ids))
                ORDER BY aur.id ASC;
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

             DECLARE
                r RECORD;
            BEGIN 
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_asset_upgrade_reasons'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
        SQL);
    }
};
