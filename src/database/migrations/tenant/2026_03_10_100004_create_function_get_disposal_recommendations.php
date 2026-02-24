<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_disposal_recommendations'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

        CREATE OR REPLACE FUNCTION get_disposal_recommendations(
            _tenant_id BIGINT
        )
        RETURNS JSON
        LANGUAGE plpgsql
        AS $fn$
        DECLARE
            v_data JSON;
        BEGIN
            SELECT COALESCE(json_agg(row_to_json(t)), '[]'::JSON)
            INTO v_data
            FROM (
                SELECT
                    dr.id,
                    dr.code,
                    dr.name,
                    dr.description
                FROM disposal_recommendations dr
                WHERE (dr.tenant_id = _tenant_id OR dr.tenant_id IS NULL)
                  AND dr.is_active = TRUE
                  AND dr.deleted_at IS NULL
                ORDER BY dr.name ASC
            ) t;

            RETURN json_build_object(
                'success', TRUE,
                'message', 'Disposal recommendations fetched successfully',
                'data', v_data
            );
        END;
        $fn$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'get_disposal_recommendations'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
        SQL);
    }
};
