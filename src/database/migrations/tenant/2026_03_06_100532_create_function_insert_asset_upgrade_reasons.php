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
                    WHERE proname = 'insert_asset_upgrade_reason'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION insert_asset_upgrade_reason(
            p_reason_text TEXT,
            p_description TEXT DEFAULT NULL,
            p_tenant_id BIGINT DEFAULT NULL,
            p_is_active BOOLEAN DEFAULT TRUE
        )
        RETURNS BIGINT
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_outcome_id BIGINT;
        BEGIN

            -- Tenant validation
            IF p_tenant_id IS NULL THEN
                RAISE EXCEPTION 'tenant_id cannot be NULL';
            END IF;

            -- Insert record
            INSERT INTO asset_upgrade_replace_reasons (
                title,
                description,
                tenant_id,
                is_active,
                created_at,
                updated_at,
                code
            )
            VALUES (
                p_reason_text,
                p_description,
                p_tenant_id,
                COALESCE(p_is_active, TRUE),
                NOW(),
                NOW(), 
               REPLACE(p_reason_text, ' ', '_')
            )
            RETURNING id INTO v_outcome_id;

            RETURN v_outcome_id;

        EXCEPTION
            WHEN unique_violation THEN
                RAISE EXCEPTION 
                'Duplicate reason definition';
        END;
        $$;
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
                    WHERE proname = 'insert_asset_upgrade_reason'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
        SQL);
    }
};
