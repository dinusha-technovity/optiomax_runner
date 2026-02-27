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
        DB::unprepared(<<<'SQL'
        DO $$
        DECLARE
            r RECORD;
        BEGIN
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'get_system_settings'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_system_settings(
            p_tenant_id BIGINT,
            p_user_id   BIGINT
        )
        RETURNS JSONB
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_settings JSONB;
        BEGIN
            SELECT jsonb_agg(
                jsonb_build_object(
                    'key', key,
                    'value', value,
                    'type', type,
                    'category', category,
                    'label', label,
                    'description', description,
                    'is_editable', is_editable,
                    'is_active', is_active,
                    'is_default', is_default,
                    'metadata', metadata
                )
                ORDER BY category, key
            )
            INTO v_settings
            FROM system_settings
            WHERE tenant_id = p_tenant_id
            AND is_active = TRUE;

            RETURN COALESCE(v_settings, '[]'::jsonb);

        EXCEPTION WHEN OTHERS THEN
            RETURN '[]'::jsonb;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
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
                WHERE proname = 'get_system_settings'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;
        SQL);
    }
};
