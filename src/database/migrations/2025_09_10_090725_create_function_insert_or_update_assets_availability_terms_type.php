<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
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
                    WHERE proname = 'insert_or_update_assets_availability_terms_type'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;

            CREATE OR REPLACE FUNCTION insert_or_update_assets_availability_terms_type(
                IN p_terms_type_name TEXT,
                IN p_description TEXT DEFAULT NULL,
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_id BIGINT DEFAULT NULL,
                IN p_current_time TIMESTAMPTZ DEFAULT now()
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                return_id BIGINT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                new_id BIGINT;
            BEGIN
                -- Insert
                IF p_id IS NULL OR p_id = 0 THEN
                    INSERT INTO asset_availability_term_types (
                        name,
                        description,
                        isactive,
                        tenant_id,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        p_terms_type_name,
                        p_description,
                        true,
                        p_tenant_id,
                        p_current_time,
                        p_current_time
                    )
                    RETURNING id INTO new_id;

                    RETURN QUERY SELECT 'SUCCESS', 'Terms type inserted successfully', new_id;

                ELSE
                    -- Update
                    UPDATE asset_availability_term_types
                    SET
                        name = p_terms_type_name,
                        description = p_description,
                        updated_at = p_current_time
                    WHERE id = p_id
                    RETURNING id INTO new_id;

                    RETURN QUERY SELECT 'SUCCESS', 'Terms type updated successfully', new_id;
                END IF;
            END;
            $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS insert_or_update_assets_availability_terms_type(
            TEXT, TEXT, BIGINT, BIGINT, TIMESTAMPTZ
        );");
    }
};
