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
                WHERE proname = 'update_system_setting'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;
        

        CREATE OR REPLACE FUNCTION update_system_setting(
            p_tenant_id BIGINT,
            p_user_id   BIGINT,
            p_key       TEXT,
            p_value     TEXT
        )
        RETURNS JSONB
        LANGUAGE plpgsql
        AS $$
        DECLARE
            v_old_record JSONB;
            v_new_record JSONB;
            v_setting_id BIGINT;
            v_settings   JSONB;
        BEGIN
            -- Lock and fetch existing setting
            SELECT id,
                to_jsonb(system_settings.*)
            INTO v_setting_id, v_old_record
            FROM system_settings
            WHERE tenant_id = p_tenant_id
            AND key = p_key
            AND is_active = TRUE
            FOR UPDATE;

            IF NOT FOUND THEN
                RETURN '[]'::jsonb;
            END IF;

            -- Prevent updates to non-editable settings
            IF (v_old_record->>'is_editable')::BOOLEAN = FALSE THEN
                RETURN '[]'::jsonb;
            END IF;

            -- Update setting
            UPDATE system_settings
            SET value = p_value,
                updated_by = p_user_id,
                updated_at = NOW()
            WHERE id = v_setting_id
            RETURNING to_jsonb(system_settings.*) INTO v_new_record;

            -- Log activity (non-blocking)
            BEGIN
                PERFORM log_activity(
                    'update_system_setting',
                    format(
                        'System setting "%s" updated from "%s" to "%s"',
                        p_key,
                        v_old_record->>'value',
                        p_value
                    ),
                    'system_settings',
                    v_setting_id,
                    'user',
                    p_user_id,
                    v_new_record,
                    p_tenant_id
                );
            EXCEPTION WHEN OTHERS THEN
                NULL;
            END;

            -- Fetch ALL active system settings (same as get_system_settings)
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
                WHERE proname = 'update_system_setting'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END
        $$;
        SQL);
    }
};
